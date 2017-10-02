<?php
namespace Module\TenderBin\Interfaces\Model\Repo;

use Module\TenderBin\Interfaces\Model\iBindata;


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
    function attainNextIdentifier($id = null);


    /**
     * Find By Search Term
     *
     * @param array  $expression
     * @param string $offset
     * @param int    $limit
     * 
     * @return \Traversable
     */
    function findAll(array $expression, $offset = null, $limit = null);

    /**
     * Find Expired
     *
     * @return \Traversable
     */
    function findExpired();

    /**
     * Persist Bindata
     *
     * - if Bindata entity has no identifier used ::nextIdentifier
     *   to assign something new
     *
     * @param iBindata $entity
     *
     * @return iBindata Contains inserted uid
     */
    function insert(iBindata $entity);

    /**
     * Save Entity By Insert Or Update
     * 
     * @param iBindata $entity
     * 
     * @return iBindata
     */
    function save(iBindata $entity);
    
    /**
     * Find Match By Given Hash ID
     *
     * @param string|mixed $hash
     *
     * @return iBindata|false
     */
    function findOneByHash($hash);

    /**
     * Find All Subversions Of a Bin Entity
     *
     * @param string|mixed $hash
     *
     * @return \MongoCursor
     */
    function findSubVersionsOf($hash);

    /**
     * Find Subversion Of an Entity Bin IF Has?
     * 
     * @param $hash
     * @param $tag
     * 
     * @return iBindata|false
     */
    function findATaggedSubVerOf($hash, $tag);
    
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
