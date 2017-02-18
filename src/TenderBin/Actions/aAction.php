<?php
namespace Module\TenderBin\Actions;


use Module\TenderBin\Model\BindataOwnerObject;
use Poirot\Application\Exception\exAccessDenied;
use Poirot\OAuth2\Interfaces\Server\Repository\iEntityAccessToken;

abstract class aAction
{
    abstract function __invoke($_ = null);



    // Action Chain Helpers:

    /**
     * Assert Token In Actions that need token is valid
     *
     * @return callable
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
     * @return callable
     */
    static function functorParseOwnerIdentifierFromToken()
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
            return array('ownerIdentifier' => $r);
        };
    }
}
