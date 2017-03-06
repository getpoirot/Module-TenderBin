<?php
namespace Module\TenderBin\Model\Mongo;


use Module\MongoDriver\Model\Repository\aRepository;

use Module\TenderBin\Interfaces\Model\iEntityBindata;
use Module\TenderBin\Interfaces\Model\Repo\iRepoBindata;


class BindataRepo
    extends aRepository
    implements iRepoBindata
{
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
        // note: currently generated hash allows 14,776,336 unique entry

        do {
            $id = \Poirot\Std\generateShuffleCode(4, \Poirot\Std\CODE_NUMBERS | \Poirot\Std\CODE_STRINGS);
        } while ($this->findOneByHash($id));

        return $id;
    }

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

        $r = $this->_query()->insertOne($binData);


        # Give back entity with persistence identifier
        $return = clone $entity;
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
}
