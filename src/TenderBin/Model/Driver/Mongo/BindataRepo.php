<?php
namespace Module\TenderBin\Model\Driver\Mongo;

use Module\TenderBin\Model\Driver\Mongo;
use Module\TenderBin\Exception\exDuplicateEntry;
use Module\TenderBin\Interfaces\DownloadFileInterface;
use Module\MongoDriver\Model\Repository\aRepository;
use Module\TenderBin\Interfaces\Model\iEntityBindata;
use Module\TenderBin\Interfaces\Model\Repo\iRepoBindata;
use Module\TenderBin\Storage\DownloadFile;
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
     * Save Entity By Insert Or Update
     *
     * @param iEntityBindata $entity
     *
     * @return mixed
     */
    function save(iEntityBindata $entity)
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
     * @return iEntityBindata|false
     */
    function findOneByHash($hash)
    {
        /** @var iEntityBindata $r */
        $r = $this->_query()->findOne([
            '_id' => $this->genNextIdentifier($hash),
        ]);

        // Not Found Any Match!!!
        if (!$r) return false;
        
        # Check Whether BinData is Associated To File??
        if ($this->_storeIsFile($r))
            // Retrieve File From Storage
            $r = $this->_storeRetriveAndInjectFile($r);

        return $r;
    }

    /**
     * Find By Search Term
     *
     * - exclude content from retrieved bins
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
        if ($this->_storeIsFile($r))
            $this->_storeDeleteAssociatedFile($r);


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

        
        if (!$r) return false;

        # Check Whether BinData is Associated To File??
        if ($this->_storeIsFile($r))
            // Retrieve File From Storage
            $r = $this->_storeRetriveAndInjectFile($r);
        
        return $r;
    }

    function storage(Bindata $bindata)
    {
        // TODO all storage interface through this proxy call
        
        // now only return bin data with injected file stream
        return $this->_storeRetriveAndInjectFile($bindata);
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


    // Storage:

    protected function _storeSaveBinDataFile(Bindata $binData)
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

    protected function _storeDeleteAssociatedFile(Bindata $binData)
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
     * @return iEntityBindata
     * @throws \Exception
     */
    private function _storeRetriveAndInjectFile(Bindata $binData)
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

    private function _storeIsFile(Bindata $binData)
    {
        return ( $binData->getMeta()->has('is_file') );
    }
}
