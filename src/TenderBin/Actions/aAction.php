<?php
namespace Module\TenderBin\Actions;


use Module\TenderBin\Interfaces\Model\iEntityBindata;
use Module\TenderBin\Model\BindataOwnerObject;
use Poirot\Application\Exception\exAccessDenied;
use Poirot\OAuth2\Interfaces\Server\Repository\iEntityAccessToken;


abstract class aAction
{
    abstract function __invoke(/*$_ = null*/);



    // Action Chain Helpers:

    /**
     * Assert Token In Actions that need token is valid
     *
     * @return \Closure
     */
    static function functorAssertTokenExists()
    {
        /**
         * @param null|iEntityAccessToken $token
         */
        return function ($token = null) {
            if (!$token instanceof iEntityAccessToken)
                throw new exAccessDenied('Token is revoked or mismatch.');


            // let it play!!
        };
    }

    /**
     * Parse Owner Identifier Object from given Token Assertion
     *
     * @return \Closure
     */
    static function functorParseOwnerObjectFromToken()
    {
        /**
         * @param null|iEntityAccessToken $token
         * @return array
         */
        return function ($token = null) {
            if (!$token instanceof iEntityAccessToken)
                throw new \RuntimeException(sprintf(
                    'Token must be instance of iEntityAccessToken; given: (%s).'
                    , gettype($token)
                ));


            $r = new BindataOwnerObject;
            $r->setRealm($token->getClientIdentifier());
            $r->setUid($token->getOwnerIdentifier());
            ## pass as argument to next chain
            return array('ownerObject' => $r);
        };
    }

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
        return function ($binData = null, $token = null) use ($forceCheckPermission)
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
            $curOwnerObject = static::functorParseOwnerObjectFromToken()->__invoke($token);
            $curOwnerObject = current($curOwnerObject);
            $binOwnerObject = $binData->getOwnerIdentifier();
            foreach ($binOwnerObject as $k => $v) {
                if ($curOwnerObject->{$k} !== $v)
                    // Mismatch Owner!!
                    throw new exAccessDenied('Owner Mismatch; You have not access to edit this data.');
            }
        };
    }
}
