<?php
namespace Module\TenderBin\Actions;

use Module\HttpFoundation\Events\Listener\ListenerDispatch;
use Module\TenderBin\Exception\exResourceNotFound;
use Module\TenderBin\Interfaces\Model\iBindata;
use Module\TenderBin\Interfaces\Model\Repo\iRepoBindata;
use Poirot\Http\Header\FactoryHttpHeader;
use Poirot\Http\HttpResponse;
use Poirot\Http\Interfaces\iHeaders;
use Poirot\Http\Interfaces\iHttpRequest;
use Poirot\OAuth2Client\Interfaces\iAccessToken;


class GetMetaBinAction
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
     * Retrieve Bin Meta Info
     *
     * @param string       $resource_hash
     * @param iAccessToken $token
     *
     * @return array
     * @throws \Exception
     */
    function __invoke($resource_hash = null, $token = null)
    {
        try {
            if (false === $binData = $this->repoBins->findOneByHash($resource_hash))
                throw new exResourceNotFound(sprintf(
                    'Resource (%s) not found.'
                    , $resource_hash
                ));
        } catch (\Exception $e) {
            throw new exResourceNotFound;
        }



        // has user access to edit content?
        $this->assertAccessPermissionOnBindata(
            $binData
            , $this->buildOwnerObjectFromToken($token)
            , false // just if its not protected resource
        );


        # Retrieve Available Versions
        $subVers  = $this->repoBins->findSubVersionsOf($resource_hash);
        $versions = array();
        /** @var iBindata $sv */
        foreach ($subVers as $sv) {
            $versions[$sv->getVersion()->getTag()] = [
                'bindata' => [
                    'uid' => $v = (string) $sv->getIdentifier(),
                ],
                '_link' => ( $v ) ? (string) \Module\HttpFoundation\Module::url(
                    'main/tenderbin/resource/'
                    , array('resource_hash' => (string) $v)
                ) : null,
            ];
        }
        
        return ['binData' => $binData, 'versions' => $versions];
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
            return [
                ListenerDispatch::RESULT_DISPATCH => \Module\TenderBin\toResponseArrayFromBinEntity($binData)
                    + ['versions' => $versions]
                    + [
                        '_link' => (string) \Module\HttpFoundation\Module::url(
                            'main/tenderbin/resource/'
                            , array('resource_hash' => (string) $binData->getIdentifier())
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
