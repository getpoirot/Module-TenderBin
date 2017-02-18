<?php
namespace Module\TenderBin\Model\Mongo;


use Module\MongoDriver\Model\Repository\aRepository;

use Module\TenderBin\Interfaces\Model\iEntityBindata;
use Module\TenderBin\Interfaces\Model\Repo\iRepoBindata;
use Module\TenderBin\Model\Bindata as BaseBindata;


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
        // TODO: Implement getNextIdentifier() method.
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
}
