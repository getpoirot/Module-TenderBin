<?php
namespace Module\TenderBin\Actions;

use Module\TenderBin\Interfaces\Model\Repo\iRepoBindata;
use Poirot\Application\Sapi\Server\Http\ListenerDispatch;


class DeleteBinAction
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
     * Delete Bin
     *
     * @param string $resource_hash
     * 
     * @return array
     * @throws \Exception
     */
    function __invoke($resource_hash = null)
    {
        $this->repoBins->deleteOneByHash($resource_hash);

        return [
            ListenerDispatch::RESULT_DISPATCH => [
                '_self' => [
                    'hash' => $resource_hash,
                ],
                'status' => 'deleted',
            ],
        ];
    }


    // Action Chain Helpers:


}
