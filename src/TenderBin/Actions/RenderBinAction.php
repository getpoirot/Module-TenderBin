<?php
namespace Module\TenderBin\Actions;

use Module\TenderBin\Exception\exResourceNotFound;
use Module\TenderBin\Interfaces\Model\iEntityBindata;
use Module\TenderBin\Interfaces\Model\Repo\iRepoBindata;
use Poirot\Application\Sapi\Server\Http\ListenerDispatch;
use Poirot\Http\Header\FactoryHttpHeader;
use Poirot\Http\HttpMessage\Request\Plugin\ParseRequestData;
use Poirot\Http\HttpResponse;
use Poirot\Http\Interfaces\iHttpRequest;
use Poirot\Stream\Interfaces\iStreamable;
use Poirot\Stream\Psr\StreamBridgeFromPsr;
use Psr\Http\Message\UploadedFileInterface;


class RenderBinAction
    extends FindBinAction
{
    /** @var iHttpRequest */
    protected $request;


    /**
     * ValidatePage constructor.
     *
     * @param iRepoBindata $repoBins    @IoC /module/tenderbin/services/repository/Bindata
     * @param iHttpRequest $httpRequest @IoC /request
     */
    function __construct(iRepoBindata $repoBins, iHttpRequest $httpRequest)
    {
        parent::__construct($repoBins);
        $this->request = $httpRequest;
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
        
        return ['binData' => $binData];
    }


    // Action Chain Helpers:

    /**
     * Response BinData Info GET Method
     *
     * @return \Closure
     */
    static function functorResponseRenderContent()
    {
        /**
         * @param iEntityBindata $binData
         * @return array
         */
        return function ($binData = null)
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

            return [
                ListenerDispatch::RESULT_DISPATCH => $response,
            ];
        };
    }
}
