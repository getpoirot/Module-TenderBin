<?php
namespace Module\TenderBin\Model\Mongo;


use Module\MongoDriver\Model\Repository\aRepository;

use Module\TenderBin\Interfaces\Model\iEntityBindata;
use Module\TenderBin\Interfaces\Model\Repo\iRepoBindata;
use MongoDB\BSON\ObjectID;
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
        $this->setModelPersist(new Bindata);
    }


    /**
     * Generate next unique identifier to persist
     * data with
     *
     * @return mixed
     */
    function getNextIdentifier()
    {
        if ($this->_functorIDGenerator) {
            return (string) call_user_func($this->_functorIDGenerator, $this);
        }
        
        return (string) new ObjectID();
    }

    /**
     * Set ID Generator Callable
     * 
     * @param callable $callable function($self): string // generated id
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
        $givenIdentifier = $entity->getIdentifier();
        if (!$givenIdentifier)
            $givenIdentifier = $this->getNextIdentifier();

        $dateCreated = $entity->getDateCreated();
        if (!$dateCreated)
            $dateCreated = new \DateTime();

        # Convert given entity to Persistence Entity Object To Insert
        $binData = new Bindata;
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

        if ($entity->getContent() instanceof UploadedFileInterface) {
            // Handle file storage
            $binData = $this->_storeBinData($binData);
        }

        $r = $this->_query()->insertOne($binData);


        # Give back entity with persistence identifier
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
            '_id' => $hash,
        ]);

        return $r ? $r : false;
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
}
