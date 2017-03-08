<?php
namespace Module\TenderBin\Interfaces\Model\Repo;


use Module\TenderBin\Interfaces\Model\iEntityBindata;

interface iRepoBindata
{
    /**
     * Generate next unique identifier to persist
     * data with
     *
     * @return mixed
     */
    function getNextIdentifier();


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
    function insert(iEntityBindata $entity);

    /**
     * Find Match By Given Hash ID
     *
     * @param string|mixed $hash
     *
     * @return iEntityBindata|false
     */
    function findOneByHash($hash);

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
    function deleteOneByHash($hash);
}
