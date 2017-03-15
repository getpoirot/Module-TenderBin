<?php
namespace Module\TenderBin\Actions;

use Module\Foundation\Actions\IOC;
use Module\TenderBin\Interfaces\Model\iEntityBindata;
use Module\TenderBin\Interfaces\Model\Repo\iRepoBindata;
use Module\TenderBin\Model\Bindata;
use Module\TenderBin\Model\BindataVersionObject;
use Poirot\Application\Sapi\Server\Http\ListenerDispatch;
use Poirot\Http\HttpMessage\Request\Plugin\ParseRequestData;
use Poirot\Http\Interfaces\iHttpRequest;


class UpdateBinAction
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
     * Update BinData Stored
     * 
     * - if version exists delete current version and replace new one
     *
     * @param Bindata $binData
     * @param array   $updates Update Request On BinData
     *
     * @return array
     */
    function __invoke($binData = null, $updates = null)
    {
        $updateBin = clone $binData;
        
        if (isset($updates['version']))
        {
            // we must duplicate a new version of bindata with new tag
            $updateBin->setIdentifier(null); // let repo assign new identifier
            $updateBin->setDateCreated(null);

            $version = new BindataVersionObject;
            $version->setSubversionOf($binData->getIdentifier());
            $version->setTag($updates['version']);
            $updateBin->setVersion($version);
        }

        (!isset($updates['content']))              ?: $updateBin->setContent($updates['content']);

        (!isset($updates['title']))                ?: $updateBin->setTitle($updates['title']);
        (!isset($updates['timestamp_expiration'])) ?: $updateBin->setDatetimeExpiration($updates['timestamp_expiration']);
        (!isset($updates['protected']))            ?: $updateBin->setProtected($updates['protected']);
        if (isset($updates['meta'])) {
            $meta       = $binData->getMeta();
            $meta       = \Poirot\Std\cast($meta)->toArray();
            $meta       = array_merge($meta, $updates['meta']);
            $updateBin->setMeta($meta);
        }

        $r = $this->repoBins->save($updateBin);

        
        # Build Response

        if ($expiration = $r->getDatetimeExpiration()) {
            $currDateTime   = new \DateTime();
            $currDateTime   = $currDateTime->getTimestamp();
            $expireDateTime = $expiration->getTimestamp();

            $expiration     = $expireDateTime - $currDateTime;
        }

        $result = array(
            '$bindata' => [
                'hash'           => (string) $r->getIdentifier(),
                'title'          => $r->getTitle(),
                'content_type'   => $r->getMimeType(),
                'expiration'     => $expiration,
                'is_protected'   => $r->isProtected(),

                'meta'           => \Poirot\Std\cast($r->getMeta())->toArray(function($_, $k) {
                    return substr($k, 0, 2) == '__'; // filter specific options
                }),

                'version'      => [
                    'subversion_of' => ($v = $r->getVersion()->getSubversionOf()) ? [
                        '$bindata' => [
                            'uid' => ( $v ) ? (string) $v : null,
                        ],
                        '_link' => ( $v ) ? (string) IOC::url(
                            'main/tenderbin/resource/'
                            , array('resource_hash' => (string) $v)
                        ) : null,
                    ] : null,
                    'tag' => $r->getVersion()->getTag(),
                ],
            ],
            '_link'          => (string) IOC::url(
                'main/tenderbin/resource/'
                , array('resource_hash' => $r->getIdentifier())
            ),
        );

        return array(
            ListenerDispatch::RESULT_DISPATCH => $result,
        );
    }


    // Action Chain Helpers:

    /**
     * Parse Create Bin Data Parameters From Http Request
     *
     * @return callable
     */
    static function functorParseUpdateFromRequest()
    {
        /**
         * @param iHttpRequest $request
         *
         * @return array
         */
        return function ($request = null) {
            if (!$request instanceof iHttpRequest)
                throw new \RuntimeException('Cant attain Http Request Object.');


            # Parse and assert Http Request
            $updates = ParseRequestData::_($request)->parseBody();
            $updates = self::_assertInputData($updates);

            # Return to next chain as 'update' argument
            return ['updates' => $updates];
        };
    }

    protected static function _assertInputData(array $data)
    {
        # Validate Data

        // TODO assert content/type
        // TODO title can be null; it can been retrieved when render requested


        # Filter Data
        if (isset($data['content'])) {
            // Content Will Changed So Version Tag Must Defined.
            if (!isset($data['version']))
                throw new \InvalidArgumentException('Due Change Content You Must Provide Version Parameter.');
        }

        if ( isset($data['timestamp_expiration']) ) {
            if ($data['timestamp_expiration'] == '0') {
                // Consider infinite
                unset($data['timestamp_expiration']);
            } else {
                $dtStr = date("c", $data['timestamp_expiration']);
                $d = new \DateTime($dtStr);
                $data['timestamp_expiration'] = $d;
            }
        }

        if (isset($data['protected']))
            // Returns TRUE for "1", "true", "on" and "yes". Returns FALSE otherwise.
            $data['protected'] = filter_var($data['protected'], FILTER_VALIDATE_BOOLEAN);

        return $data;
    }
}
