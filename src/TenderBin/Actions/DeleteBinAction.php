<?php
namespace Module\TenderBin\Actions;

use Module\TenderBin\Exception\exResourceNotFound;
use Module\TenderBin\Interfaces\Model\Repo\iRepoBindata;
use Poirot\Application\Sapi\Server\Http\ListenerDispatch;
use Poirot\Http\Interfaces\iHttpRequest;
use Poirot\OAuth2\Interfaces\Server\Repository\iEntityAccessToken;


class DeleteBinAction
    extends aAction
{
    /** @var iRepoBindata */
    protected $repoBins;


    /**
     * ValidatePage constructor.
     *
     * @param iHttpRequest $request  @IoC /
     * @param iRepoBindata $repoBins @IoC /module/tenderbin/services/repository/Bindata
     */
    function __construct(iHttpRequest $request, iRepoBindata $repoBins)
    {
        parent::__construct($request);

        $this->repoBins = $repoBins;
    }


    /**
     * Delete Bin
     *
     * @param string             $resource_hash
     * @param iEntityAccessToken $token
     *
     * @return array
     * @throws \Exception
     */
    function __invoke($resource_hash = null, $token = null)
    {
        if (false === $binData = $this->repoBins->findOneByHash($resource_hash))
            throw new exResourceNotFound(sprintf(
                'Resource (%s) not found.'
                , $resource_hash
            ));


        # Assert Token
        $this->assertTokenByOwnerAndScope($token);

        // has user access to delete content?
        $this->assertAccessPermissionOnBindata(
            $binData
            , $this->buildOwnerObjectFromToken($token)
            , true // even if its not protected
        );


        # Delete From Persistence Bin
        $this->repoBins->deleteOneByHash($resource_hash);

        return [
            ListenerDispatch::RESULT_DISPATCH => [
                '_self' => [
                    'hash' => (string) $resource_hash,
                ],
                'status' => 'deleted',
            ],
        ];
    }
}
