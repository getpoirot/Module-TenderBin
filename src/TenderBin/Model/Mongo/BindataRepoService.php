<?php
namespace Module\TenderBin\Model\Mongo;

use Module\MongoDriver\Services\aServiceRepository;


class BindataRepoService
    extends aServiceRepository
{
    /** @var string Service Name */
    protected $name = 'Bindata';

    
    /**
     * Repository Class Name
     *
     * @return string
     */
    function getRepoClassName()
    {
        return BindataRepo::class;
    }
}
