<?php
namespace Module\TenderBin\Actions;

use Module\Foundation\Actions\IOC;
use Module\TenderBin\Exception\exResourceNotFound;
use Module\TenderBin\Interfaces\Model\iEntityBindata;
use Module\TenderBin\Interfaces\Model\Repo\iRepoBindata;
use Poirot\Application\Sapi\Server\Http\ListenerDispatch;
use Poirot\Http\Header\FactoryHttpHeader;
use Poirot\Http\HttpResponse;
use Poirot\Http\Interfaces\iHeaders;


class FindBinAction
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
     * @param string $resource_hash
     * 
     * @return array
     * @throws \Exception
     */
    function __invoke($resource_hash = null)
    {
        if (false === $binData = $this->repoBins->findOneByHash($resource_hash))
            throw new exResourceNotFound(sprintf(
                'Resource (%s) not found.'
                , $resource_hash
            ));


        # Retrieve Available Versions
        $subVers  = $this->repoBins->findAllSubversionsOf($resource_hash);
        $versions = array();
        /** @var iEntityBindata $sv */
        foreach ($subVers as $sv) {
            $versions[$sv->getVersion()->getTag()] = [
                '$bindata' => [
                    'uid' => $v = (string) $sv->getIdentifier(),
                ],
                '_link' => ( $v ) ? (string) IOC::url(
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
         * @param iEntityBindata $binData
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

            return [
                ListenerDispatch::RESULT_DISPATCH => [
                    '_self'      => [
                        'hash' => (string) $binData->getIdentifier(),
                    ],
                    'title'        => $binData->getTitle(),
                    'mime_type'    => $binData->getMimeType(),
                    'expiration'   => $expiration,
                    'is_protected' => (boolean) $binData->isProtected(),
                    'meta'         => \Poirot\Std\cast($binData->getMeta())->toArray(function($_, $k) {
                        return substr($k, 0, 2) === '__'; // filter system specific meta data
                    }),
                    'versions'     => $versions,

                    '_link' => (string) IOC::url(
                        'main/tenderbin/resource/'
                        , array('resource_hash' => (string) $binData->getIdentifier())
                    ),
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
         * @param iEntityBindata $binData
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
