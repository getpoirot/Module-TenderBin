<?php
namespace Module\TenderBin\Actions;

use Module\TenderBin\Model\Entity\BindataEntity;
use Poirot\Http\HttpMessage\Request\Plugin\ParseRequestData;
use Poirot\Http\HttpResponse;
use Module\HttpFoundation\Events\Listener\ListenerDispatch;
use Module\TenderBin\Interfaces\Model\iBindata;
use Module\TenderBin\Interfaces\Model\Repo\iRepoBindata;
use Poirot\OAuth2Client\Interfaces\iAccessTokenEntity;
use Poirot\Http\Header\FactoryHttpHeader;
use Poirot\Http\Interfaces\iHeaders;
use Poirot\Http\Interfaces\iHttpRequest;
use Poirot\Std\Exceptions\exUnexpectedValue;


class ListMetaBinAction
    extends aAction
{
    /** @var iRepoBindata */
    protected $repoBins;


    /**
     * ValidatePage constructor.
     *
     * @param iHttpRequest $httpRequest @IoC /HttpRequest
     * @param iRepoBindata $repoBins    @IoC /module/tenderbin/services/repository/Bindata
     */
    function __construct(iHttpRequest $httpRequest, iRepoBindata $repoBins)
    {
        parent::__construct($httpRequest);

        $this->repoBins = $repoBins;
    }


    /**
     * List Retrieve Bin Meta Info
     *
     * @param iAccessTokenEntity $token
     *
     * @return array
     * @throws \Exception
     */
    function __invoke($token = null)
    {
        ## Parse Given Hashes From Request
        #
        $data = ParseRequestData::_($this->request)->parseBody();
        if (! isset($data['hashes']))
            throw new exUnexpectedValue('Required Parameter "hashes" not exists.');


        ## Retrieve Resources From Given Hashes
        #
        $hashes = $data['hashes'];
        $bins   = $this->repoBins->findMatchWithHashes($hashes);

        $metaResponse = [];
        /** @var BindataEntity $bin */
        foreach ($bins as $bin) {
            // has user access to edit content?
            $this->assertAccessPermissionOnBindata(
                $bin
                , $token
                , false // just if its not protected resource
            );


            $bin = \Module\TenderBin\toResponseArrayFromBinEntity($bin);
            $metaResponse[$bin['bindata']['hash']] = $bin['bindata']['meta'];
        }

        return [
            ListenerDispatch::RESULT_DISPATCH => $metaResponse,
        ];
    }


    // Action Chain Helpers:

    /**
     * Response BinData Info GET Method
     *
     * @return \Closure
     */
    static function functorResponseGetInfoResult()
    {
        /**
         * @param iBindata $binData
         * @return array
         */
        return function ($binData = null, $versions = null)
        {
            # Build Response
            $linkParams = [
                'resource_hash' => $binData->getIdentifier(), ];

            if ( $binData->getMeta()->has('is_file') )
                $linkParams += [
                    'filename' => $binData->getMeta()->get('filename'), ];

            return [
                ListenerDispatch::RESULT_DISPATCH => \Module\TenderBin\toResponseArrayFromBinEntity($binData)
                    + ['versions' => $versions]
                    + [
                        '_link' => (string) \Module\HttpFoundation\Actions::url(
                            'main/tenderbin/resource/'
                            , $linkParams
                        ),
                        '_self'      => [
                            'hash' => (string) $binData->getIdentifier(),
                        ],
                    ],
            ];
        };
    }

    /**
     * Response BinData Info HEAD Method
     *
     * @return \Closure
     */
    static function functorResponseHeadInfoResult()
    {
        /**
         * @param iBindata $binData
         * @return array
         */
        return function ($binData = null, $versions = null)
        {
            if ($expiration = $binData->getDatetimeExpiration()) {
                $currDateTime   = new \DateTime();
                $currDateTime   = $currDateTime->getTimestamp();
                $expireDateTime = $expiration->getTimestamp();

                $expiration     = $expireDateTime - $currDateTime;
            }


            $meta = \Poirot\Std\cast($binData->getMeta())->toArray(function($_, $k) {
                return substr($k, 0, 2) === '__'; // filter system specific meta data
            });

            $r = [
                'title'        => $binData->getTitle(),
                'mime_type' => $binData->getMimeType(),
                'expiration'   => $expiration,
                'is_protected' => (boolean) $binData->isProtected(),
                'meta'         => $meta,
                'versions'     => $versions,
            ];

            $_f_AddHeaders = function(iHeaders $headers, $r, $prefix = 'X-') use (&$_f_AddHeaders)
            {
                foreach ($r as $k => $v) {
                    $name = strtr($k, '_', ' ');
                    $name = strtr(ucwords(strtolower($name)), ' ', '-');

                    if (is_array($v)) {
                        $_f_AddHeaders($headers, $v, $prefix.$name.'-');
                        continue;
                    }


                    $valuable = [$prefix.$name => $v];
                    $header   = FactoryHttpHeader::of($valuable);
                    $headers->insert($header);
                }
            };

            $response = new HttpResponse;
            $_f_AddHeaders($response->headers(), $r);

            return [
                ListenerDispatch::RESULT_DISPATCH => $response,
            ];
        };
    }
}
