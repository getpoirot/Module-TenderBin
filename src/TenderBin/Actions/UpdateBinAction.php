<?php
namespace Module\TenderBin\Actions;

use Module\Foundation\Actions\IOC;
use Module\TenderBin\Exception\exDuplicateEntry;
use Module\TenderBin\Exception\exResourceNotFound;
use Module\TenderBin\Interfaces\Model\iEntityBindata;
use Module\TenderBin\Interfaces\Model\Repo\iRepoBindata;
use Module\TenderBin\Model\Bindata;
use Module\TenderBin\Model\BindataOwnerObject;
use Poirot\Application\Exception\exAccessDenied;
use Poirot\Application\Exception\exRouteNotMatch;
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
     * Update Bin By Owner
     *
     * @param string             $resource_hash
     * @param BindataOwnerObject $ownerIdentifier Current resource owner identifier from token assertion
     *
     * @return array
     */
    function __invoke($resource_hash = null, $ownerIdentifier = null, $updates = null)
    {
        if (false === $binData = $this->repoBins->findOneByHash($resource_hash))
            throw new exResourceNotFound(sprintf(
                'Resource (%s) not found.'
                , $resource_hash
            ));


        # Check Owner Privilege On Modify Bindata
        $binOwner = $binData->getOwnerIdentifier();
        foreach ($binOwner as $k => $v) {
            if ($ownerIdentifier->{$k} !== $v)
                // Mismatch Owner!!
                throw new exAccessDenied('Owner Mismatch; You have not access to edit this data.');
        }


        // To Implement changes
        print_r($updates);kd('todo');
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
         * @param iHttpRequest       $request
         *
         * @return array
         */
        return function ($request = null) {
            if (!$request instanceof iHttpRequest)
                throw new \RuntimeException('Cant attain Http Request Object.');


            # Parse and assert Http Request
            $_post = ParseRequestData::_($request)->parseBody();
            $_post = self::_assertInputData($_post);

            # Return to next chain as 'update' argument
            return ['updates' => $_post];
        };
    }

    protected static function _assertInputData(array $data)
    {
        # Validate Data

        // TODO assert content/type
        // TODO title can be null; it can been retrieved when render requested


        # Filter Data

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
