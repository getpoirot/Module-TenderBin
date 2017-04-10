<?php
namespace Module\TenderBin\Actions;


use Module\TenderBin\Interfaces\Model\iEntityBindata;
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


    // Action Chain Helpers:

    /**
     * Assert BinData Access By Check Permission
     * note: determine current user from token
     *
     * @param boolean $forceCheckPermission Force Check Permission On None Protected Resource
     *
     * @return \Closure
     */
    static function functorAssertBinPermissionAccess($forceCheckPermission = false)
    {
        /**
         * @param iEntityBindata     $binData
         * @param iEntityAccessToken $token
         * @return array
         */
        return function ($binData = null, iEntityAccessToken $token = null) use ($forceCheckPermission)
        {
            if (!$binData instanceof iEntityBindata)
                throw new \RuntimeException(sprintf(
                    'BinData Entity must be instance of iEntityBindata; given: (%s).'
                    , gettype($token)
                ));


            if (false === $forceCheckPermission)
                // Force To Check Owner Permission 
                if (!$binData->isProtected())
                    // Bin Data is not protected; let it play ...
                    return;
            
            
            # Check Owner Privilege On Modify Bindata
            $curOwnerObject = \Module\TenderBin\buildOwnerObjectFromToken($token);
            $binOwnerObject = $binData->getOwnerIdentifier();
            foreach ($binOwnerObject as $k => $v) {
                if ($curOwnerObject->{$k} !== $v)
                    // Mismatch Owner!!
                    throw new exAccessDenied('Owner Mismatch; You have not access to edit this data.');
            }
        };
    }
}
