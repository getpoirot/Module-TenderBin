<?php
namespace Module\OAuth2\Model\Mongo;


use Module\MongoDriver\Model\Repository\aRepository;

use Module\TenderBin\Interfaces\Model\iEntityBindata;
use Module\TenderBin\Interfaces\Model\Repo\iRepoBindata;
use Module\TenderBin\Model\Mongo\Bindata;


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
        // TODO: Implement insert() method.
    }
}
