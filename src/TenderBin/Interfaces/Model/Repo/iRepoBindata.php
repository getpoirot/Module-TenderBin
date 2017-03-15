<?php
namespace Module\TenderBin\Interfaces\Model\Repo;


use Module\TenderBin\Interfaces\Model\iEntityBindata;

interface iRepoBindata
{
    /**
     * Generate next unique identifier to persist
     * data with
     *
     * @param null|string $id
     * 
     * @return mixed
     */
    function genNextIdentifier($id = null);


    /**
     * Find By Search Term
     *
     * @param array  $term
     * @param string $offset
     * @param int    $limit
     * 
     * @return \Traversable
     */
    function find(array $term, $offset = null, $limit = null);
    
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
     * Save Entity By Insert Or Update
     * 
     * @param iEntityBindata $entity
     * 
     * @return iEntityBindata
     */
    function save(iEntityBindata $entity);
    
    /**
     * Find Match By Given Hash ID
     *
     * @param string|mixed $hash
     *
     * @return iEntityBindata|false
     */
    function findOneByHash($hash);

    /**
     * Find All Subversions Of a Bin Entity
     *
     * @param string|mixed $hash
     *
     * @return \MongoCursor
     */
    function findAllSubversionsOf($hash);

    /**
     * Find Subversion Of an Entity Bin IF Has?
     * 
     * @param $hash
     * @param $tag
     * 
     * @return iEntityBindata|false
     */
    function findOneTagedSubverOf($hash, $tag);
    
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
