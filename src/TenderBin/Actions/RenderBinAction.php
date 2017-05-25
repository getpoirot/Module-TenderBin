<?php
namespace Module\TenderBin\Actions;

use Module\HttpFoundation\Events\Listener\ListenerDispatch;
use Module\TenderBin\Exception\exResourceNotFound;
use Module\TenderBin\Interfaces\DownloadFileInterface;
use Module\TenderBin\Interfaces\Model\iBindata;
use Module\TenderBin\Interfaces\Model\Repo\iRepoBindata;
use Poirot\Http\Header\FactoryHttpHeader;
use Poirot\Http\HttpMessage\Request\Plugin\ParseRequestData;
use Poirot\Http\HttpResponse;
use Poirot\Http\Interfaces\iHeader;
use Poirot\Http\Interfaces\iHttpRequest;
use Poirot\Http\Interfaces\iHttpResponse;
use Poirot\OAuth2Client\Interfaces\iAccessToken;
use Poirot\Stream\Interfaces\iStreamable;
use Poirot\Stream\Psr\StreamBridgeFromPsr;
use Poirot\Stream\Streamable\SLimitSegment;
use Poirot\Stream\Streamable\STemporary;
use Psr\Http\Message\UploadedFileInterface;


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
     * @param iAccessToken $token
     *
     * @return array
     * @throws \Exception
     */
    function __invoke($resource_hash = null, $token = null)
    {
        $_get = ParseRequestData::_($this->request)->parseQueryParams();

        try {
            if (isset($_get['ver'])) {
                $version = $_get['ver'];
                $binData = $this->repoBins->findATaggedSubVerOf($resource_hash, $version);
            } else {
                $binData = $this->repoBins->findOneByHash($resource_hash);
            }
        } catch (\Exception $e) {
            throw new exResourceNotFound;
        }

        if (false === $binData)
            throw new exResourceNotFound(sprintf(
                'Resource (%s) not found.'
                , $resource_hash
            ));


        // has user access to retrieve content
        $this->assertAccessPermissionOnBindata(
            $binData
            , $this->buildOwnerObjectFromToken($token)
            , false // just if its not protected resource
        );


        return [
            ListenerDispatch::RESULT_DISPATCH
                => $this->makeHttpResponseFromBinData($this->request, $binData)
        ];
    }


    // ..

    /**
     * Render Bin Data To Response
     *
     * @param iHttpRequest $request
     * @param iBindata     $binData
     *
     * @return HttpResponse
     * @throws \Exception
     */
    protected function makeHttpResponseFromBinData($request, iBindata $binData)
    {
        $response = $this->response;

        $content  = $binData->getContent();
        if ($content instanceof UploadedFileInterface || $content instanceof DownloadFileInterface) {
            $content = $content->getStream();
            $content = new StreamBridgeFromPsr($content);
        } elseif (! $content instanceof iStreamable ) {
            $content = new STemporary((string) $content);
            $content->rewind();
        }


        $totalContentSize = $content->getSize();

        ## Check Range Request
        if ($request->headers()->has('Range')) {
            $rangeRequest = ''; // byte=0-500|500-|-500
            /** @var iHeader $h */
            foreach ($header = $request->headers()->get('Range') as $h)
                $rangeRequest .= $h->renderValueLine();

            parse_str($rangeRequest, $parsedRange);

            // HTTP/1.1 416 Range Not Satisfiable
            // Date: Fri, 20 Jan 2012 15:41:54 GMT
            // Content-Range: bytes */47022
            if (! isset($parsedRange['bytes']) )
                throw new \Exception('Range Not Satisfiable', 416);


            $range      = explode('-', $parsedRange['bytes']);

            if ($range[0] == '') {
                // -500 Read 500 byte from last
                $rangeStart = $totalContentSize - (int) $range[1];
                $content    = new SLimitSegment($content, $totalContentSize, $rangeStart);
            } elseif ($range[1] == '') {
                // 500- Read form 500 to the end
                $content    = new SLimitSegment($content, $totalContentSize, (int) $range[0]);
            } else {
                // 500-1000 Read form 500 to the end
                if ($range[1] > $totalContentSize)
                    $range[1] = $totalContentSize;

                $content    = new SLimitSegment($content, (int) $range[1] - (int) $range[0], (int) $range[0]);
            }


            // When the complete length is unknown:
            // Content-Range: bytes 42-1233/*
            $response->setStatusCode(206);
            $response->headers()->insert(FactoryHttpHeader::of(array(
                // Content-Range: bytes 0-1023/146515
                'Content-Range' => 'bytes '.$range[0].'-'.$range[1].'/'.$totalContentSize
            )));
        }


        ## Add Response Headers:

        // Support Accept Range; Resume Download ...
        $response->headers()->insert(FactoryHttpHeader::of(array(
            'Accept-Ranges' => 'bytes'
        )));


        // Content Length
        $response->headers()->insert(FactoryHttpHeader::of(array(
            'Content-Length' => $content->getSize()
        )));


        // Content Type
        $response->headers()->insert(FactoryHttpHeader::of(array(
            'Content-Type' => $binData->getMimeType()
        )));


        $response->setBody($content);
        return $response;
    }
}
