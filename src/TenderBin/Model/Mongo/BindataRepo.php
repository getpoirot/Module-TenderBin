<?php
namespace Module\TenderBin\Model\Mongo;

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
     * - if Bindata entity has no identifier used ::nextIdentifier
     *   to assign something new
     *
     * @param iEntityBindata $entity
     *
     * @return iEntityBindata Contains inserted uid
     */
    function insert(iEntityBindata $entity)
    {
        $givenIdentifier = $this->genNextIdentifier(
            $entity->getIdentifier()
        );

        $dateCreated = $entity->getDateCreated();
        if (!$dateCreated)
            $dateCreated = new \DateTime();

        # Convert given entity to Persistence Entity Object To Insert
        $binData = new Mongo\Bindata;
        $binData
            ->setIdentifier($givenIdentifier)
            ->setTitle($entity->getTitle())
            ->setMeta($entity->getMeta())
            ->setContent($entity->getContent())
            ->setMimeType($entity->getMimeType())
            ->setOwnerIdentifier($entity->getOwnerIdentifier())
            ->setDatetimeExpiration($entity->getDatetimeExpiration())
            ->setDateCreated($dateCreated)
            ->setProtected($entity->isProtected())
        ;

        
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
        // TODO Delete Tagged Versions


        return true;
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
            $buff = $file->getStream()->read(2048);
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
}
