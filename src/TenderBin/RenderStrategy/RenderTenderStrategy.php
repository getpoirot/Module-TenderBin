<?php
namespace Module\TenderBin\RenderStrategy;

use HttpResponse;
use Module\HttpRenderer\RenderStrategy\aRenderStrategy;
use Module\TenderBin\Interfaces\DownloadFileInterface;
use Module\TenderBin\Interfaces\Model\iBindata;
use Module\TenderBin\Model\Entity\BindataEntity;
use Poirot\Application\Sapi\Event\EventHeapOfSapi;

use Poirot\Events\Interfaces\iEvent;
use Poirot\Http\Header\FactoryHttpHeader;
use Poirot\Http\Interfaces\iHeader;
use Poirot\Http\Interfaces\iHttpRequest;
use Poirot\Http\Interfaces\iHttpResponse;
use Poirot\Stream\Interfaces\iStreamable;
use Poirot\Stream\Psr\StreamBridgeFromPsr;
use Poirot\Stream\Streamable\SLimitSegment;
use Poirot\Stream\Streamable\STemporary;
use Psr\Http\Message\UploadedFileInterface;


class RenderTenderStrategy
    extends aRenderStrategy
{
    const RENDER_PRIORITY = 1000;

    protected $request;
    protected $response;


    /**
     * aAction constructor.
     * @param iHttpRequest  $httpRequest  @IoC /HttpRequest
     * @param iHttpResponse $httpResponse @IoC /HttpResponse
     */
    function __construct(iHttpRequest $httpRequest, iHttpResponse $httpResponse)
    {
        $this->request  = $httpRequest;
        $this->response = $httpResponse;
    }


    /**
     * Initialize To Events
     *
     * - usually bind listener(s) to events
     *
     * @param EventHeapOfSapi|iEvent $events
     *
     * @return $this
     */
    function attachToEvent(iEvent $events)
    {
        $self = $this;
        $events
            ## create view model from string result
            ->on(
                EventHeapOfSapi::EVENT_APP_RENDER
                , function ($result = null) use ($self) {
                    return $self->createResponseFromResult($result);
                }
                , self::RENDER_PRIORITY
            )
        ;

        return $this;
    }

    /**
     * Get Content Type That Renderer Will Provide
     * exp. application/json; text/html
     *
     * @return string
     */
    function getContentType()
    {
        return 'text/html; charset=UTF-8';
    }


    // ..

    /**
     * Create ViewModel From Actions Result
     *
     * @param BindataEntity $result Result from dispatch action
     *
     * @return array|void
     */
    protected function createResponseFromResult($result = null)
    {
        if (! $result instanceof BindataEntity)
            // Do Nothing;
            return;

        return ['result' => $this->makeHttpResponseFromBinData($this->request, $result)];
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
    private function makeHttpResponseFromBinData($request, iBindata $binData)
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
            if (! $range[1])
                $range[1] = $totalContentSize-1;
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
