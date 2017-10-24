<?php
namespace Module\TenderBin\Model\Driver\Mongo;

use Module\TenderBin\Model\Entity;
use Module\TenderBin\Model\Driver\Mongo;
use Module\TenderBin\Exception\exDuplicateEntry;
use Module\TenderBin\Interfaces\DownloadFileInterface;
use Module\MongoDriver\Model\Repository\aRepository;
use Module\TenderBin\Interfaces\Model\iBindata;
use Module\TenderBin\Interfaces\Model\Repo\iRepoBindata;
use Module\TenderBin\Storage\DownloadFile;
use Module\TenderBin\Storage\StorageGridFS;
use MongoDB\BSON\ObjectID;
use MongoDB\BSON\UTCDatetime;
use MongoDB\Driver\Cursor;
use Psr\Http\Message\UploadedFileInterface;

// TODO return entity object instead of persistence entity
//      use protected method to interchange these two types between each other
//      also when persist we can not ignore some fields and when retrieve it the orig fields included

class BindataRepo
    extends aRepository
    implements iRepoBindata
{
    /** @var StorageGridFS */
    protected $storage;
    /** @var callable */
    protected $_functorIDGenerator;


    /**
     * Initialize Object
     *
     */
    protected function __init()
    {
        $this->setModelPersist(new Mongo\BindataEntity);
        $this->storage = new StorageGridFS($this->gateway);
    }

    /**
     * Generate next unique identifier to persist
     * data with
     *
     * @param null|string $id
     *
     * @return mixed
     * @throws \Exception
     */
    function attainNextIdentifier($id = null)
    {
        if ($this->_functorIDGenerator)
            // Generator will build ID
            return call_user_func($this->_functorIDGenerator, $id, $this);

        try {
            $objectId = ($id !== null) ? new ObjectID( (string)$id ) : new ObjectID;
        } catch (\Exception $e) {
            throw new \Exception(sprintf('Invalid Persist (%s) Id is Given.', $id));
        }

        return $objectId;
    }
    
    /**
     * Set ID Generator Callable
     * 
     * @param callable $callable function($id=null, $self=null): iObjectID // generated id
     * 
     * @return $this
     */
    function setIdentifierGenerator($callable)
    {
        if (!is_callable($callable))
            throw new \InvalidArgumentException(sprintf(
                'Must be Callable. given: (%s).'
                , \Poirot\Std\flatten($callable)
            ));
        
        
        $this->_functorIDGenerator = $callable;
        return $this;
    }
    
    
    // ..
    
    /**
     * Persist Bindata
     *
     * - check given entity identifier not exists; must be unique
     * - if Bindata entity has no identifier used ::nextIdentifier
     *   to assign something new
     * - if the entity is subversion then old version for this entity exists must be deleted
     *   and replaced with this one
     *
     * @param iBindata $entity
     *
     * @return iBindata Contains inserted uid
     */
    function insert(iBindata $entity)
    {
        $givenIdentifier = $entity->getIdentifier();
        if ($givenIdentifier) {
            $r = $this->_query()->findOne([
                '_id' => $this->attainNextIdentifier($givenIdentifier),
            ]);

            if ($r)
                throw new exDuplicateEntry(sprintf(
                    'Bindata with Hash (%s) exists.'
                    , (string) $givenIdentifier
                ), 400);
        }


        $dateCreated = $entity->getDateCreated();
        if (!$dateCreated)
            $dateCreated = new \DateTime();

        # Convert given entity to Persistence Entity Object To Insert
        $binData = new Mongo\BindataEntity;
        $binData
            ->setIdentifier( $this->attainNextIdentifier($givenIdentifier) )
            ->setTitle($entity->getTitle())
            ->setVersion($entity->getVersion())
            ->setMeta($entity->getMeta())
            ->setContent($entity->getContent())
            ->setMimeType($entity->getMimeType())
            ->setOwnerIdentifier($entity->getOwnerIdentifier())
            ->setDatetimeExpiration($entity->getDatetimeExpiration())
            ->setDateCreated($dateCreated)
            ->setProtected($entity->isProtected())
        ;


        # check has any same subversion
        if ($entity->getVersion()->getSubversionOf()) {
            // this store as subversion of base bindata and must not has duplicate version
            $sb = $this->findATaggedSubVerOf($entity->getVersion()->getSubversionOf(), $entity->getVersion()->getTag());
            if ($sb) {
                // Delete current version and replace with this one
                $this->deleteOneByHash($sb->getIdentifier());
            }
        }


        # Store BinData File Content In Storage
        if ( $this->_isFile($entity) )
        {
            $content = $entity->getContent();

            if (! $content instanceof DownloadFileInterface )
                // Write File Content
                $fileEntity = $this->storage->write( $content );
            else
                // Assume file retrieved from storage so not write it again
                $fileEntity = $this->storage->getFileDocument( $content->getFSResource() );


            # Make Bin Data For Parent Meta Repository

            if ($content instanceof UploadedFileInterface || $content instanceof DownloadFileInterface) {
                $filename = $content->getClientFilename();
                $binData->setMimeType($content->getClientMediaType());

            } else
                $filename  = $fileEntity->getFilename();

            $binData->setContent([
                '__storage_id' => $fileEntity->get_Id(),
                '__storage'    => get_class($this->storage),
            ]);

            $binData->getMeta()->import([
                'is_file'  => true,
                // keep original filename
                'filename' => $filename,
                'filesize' => $fileEntity->getLength(),
                'md5'      => $fileEntity->getMd5(),
            ]);
        }

        
        # Persist BinData Record 
        $r = $this->_query()->insertOne($binData);

        if ( isset($content) )
            // Give Back Content After Writing It
            $binData->setContent($content);


        # Give back entity with given id and meta record info
        return $binData;
    }

    /**
     * Save Entity By Insert Or Update
     *
     * @param iBindata $entity
     *
     * @return mixed
     */
    function save(iBindata $entity)
    {
        if ( $entity->getIdentifier() )
        {
            if ($existEntity = $this->findOneByHash( $entity->getIdentifier() ))
            {
                // just delete bin meta
                $this->_query()->deleteOne([
                    '_id' => $this->attainNextIdentifier( $entity->getIdentifier() ),
                ]);

                if ( ! $entity->getContent() instanceof DownloadFileInterface ) {
                    if ($existEntity->getContent() instanceof DownloadFileInterface) {
                        // Delete currently related stored file
                        // Because new File Uploaded
                        $this->storage->delete(
                            $this->storage->getFileIDFromStream( $existEntity->getContent()->getFSResource() )
                        );
                    }
                }
            }
        }


        $r = $this->insert($entity);
        return $r;
    }

    /**
     * Find Match By Given Hash ID
     *
     * @param string|mixed $hash
     *
     * @return iBindata|false
     */
    function findOneByHash($hash)
    {
        /** @var BindataEntity $binDataEntity */
        $binDataEntity = $this->_query()->findOne([
            '_id' => $this->attainNextIdentifier($hash),
        ]);

        // Not Found Any Match!!!
        if (!$binDataEntity) return false;


        # Check Whether BinData is Associated To File??
        if ( $this->_isFile($binDataEntity) )
            $this->_loadFileIntoBinData($binDataEntity);


        # Convert Persist entity to Entity Object
        $binData = new Entity\BindataEntity;
        $binData
            ->setIdentifier($binDataEntity->getIdentifier())
            ->setTitle($binDataEntity->getTitle())
            ->setVersion($binDataEntity->getVersion())
            ->setMeta($binDataEntity->getMeta())
            ->setContent($binDataEntity->getContent())
            ->setMimeType($binDataEntity->getMimeType())
            ->setOwnerIdentifier($binDataEntity->getOwnerIdentifier())
            ->setDatetimeExpiration($binDataEntity->getDatetimeExpiration())
            ->setDateCreated($binDataEntity->getDateCreated())
            ->setProtected($binDataEntity->isProtected())
        ;

        return $binData;
    }

    /**
     * Find By Search Term
     *
     * - exclude content from retrieved bins
     *
     * @param array  $expression
     * @param string $offset
     * @param int    $limit
     *
     * @return \Traversable
     */
    function findAll(array $expression, $offset = null, $limit = null)
    {
        # search term to mongo condition
        $expression = \Module\MongoDriver\parseExpressionFromArray($expression);
        $condition  = \Module\MongoDriver\buildMongoConditionFromExpression($expression);

        if ($offset)
            $condition = [
                '_id' => [
                    '$lt' => $this->attainNextIdentifier($offset),
                ]
            ] + $condition;

        $r = $this->_query()->find(
            $condition
            , [
                'limit' => $limit,
                'sort'  => [
                    '_id' => -1,
                ]
            ]
        );

        return $this->_wrapFileLoaderIterator($r);
    }

    /**
     * Find Expired
     *
     * @return \Traversable
     */
    function findExpired()
    {
        $currTime = new \DateTime();

        $r = $this->_query()->find(
            [
                'datetime_expiration_mongo' => [
                    '$lte' => new UTCDatetime($currTime->getTimestamp() * 1000),
                ],
            ]
            , [
                'sort'  => [
                    '_id' => 1,
                ]
            ]
        );

        return $this->_wrapFileLoaderIterator($r);
    }

    /**
     * Delete Bin Data With Given Hash
     *
     * - consider when bin data is file
     * - consider to delete version tags
     *
     * @param string|mixed $hash
     *
     * @return boolean
     */
    function deleteOneByHash($hash)
    {
        $hash = $this->attainNextIdentifier($hash);
        
        # Find and delete object
        /** @var iBindata $binDataEntity */
        $binDataEntity = $this->_query()->findOneAndDelete([
            '_id' => $hash,
        ]);


        # Check Whether BinData is Associated To File??
        if ( $this->_isFile($binDataEntity) ) {
            // Document From Bindata persist
            // will contains content.__storage_id
            // open file from storage with given id
            $content  = $binDataEntity->getContent();
            $this->storage->delete($content['__storage_id']);
        }


        # Delete Tagged Versions
        $subVers = $this->findSubVersionsOf($hash);
        /** @var iBindata $v */
        foreach ($subVers as $v)
            $this->deleteOneByHash($v->getIdentifier());
        
        return true;
    }

    /**
     * Find All Subversions Of a Bin Entity
     *
     * @param string|mixed $hash
     *
     * @return \Iterator of @see iBindata
     */
    function findSubVersionsOf($hash)
    {
        $currStoredVer = $this->_query()->find([
            'version.subversion_of' => $this->attainNextIdentifier( $hash ),
        ]);

        return $this->_wrapFileLoaderIterator($currStoredVer);
    }

    /**
     * Find Subversion Of an Entity Bin IF Has?
     *
     * @param $hash
     * @param $tag
     *
     * @return iBindata|false
     */
    function findATaggedSubVerOf($hash, $tag)
    {
        $r = $this->_query()->findOne([
            'version.subversion_of' => $this->attainNextIdentifier($hash),
            'version.tag'           => (string) $tag,
        ]);

        
        if (!$r) return false;

        # Check Whether BinData is Associated To File??
        if ( $this->_isFile($r) )
            // Retrieve File From Storage
            $r = $this->_loadFileIntoBinData($r);
        
        return $r;
    }


    // ...

    /**
     * Check Weather Given Entity Is File?
     *
     * @param iBindata $binData
     *
     * @return bool
     */
    private function _isFile(iBindata $binData)
    {
        return (
            $binData->getMeta()->has('is_file')
            || $binData->getContent() instanceof UploadedFileInterface
            || $binData->getContent() instanceof DownloadFileInterface
        );
    }

    /**
     * @param Entity\BindataEntity $binDataEntity
     * @return Entity\BindataEntity
     */
    private function _loadFileIntoBinData($binDataEntity)
    {
        // Document From Bindata persist
        // will contains content.__storage_id
        // open file from storage with given id
        $content  = $binDataEntity->getContent();
        $resource = $this->storage->open($content['__storage_id']);

        // Determine the file is retrieved from storage itself
        $content = new DownloadFile(array(
            'stream' => $resource,
            'name'   => $binDataEntity->getMeta()->get('filename'),
            'type'   => $binDataEntity->getMimeType(),
            'size'   => $binDataEntity->getMeta()->get('filesize'),
            'error'  => 0,
        ));


        $binDataEntity->setContent($content);
        return $binDataEntity;
    }

    private function _wrapFileLoaderIterator(Cursor $cursor)
    {
        return new BindataResultSet($this->storage, $cursor);
    }
}
