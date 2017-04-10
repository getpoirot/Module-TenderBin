<?php
namespace Module\TenderBin
{
    use Module\TenderBin\Model\Entity\Bindata\OwnerObject;
    use Poirot\OAuth2\Interfaces\Server\Repository\iEntityAccessToken;

    /**
     * Build OwnerObject From Token
     *
     * @param iEntityAccessToken $token
     *
     * @return OwnerObject
     */
    function buildOwnerObjectFromToken($token = null)
    {
        if (!$token instanceof iEntityAccessToken)
            throw new \RuntimeException(sprintf(
                'Token must be instance of iEntityAccessToken; given: (%s).'
                , gettype($token)
            ));


        $r = new OwnerObject;
        $r->setRealm($token->getClientIdentifier());
        $r->setUid($token->getOwnerIdentifier());
        return $r;
    };
}
