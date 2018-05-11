<?php
namespace Module\TenderBin\Actions;

use Module\HttpFoundation\Events\Listener\ListenerDispatch;
use Module\TenderBin\Exception\exResourceNotFound;
use Module\TenderBin\Interfaces\Model\Repo\iRepoBindata;
use Poirot\Http\HttpMessage\Request\Plugin\ParseRequestData;
use Poirot\Http\Interfaces\iHttpRequest;
use Poirot\Http\Interfaces\iHttpResponse;
use Poirot\OAuth2Client\Interfaces\iAccessTokenEntity;


class RenderBinAction
    extends aAction
{
    /** @var iRepoBindata */
    protected $repoBins;
    /** @var iHttpResponse */
    protected $response;


    /**
     * ValidatePage constructor.
     *
     * @param iHttpRequest  $httpRequest @IoC /HttpRequest
     * @param iHttpResponse $response    @IoC /HttpResponse
     * @param iRepoBindata  $repoBins    @IoC /module/tenderbin/services/repository/Bindata
     */
    function __construct(iHttpRequest $httpRequest, iHttpResponse $response, iRepoBindata $repoBins)
    {
        parent::__construct($httpRequest);

        $this->repoBins = $repoBins;
        $this->response = $response;
    }


    /**
     * Render Bin Content Into Browser
     *
     * @param string       $resource_hash
     * @param iAccessTokenEntity $token
     *
     * @return array
     * @throws \Exception
     */
    function __invoke($resource_hash = null, $token = null)
    {
        $_get = ParseRequestData::_($this->request)->parseQueryParams();

            if (isset($_get['ver'])) {
                $version = $_get['ver'];
                $binData = $this->repoBins->findATaggedSubVerOf($resource_hash, $version);
            } else {
                $binData = $this->repoBins->findOneByHash($resource_hash);
            }

        if (false === $binData)
            throw new exResourceNotFound(sprintf(
                'Resource (%s) not found.'
                , $resource_hash
            ));


        // has user access to retrieve content
        $this->assertAccessPermissionOnBindata(
            $binData
            , $token
            , false // just if its not protected resource
        );


        return [
            // Return The Result For Renderer Strategy
            ListenerDispatch::RESULT_DISPATCH => $binData
        ];
    }
}
