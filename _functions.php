<?php
namespace Module\TenderBin
{
    use Poirot\Http\Interfaces\iHttpRequest;
    use Poirot\Http\Psr\ServerRequestBridgeInPsr;

    /**
     * Assert Authorization Token From Request
     *
     * @param iHttpRequest $request
     *
     * @return iEntityAccessToken
     */
    function assertAuthToken(iHttpRequest $request)
    {
        $requestPsr = new ServerRequestBridgeInPsr($request);
        $validator  = new AuthorizeByInternalServer($repoAccessTokens);

        return $validator->hasValidated($requestPsr);
    }
}
