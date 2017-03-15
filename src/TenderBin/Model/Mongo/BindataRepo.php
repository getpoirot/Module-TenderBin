<?php
namespace Module\TenderBin\Model\Mongo;

use Module\TenderBin\Exception\exDuplicateEntry;
use Module\TenderBin\Model\Mongo;
use Module\MongoDriver\Model\Repository\aRepository;

use Module\TenderBin\Interfaces\Model\iEntityBindata;
use Module\TenderBin\Interfaces\Model\Repo\iRepoBindata;
use MongoDB\BSON\ObjectID;
use MongoDB\GridFS\Exception\FileNotFoundException;
use Poirot\Stream\ResourceStream;
use Poirot\Stream\Streamable;
use Psr\Http\Message\UploadedFileInterface;


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
        $this->setModelPersist(new Mongo\Bindata);
    }

    /**
     * Generate next unique identifier to persist
     * data with
     *
     * @param null|string $id
     *
     * @return mixed
     */
    function genNextIdentifier($id = null)
    {
        if ($this->_functorIDGenerator)
            // Generator will build ID
            return call_user_func($this->_functorIDGenerator, $id, $this);

        return ($id !== null) ? new ObjectID( (string)$id ) : new ObjectID;
    }


    /**
     * Find By Search Term
     *
     * @param array  $term
     * @param string $offset
     * @param int    $limit
     *
     * @return \Traversable
     */
    function find(array $term, $offset = null, $limit = null)
    {
        # search term to mongo condition
        $condition = $this->__importCondition($term);
        $r = $this->_query()->find(
            $condition
            , [
                'skip' => $offset,
                'limit' => $limit,
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
     * @param iEntityBindata $entity
     *
     * @return iEntityBindata Contains inserted uid
     */
    function insert(iEntityBindata $entity)
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
        $binData = new Mongo\Bindata;
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
        if ($entity->getContent() instanceof UploadedFileInterface) {
            // Handle file storage return meta record
            $binData = $this->_storeBinData($binData);
        }

        
        # Persist BinData Record 
        $r = $this->_query()->insertOne($binData);
        
        
        # Give back entity with given id and meta record info
        $return = clone $binData;
        $return->setIdentifier($givenIdentifier);
        return $return;
    }

    /**
     * Save Entity By Insert Or Update
     *
     * @param iEntityBindata $entity
     *
     * @return mixed
     */
    function save(iEntityBindata $entity)
    {
        if ($entity->getIdentifier())
        {
            // Delete Currently Entity and Replace with Changes

            // just delete bin meta
            $this->_query()->deleteOne([
                '_id' => $entity->getIdentifier(),
            ]);

            if ($entity->getContent() instanceof UploadedFileInterface)
                // Also delete related stored file
                $this->_storeDeleteById($entity->getIdentifier());
        }
        
        $r = $this->insert($entity);
        return $r;
    }

    /**
     * Find Match By Given Hash ID
     *
     * @param string|mixed $hash
     *
     * @return iEntityBindata|false
     */
    function findOneByHash($hash)
    {
        $r = $this->_query()->findOne([
            '_id' => $this->genNextIdentifier($hash),
        ]);

        return $r ? $r : false;
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
        /** @var iEntityBindata $r */
        $r = $this->_query()->findOneAndDelete([
            '_id' => $hash,
        ]);


        # Check Whether BinData is Associated To File??
        if ($r->getMeta()->has('is_file'))
            $this->_storeDeleteById($hash);


        # Delete Tagged Versions
        $subVers = $this->findAllSubversionsOf($hash);
        /** @var iEntityBindata $v */
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
     * @return iEntityBindata|false
     */
    function findOneTagedSubverOf($hash, $tag)
    {
        $hash = $this->genNextIdentifier( $hash );

        $r = $this->_query()->findOne([
            'version.subversion_of' => $hash,
            'version.tag'           => (string) $tag,
        ]);

        return ($r) ? $r : false;
    }
    

    // ....

    protected function _storeBinData(Bindata $binData)
    {
        /** @var UploadedFileInterface $file */
        $file = $binData->getContent();
        if ($file->getError())
            throw new \Exception('File has error.');


        # Store file in storage

        $gridFS   = $this->gateway->selectGridFSBucket();

        $storageID = $binData->getIdentifier();
        $meta = \Poirot\Std\cast($binData->getMeta())->toArray();
        $resGrid = $gridFS->openUploadStream(
            $file->getClientFilename()
            , array('_id' => $storageID, 'metadata' => $meta)
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

        $binData->setContent($content);
        $binData->setMimeType($file->getClientMediaType());
        $binData->getMeta()->import([
            '__storage' => 'gridfs',
            'is_file'  => true,
            'filesize' => $size,
        ]);

        return $binData;
    }

    protected function _storeDeleteById($hash)
    {
        $gridFS   = $this->gateway->selectGridFSBucket();
        try {
            $gridFS->delete($hash);
        } catch (FileNotFoundException $e) {
            return false;
        }

        return true;
    }

    private function __importCondition($term)
    {
        $condition = [];
        foreach ($term as $field => $conditioner) {
            foreach ($conditioner as $o => $vl) {
                if ($o === '$eq') {
                    // 'limit' => [
                    //    '$eq' => [
                    //       40000,
                    //     ]
                    //  ],
                    if (count($vl) > 1)
                        // equality checks for the values of the same field
                        // '$eq' => [100, 200, 300]
                        $condition[$field] = ['$in' => $vl];
                    else
                        // '$eq' => [100]
                        $condition[$field] = current($vl);
                } elseif ($o === '$gt') {
                    $condition[$field] = [
                        '$gt' => $vl,
                    ];
                } elseif ($o === '$lt') {
                    $condition[$field] = [
                        '$lt' => $vl,
                    ];
                } else {
                    // Condition also can be other embed field condition
                    $cond = $this->__importCondition([$o => $vl]);
                    $condition[$field.'.'.$o] = current($cond);
                }
            }
        }

        return $condition;
    }
}
