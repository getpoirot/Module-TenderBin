<?php
namespace Module\TenderBin\Actions;

use Module\HttpFoundation\Events\Listener\ListenerDispatch;
use Module\TenderBin\Model\Driver\Mongo\BindataEntity;
use Module\TenderBin\Model\Entity;
use Module\TenderBin\Exception\exResourceNotFound;
use Module\TenderBin\Interfaces\Model\Repo\iRepoBindata;
use Poirot\Http\HttpMessage\Request\Plugin\ParseRequestData;
use Poirot\Http\Interfaces\iHttpRequest;
use Poirot\OAuth2Client\Interfaces\iAccessTokenEntity;
use Poirot\Std\Exceptions\exUnexpectedValue;


class TouchBinAction
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
     * Update BinData Stored
     *
     * - if version exists delete current version and replace new one
     *
     * @param string       $resource_hash
     * @param iAccessTokenEntity $token
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


        ## Assert Token
        #
        $this->assertTokenByOwnerAndScope($token);

        // has user access to edit content?
        $this->assertAccessPermissionOnBindata(
            $binData
            , $token
            , true // even if its not protected
        );


        # Create Updated Bin

        try
        {
            $updatedBin = new Entity\BindataEntity($binData);
            $updatedBin->setDatetimeExpiration(false);


        } catch (exUnexpectedValue $e)
        {
            // TODO Handle Validation ...
            throw new exUnexpectedValue('Validation Failed', null,  400, $e);
        }

        ## Touch All SubVersions
        #
        $subVersions = $this->repoBins->findSubVersionsOf($updatedBin->getIdentifier());
        /** @var BindataEntity $bin */
        foreach ($subVersions as $bin) {
            $bin->setDatetimeExpiration(false);
            $this->repoBins->save($bin);
        }

        $r = $this->repoBins->save($updatedBin);


        # Build Response
        $linkParams = [
            'resource_hash' => $r->getIdentifier(), ];

        if ( $r->getMeta()->has('is_file') )
            $linkParams += [
                'filename' => $r->getMeta()->get('filename'), ];


        $result = \Module\TenderBin\toResponseArrayFromBinEntity($r)
            + [
                '_link'          => (string) \Module\HttpFoundation\Actions::url(
                    'main/tenderbin/resource/'
                    , $linkParams
                ),
            ];

        return [
            ListenerDispatch::RESULT_DISPATCH => $result,
        ];
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
