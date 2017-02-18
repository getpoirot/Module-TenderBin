<?php
namespace Module\TenderBin\Actions;

use Module\TenderBin\Interfaces\Model\Repo\iRepoBindata;


class CreateBinAction
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
     * @param string $custom_hash
     */
    function __invoke($custom_hash = null)
    {
        // TODO: Implement __invoke() method.
    }
}
