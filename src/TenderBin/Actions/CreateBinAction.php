<?php
namespace Module\TenderBin\Actions;

use Module\Foundation\Actions\IOC;
use Module\TenderBin\Exception\exDuplicateEntry;
use Module\TenderBin\Interfaces\Model\iEntityBindata;
use Module\TenderBin\Interfaces\Model\Repo\iRepoBindata;
use Module\TenderBin\Model\Bindata;
use Module\TenderBin\Model\BindataOwnerObject;
use Poirot\Application\Sapi\Server\Http\ListenerDispatch;
use Poirot\Http\HttpMessage\Request\Plugin\ParseRequestData;
use Poirot\Http\Interfaces\iHttpRequest;
use Psr\Http\Message\UploadedFileInterface;


class CreateBinAction
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
     * @param iEntityBindata $binData
     *
     * @return array
     * @throws \Exception
     */
    function __invoke($binData = null)
    {
        # Check Whether Bin With Custom Hash Exists?

        if (null !== $customHash = $binData->getIdentifier()) {
            if (false !== $this->repoBins->findOneByHash($customHash))
                throw new exDuplicateEntry(sprintf(
                    'Bindata with Custom Hash (%s) exists.'
                    , $customHash
                ), 400);
        }


        # Persist Data

        $r = $this->repoBins->insert($binData);


        # Build Response

        if ($expiration = $r->getDatetimeExpiration()) {
            $currDateTime   = new \DateTime();
            $currDateTime   = $currDateTime->getTimestamp();
            $expireDateTime = $expiration->getTimestamp();

            $expiration     = $expireDateTime - $currDateTime;
        }

        $result = array(
            'hash'           => $r->getIdentifier(),
            'title'          => $r->getTitle(),
            'content_type'   => $r->getMimeType(),
            'meta'           => \Poirot\Std\cast($r->getMeta())->toArray(function($_, $k) {
                return substr($k, 0, 2) == '__'; // filter specific options
            }),
            'expiration'     => $expiration,
            'is_protected'   => $r->isProtected(),
            '_link'            => (string) IOC::url(
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
    static function functorMakeBindataEntityFromRequest()
    {
        /**
         * @param iHttpRequest       $request
         * @param BindataOwnerObject $ownerObject
         * @param string             $custom_uid
         *
         * @return array [iEntityBindata 'binData']
         */
        return function ($request = null, $ownerObject = null, $custom_uid = null) {
            if (!$request instanceof iHttpRequest)
                throw new \RuntimeException('Cant attain Http Request Object.');


            # Parse and assert Http Request
            $_post = ParseRequestData::_($request)->parseBody();
            $_post = self::_assertInputData($_post);


            # Create BinData Entity From Parsed Request Params
            $binData = new Bindata;
            $binData->setTitle($_post['title']);
            $binData->setContent($_post['content']);
            $binData->setOwnerIdentifier($ownerObject);

            (!isset($_post['content_type']))         ?: $binData->setMimeType($_post['content_type']);
            (!isset($_post['meta']))                 ?: $binData->setMeta($_post['meta']);
            (!isset($_post['protected']))            ?: $binData->setProtected($_post['protected']);
            (!isset($_post['timestamp_expiration'])) ?: $binData->setDatetimeExpiration($_post['timestamp_expiration']);

            if ($custom_uid !== null)
                // Set Custom Object Identifier if it given.
                $binData->setIdentifier($custom_uid);


            # Return to next chain as 'binData' argument
            return ['binData' => $binData];
        };
    }


    protected static function _assertInputData(array $data)
    {
        # Validate Data
        // TODO meta data start with >__< are reserved and cant be applied 
        
        if (!isset($data['content']))
            throw new \InvalidArgumentException('Parameter "content" is required.');

        if (!$data['content'] instanceof UploadedFileInterface) {
            // Content-Type can be retrieved from uploaded file
            if (!isset($data['content_type']))
                throw new \InvalidArgumentException('Parameter "content_type" is required.');
        } else {
            // File Upload With No Error
            /** @var UploadedFileInterface $file */
            $file = $data['content'];
            if ($file->getError())
                throw new \RuntimeException('Error Uploading File; The File Not Received.');
        }

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
