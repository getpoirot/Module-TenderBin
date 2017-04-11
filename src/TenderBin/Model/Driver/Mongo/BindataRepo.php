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
use MongoDB\BSON\ObjectID;
use MongoDB\GridFS\Exception\FileNotFoundException;
use Poirot\Stream\ResourceStream;
use Poirot\Stream\Streamable;
use Psr\Http\Message\UploadedFileInterface;

// TODO return entity object instead of persistence entity
//      use protected method to interchange these two types between each other
//      also when persist we can not ignore some fields and when retrieve it the orig fields included

class BindataRepo
    extends aRepository
    implements iRepoBindata
{
    /** @var callable */
    protected $_functorIDGenerator;

    
    /**
     * Initialize Object
     *
     */
    protected function __init()
    {
        $this->setModelPersist(new Mongo\BindataEntity);
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
    function genNextIdentifier($id = null)
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
        if ($givenIdentifier && false !== $this->findOneByHash($givenIdentifier))
            throw new exDuplicateEntry(sprintf(
                'Bindata with Hash (%s) exists.'
                , (string) $givenIdentifier
            ), 400);


        $givenIdentifier = $this->genNextIdentifier($givenIdentifier);

        $dateCreated = $entity->getDateCreated();
        if (!$dateCreated)
            $dateCreated = new \DateTime();
        
        # Convert given entity to Persistence Entity Object To Insert
        $binData = new Mongo\BindataEntity;
        $binData
            ->setIdentifier($givenIdentifier)
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
            $sb = $this->findOneTagedSubverOf($entity->getVersion()->getSubversionOf(), $entity->getVersion()->getTag());
            if ($sb) {
                // Delete current version and replace with this one
                $this->deleteOneByHash($sb->getIdentifier());
            }
        }


        # Store BinData File Content In Storage
        if ($entity->getContent() instanceof UploadedFileInterface)
            // Handle file storage return meta record
            $binData = $this->_storeSaveBinDataFile($binData);

        
        # Persist BinData Record 
        $r = $this->_query()->insertOne($binData);


        # Give back entity with given id and meta record info

        $binData->setIdentifier($givenIdentifier);
        return $binData;
    }

    /**
     * // TODO improve update by not have to delete old one and insert again
     *
     * Save Entity By Insert Or Update
     *
     * @param iBindata $entity
     *
     * @return mixed
     */
    function save(iBindata $entity)
    {
        $updateFlag = false;
        if ($entity->getIdentifier())
        {
            if (
                !  ($entity->getContent() instanceof DownloadFileInterface)
                && ($entity->getContent() instanceof UploadedFileInterface)
            )
                // Delete currently related stored file
                $this->_storeDeleteAssociatedFile($entity);


            $updateFlag = true;
        }

        if ( ( $file = $entity->getContent() ) instanceof DownloadFileInterface) {
            // Get Back Content Into Storage Meta Content
            // so not duplicate file into storage ...
            $gridFS  = $this->gateway->selectGridFSBucket();
            $content = [
                '_id'      => $gridFS->getFileIdForStream( $file->getFSResource() ),
                'filename' => $file->getClientFilename(),
            ];
            $entity->setContent($content);

        }


        if ($updateFlag)
            // just delete bin meta
            $this->_query()->deleteOne([
                '_id' => $entity->getIdentifier(),
            ]);

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
        /** @var iBindata $r */
        $r = $this->_query()->findOne([
            '_id' => $this->genNextIdentifier($hash),
        ]);

        // Not Found Any Match!!!
        if (!$r) return false;
        
        # Check Whether BinData is Associated To File??
        if ($this->_storeIsFile($r))
            // Retrieve File From Storage
            $r = $this->_storeRetriveAndInjectFile($r);


        # Convert Persist entity to Entity Object
        $binData = new Entity\BindataEntity;
        $binData
            ->setIdentifier($r->getIdentifier())
            ->setTitle($r->getTitle())
            ->setVersion($r->getVersion())
            ->setMeta($r->getMeta())
            ->setContent($r->getContent())
            ->setMimeType($r->getMimeType())
            ->setOwnerIdentifier($r->getOwnerIdentifier())
            ->setDatetimeExpiration($r->getDatetimeExpiration())
            ->setDateCreated($r->getDateCreated())
            ->setProtected($r->isProtected())
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
    function find(array $expression, $offset = null, $limit = null)
    {
        # search term to mongo condition
        $condition = \Module\MongoDriver\buildMongoConditionFromExpression($expression);

        if ($offset)
            $condition = [
                '_id' => [
                    '$lt' => $this->genNextIdentifier($offset),
                ]
            ] + $condition;

        $r = $this->_query()->find(
            $condition
            , [
                'limit' => $limit,
                'sort'  => [
                    '_id' => -1,
                ],
                'projection' => [
                    '_id'   => true,
                    'title' => true,
                    'mime_type' => true,
                    'version'   => true,
                    'protected' => true,
                    'meta'      => true,
                ]
            ]
        );

        return $r;
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
        $hash = $this->genNextIdentifier($hash);
        
        # Find and delete object
        /** @var iBindata $r */
        $r = $this->_query()->findOneAndDelete([
            '_id' => $hash,
        ]);


        # Check Whether BinData is Associated To File??
        if ($this->_storeIsFile($r))
            $this->_storeDeleteAssociatedFile($r);


        # Delete Tagged Versions
        $subVers = $this->findAllSubversionsOf($hash);
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
     * @return \MongoCursor
     */
    function findAllSubversionsOf($hash)
    {
        $currStoredVer = $this->_query()->find([
            'version.subversion_of' => $this->genNextIdentifier( $hash ),
        ]);

        return $currStoredVer;
    }

    /**
     * Find Subversion Of an Entity Bin IF Has?
     *
     * @param $hash
     * @param $tag
     *
     * @return iBindata|false
     */
    function findOneTagedSubverOf($hash, $tag)
    {
        $hash = $this->genNextIdentifier( $hash );

        $r = $this->_query()->findOne([
            'version.subversion_of' => $hash,
            'version.tag'           => (string) $tag,
        ]);

        
        if (!$r) return false;

        # Check Whether BinData is Associated To File??
        if ($this->_storeIsFile($r))
            // Retrieve File From Storage
            $r = $this->_storeRetriveAndInjectFile($r);
        
        return $r;
    }


    // ...

    function storage(BindataEntity $bindata)
    {
        // TODO all storage interface through this proxy call
        
        // now only return bin data with injected file stream
        return $this->_storeRetriveAndInjectFile($bindata);
    }
    

    // Storage:

    protected function _storeSaveBinDataFile(BindataEntity $binData)
    {
        /** @var UploadedFileInterface $file */
        $file = $binData->getContent();

        if (!$file instanceof UploadedFileInterface)
            throw new \InvalidArgumentException('BinData Has No Contains File.');

        if ($file->getError())
            throw new \Exception('File has error.');


        # Store file in storage

        $gridFS   = $this->gateway->selectGridFSBucket();

        $storageID = $binData->getIdentifier();
        $resGrid   = $gridFS->openUploadStream(
            $file->getClientFilename()
            , array('_id' => $storageID)
        );

        $resGrid    = new ResourceStream($resGrid);
        $streamGrid = new Streamable($resGrid);
        $size       = 0;
        while (!$file->getStream()->eof()) {
            $buff = $file->getStream()->read(2097152);
            $streamGrid->write($buff);
            $size += $streamGrid->getTransCount();
        }


        # Make Bin Data For Parent Meta Repository

        $content = [
            '_id'      => $storageID,
            'filename' => $file->getClientFilename(),
        ];


        $binData = clone $binData;
        $binData->setContent($content);
        $binData->setMimeType($file->getClientMediaType());
        $binData->getMeta()->import([
            '__storage' => 'gridfs',
            'is_file'  => true,
            'filesize' => $size,
        ]);

        return $binData;
    }

    protected function _storeDeleteAssociatedFile(BindataEntity $binData)
    {
        $gridFS  = $this->gateway->selectGridFSBucket();
        
        $content = $binData->getContent();
        if ($content instanceof DownloadFileInterface) {
            // Assume that storage id is equal to identifier
            // for now we have no other way to achieve this
            
            $hash   = $gridFS->getFileIdForStream($content->getFSResource());
        } else {
            /*
             * Meta Content That Storage Write
             *
             * [_id] => MongoDB\BSON\ObjectID,
             * [filename] => Björk - All is full of love.mp3
             */
            $metaStorageData = \Poirot\Std\cast($content)->toArray();
            if (!isset($metaStorageData['_id']))
                throw new \Exception('Mismatch Bindata Filetype.');
            
            $hash = $metaStorageData['_id'];
        }
        
        
        try {
            $gridFS->delete($hash);
        } catch (FileNotFoundException $e) {
            return false;
        }

        return true;
    }

    /**
     * @return iBindata
     * @throws \Exception
     */
    private function _storeRetriveAndInjectFile(BindataEntity $binData)
    {
        if (!$this->_storeIsFile($binData))
            throw new \InvalidArgumentException('Associated BinData is not a file.');


        if ($binData->getContent() instanceof UploadedFileInterface)
            // The File is associated with bin meta content
            return $binData;

        /*
         * Meta Content That Storage Write
         *
         * [_id] => MongoDB\BSON\ObjectID,
         * [filename] => Björk - All is full of love.mp3
         */
        $metaStorageData = \Poirot\Std\cast($binData->getContent())->toArray();

        $gridFS   = $this->gateway->selectGridFSBucket();
        try {
            $res      = $gridFS->openDownloadStream($metaStorageData['_id']);
        } catch (\MongoDB\GridFS\Exception\FileNotFoundException $e) {
            // New Tagged Version Of File May Deleted The Resource.
            throw new \RuntimeException('File Not Found.');
        }
        
        // Determine the file is retrieved from storage itself
        $content = new DownloadFile(array(
            'stream' => $res,
            'name'   => $metaStorageData['filename'],
            'type'   => $binData->getMimeType(),
            'size'   => $binData->getMeta()->get('filesize'),
            'error'  => 0,
        ));

        
        $binData = clone $binData;
        $binData->setContent($content);
        return $binData;
    }

    private function _storeIsFile(BindataEntity $binData)
    {
        return ( $binData->getMeta()->has('is_file') );
    }
}
