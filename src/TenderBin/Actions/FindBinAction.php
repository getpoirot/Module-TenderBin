<?php
namespace Module\TenderBin\Actions;

use Module\TenderBin\Exception\exResourceNotFound;
use Module\TenderBin\Interfaces\Model\Repo\iRepoBindata;


class FindBinAction
    extends aAction
{
    /** @var iRepoBindata */
    protected $repoBins;


    /**
     * ValidatePage constructor.
     *
     * @param iRepoBindata $repoBins @IoC /module/tenderbin/services/repository/Bindata
     */
    function __construct(iRepoBindata $repoBins)
    {
        $this->repoBins = $repoBins;
    }


    /**
     * Create New Bin and Persist
     *
     * @param string $resource_hash
     * 
     * @return array
     * @throws \Exception
     */
    function __invoke($resource_hash = null)
    {
        if (false === $binData = $this->repoBins->findOneByHash($resource_hash))
            throw new exResourceNotFound(sprintf(
                'Resource (%s) not found.'
                , $resource_hash
            ));
        
        return ['binData' => $binData];
    }


    // Action Chain Helpers:
    
}
