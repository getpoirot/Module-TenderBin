<?php
namespace Module\TenderBin\Actions;

use Module\TenderBin\Interfaces\Model\iBindata;
use Module\TenderBin\Model\Entity\Bindata\OwnerObject;
use Poirot\Application\Exception\exAccessDenied;
use Poirot\Http\Interfaces\iHttpRequest;
use Poirot\OAuth2\Interfaces\Server\Repository\iEntityAccessToken;


/**
 * @method \Traversable XX($owner_identifier, $skip = null, $limit = null)
 */
abstract class aAction
    extends \Module\Foundation\Actions\aAction
{
    /** @var iHttpRequest */
    protected $request;

    protected $tokenMustHaveOwner  = false;
    protected $tokenMustHaveScopes = array(

    );


    /**
     * aAction constructor.
     * @param iHttpRequest $request @IoC /
     */
    function __construct(iHttpRequest $request)
    {
        $this->request = $request;
    }


    // ...

    /**
     * Assert Token
     *
     * @param iEntityAccessToken $token
     *
     * @throws exAccessDenied
     */
    protected function assertTokenByOwnerAndScope($token)
    {
        # Validate Access Token
        \Module\OAuth2Client\validateGivenToken(
            $token
            , (object) ['mustHaveOwner' => $this->tokenMustHaveOwner, 'scopes' => $this->tokenMustHaveScopes ]
        );

    }

    /**
     * Assert BinData Access By Check Permission
     * note: determine current user from token
     *
     * @param iBindata $binData
     * @param OwnerObject    $ownerObject
     * @param boolean        $forceCheckPermission Force Check Permission On None Protected Resource
     *                                             when we want update the resource not protected but
     *                                             access must be checked.
     *
     * @throws exAccessDenied
     */
    protected function assertAccessPermissionOnBindata(
        iBindata $binData = null
        , $ownerObject
        , $forceCheckPermission = false
    ) {
        if (false === $forceCheckPermission)
            // Has Force To Check Owner Permission?
            if (!$binData->isProtected())
                // Bin Data is not protected; let it play ...
                return;


        # Check Owner Privilege On Modify Bindata
        if ($ownerObject instanceof OwnerObject) {
            /** @var OwnerObject $binOwnerObject */
            $binOwnerObject = $binData->getOwnerIdentifier();
            if (! (
                   $ownerObject->getRealm() == $binOwnerObject->getRealm()
                && $ownerObject->getUid()   == $binOwnerObject->getUid()
            ) )
                // Mismatch Owner!!
                throw new exAccessDenied('Owner Mismatch; You have not access to edit this data.');
        }

    }

    /**
     * Build OwnerObject From Token
     *
     * @param iEntityAccessToken $token
     *
     * @return OwnerObject
     */
    protected function buildOwnerObjectFromToken($token = null)
    {
        if (!$token instanceof iEntityAccessToken)
            return null;


        $clientIdentifier = $token->getClientIdentifier();

        // Check Client Identifier Aliases:
        $conf  = $this->sapi()->config()->get(\Module\TenderBin\Module::CONF_KEY);
        $conf  = $conf['aliases_client'];
        while(isset($conf[$clientIdentifier]))
            $clientIdentifier = $conf[$clientIdentifier];


        $r = new OwnerObject;
        $r->setRealm($clientIdentifier);
        $r->setUid($token->getOwnerIdentifier());
        return $r;
    }
}
