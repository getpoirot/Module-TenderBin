<?php
namespace Module\TenderBin\Model\Mongo;

use Module\MongoDriver\Services\aServiceRepository;


class BindataRepoService
    extends aServiceRepository
{
    const CONF_GENERATOR_KEY = 'unique_id_generator';
    
    /** @var string Service Name */
    protected $name = 'Bindata';


    /**
     * Return new instance of Repository
     *
     * @param \MongoDB\Database $mongoDb
     * @param string            $collection
     *
     * @return BindataRepo
     */
    function newRepoInstance($mongoDb, $collection)
    {
        $repo = new BindataRepo($mongoDb, $collection);
        if ($hasIdGenerator = $this->_getConf(self::CONF_GENERATOR_KEY))
            $repo->setIdentifierGenerator($hasIdGenerator);
        
        return $repo;
    }
}
