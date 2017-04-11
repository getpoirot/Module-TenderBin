<?php
namespace Module\TenderBin\Actions;

use Module\TenderBin\Exception\exResourceNotFound;
use Module\TenderBin\Interfaces\Model\iBindata;
use Module\TenderBin\Interfaces\Model\Repo\iRepoBindata;
use Poirot\Application\Sapi\Server\Http\ListenerDispatch;
use Poirot\Http\Header\FactoryHttpHeader;
use Poirot\Http\HttpMessage\Request\Plugin\ParseRequestData;
use Poirot\Http\HttpResponse;
use Poirot\Http\Interfaces\iHttpRequest;
use Poirot\OAuth2\Interfaces\Server\Repository\iEntityAccessToken;
use Poirot\Stream\Interfaces\iStreamable;
use Poirot\Stream\Psr\StreamBridgeFromPsr;
use Psr\Http\Message\UploadedFileInterface;


class RenderBinAction
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
     * Render Bin Content Into Browser
     *
     * @param string             $resource_hash
     * @param iEntityAccessToken $token
     *
     * @return array
     * @throws \Exception
     */
    function __invoke($resource_hash = null, $token = null)
    {
        $_get = ParseRequestData::_($this->request)->parseQueryParams();

        if (isset($_get['ver'])) {
            $version = $_get['ver'];
            $binData = $this->repoBins->findOneTagedSubverOf($resource_hash, $version);
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
            , \Module\TenderBin\buildOwnerObjectFromToken($token)
            , false // just if its not protected resource
        );


        return [
            ListenerDispatch::RESULT_DISPATCH => $this->makeHttpResponseFromBinData($binData)
        ];
    }

    // ..

    /**
     * // TODO Render as a Service Extensible
     * Render Bin Data To Response
     *
     * @param iBindata $binData
     *
     * @return HttpResponse
     */
    protected function makeHttpResponseFromBinData(iBindata $binData)
    {
        $response = new HttpResponse();

        $content  = $binData->getContent();
        if ($content instanceof UploadedFileInterface) {
            $content = $content->getStream();
            $content = new StreamBridgeFromPsr($content);
        } elseif (!$content instanceof iStreamable)
            $content = (string) $content;


        # Content Length
        if ($content instanceof iStreamable)
            $contentLength = $content->getSize();
        else
            $contentLength = strlen($content);

        $response->headers()->insert(FactoryHttpHeader::of(array(
            'Content-Length' => $contentLength
        )));


        # Content Type
        $response->headers()->insert(FactoryHttpHeader::of(array(
            'Content-Type' => $binData->getMimeType()
        )));

        $response->setBody($content);

        return $response;
    }
}
