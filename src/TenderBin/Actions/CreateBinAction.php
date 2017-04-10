<?php
namespace Module\TenderBin\Actions;

use Module\TenderBin\Model\Entity;
use Module\Foundation\Actions\IOC;
use Module\TenderBin\Interfaces\Model\Repo\iRepoBindata;
use Poirot\Application\Sapi\Server\Http\ListenerDispatch;
use Poirot\Http\Interfaces\iHttpRequest;
use Poirot\OAuth2\Interfaces\Server\Repository\iEntityAccessToken;
use Psr\Http\Message\UploadedFileInterface;


class CreateBinAction
    extends aAction
{
    /** @var iRepoBindata */
    protected $repoBins;


    /**
     * ValidatePage constructor.
     *
     * @param iHttpRequest $request  @IoC /
     * @param iRepoBindata $repoBins @IoC /module/tenderbin/services/repository/Bindata
     */
    function __construct(iHttpRequest $request, iRepoBindata $repoBins)
    {
        parent::__construct($request);

        $this->repoBins = $repoBins;
    }


    /**
     * Create New Bin and Persist
     *
     * @param string                  $custom_uid
     * @param iEntityAccessToken|null $token
     *
     * @return array
     * @throws \Exception
     */
    function __invoke($custom_uid = null, iEntityAccessToken $token = null)
    {
        # Assert Token
        $this->assertTokenByOwnerAndScope($token);


        # Create Post Entity From Http Request
        $hydrateBindata = new Entity\HydrateBindata(
            Entity\HydrateBindata::parseWith($this->request) );

        try
        {
            $entityBindata  = new Entity\Bindata($hydrateBindata);
            // Determine Owner Identifier From Token
            $entityBindata->setOwnerIdentifier( \Module\TenderBin\buildOwnerObjectFromToken($token) );
            // Set Custom Object Identifier if it given.
            $entityBindata->setIdentifier($custom_uid);

            // TODO Assert Validate Entity

        } catch (\InvalidArgumentException $e)
        {
            // TODO Handle Validation ...
            throw $e;
        }


        # Persist Data
        $r = $this->repoBins->insert($entityBindata);


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

    protected static function _assertInputData(array $data)
    {
        # Validate Data
        // TODO meta data start with >__< are reserved and cant be applied 
        
        if (!isset($data['content']))
            throw new \InvalidArgumentException('Parameter "content" is required.');

        if ($data['content'] instanceof UploadedFileInterface) {
            // Content-Type can be retrieved from uploaded file
            // File Upload With No Error
            /** @var UploadedFileInterface $file */
            $file = $data['content'];
            if ($file->getError())
                throw new \RuntimeException('Error Uploading File; The File Not Received.');
        } else {
            if (!isset($data['content_type']))
                throw new \InvalidArgumentException('Parameter "content_type" is required.');
        }

        // TODO assert content/type
        // TODO title can be null; it can been retrieved when render requested


        return $data;
    }
}
