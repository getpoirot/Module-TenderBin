<?php
namespace Module\TenderBin\Actions;

use Poirot\Application\aSapi;
use Poirot\Http\Interfaces\iHttpRequest;
use Poirot\Http\Psr\ServerRequestBridgeInPsr;
use Poirot\Ioc\Container\Service\aServiceContainer;
use Poirot\OAuth2\Interfaces\Server\Repository\iEntityAccessToken;
use Poirot\OAuth2\Resource\Validation\AuthorizeByRemoteServer;
use Poirot\OAuth2\Server\Exception\exOAuthServer;
use Poirot\Std\Struct\DataEntity;


class ServiceAssertToken
    extends aServiceContainer
{
    const CONF_KEY = 'ServiceAssertToken';


    /** @var string Service Name */
    protected $name = 'assertToken';

    protected $endPoint;
    protected $authHeader;


    /**
     * Create Service
     *
     * @return callable
     */
    function newService()
    {
        return $this->_makeCallable();
    }


    // options:

    /**
     * Get Endpoint
     * @return string
     */
    function getEndPoint()
    {
        if ($this->endPoint)
            return $this->endPoint;


        $config   = $this->_attainConf();
        $endpoint = $config['oauth_server']['token_endpoint'];

        return $this->endPoint = $endpoint;
    }

    /**
     * Set Token Endpoint of OAuth Server That extension grant is enabled on
     * @param string $endPoint
     * @return $this
     */
    function setEndPoint($endPoint)
    {
        $this->endPoint = (string) $endPoint;
        return $this;
    }

    /**
     * Get Authorize Header
     *
     * @return string
     */
    function getAuthHeader()
    {
        if ($this->authHeader)
            return $this->authHeader;


        $config   = $this->_attainConf();
        $endpoint = $config['oauth_server']['authorization'];

        return $this->endPoint = $endpoint;
    }

    /**
     * Set Authorize header interacting with oauth server to
     * validate client
     *
     * @param string $authHeader
     * @return $this
     */
    function setAuthHeader($authHeader)
    {
        $this->authHeader = (string) $authHeader;
        return $this;
    }


    // ..

    protected function _makeCallable()
    {
        $endpoint = $this->getEndPoint();
        $authHead = $this->getAuthHeader();

        /**
         * Assert Authorization Token From Request
         *
         * @param iHttpRequest $request
         *
         * @return iEntityAccessToken[]
         */
        return function (iHttpRequest $request) use ($endpoint, $authHead)
        {
            $requestPsr = new ServerRequestBridgeInPsr($request);
            $validator  = new AuthorizeByRemoteServer($endpoint, $authHead);

            try {
                $token = $validator->hasValidated($requestPsr);

                // TODO check scope

            } catch (exOAuthServer $e) {
                // any oauth server error will set token result to false
                $token = false;
            }

            return ['token' => $token];
        };
    }

    /**
     * Attain Merged Module Configuration
     * @return array
     */
    protected function _attainConf()
    {
        $sc     = $this->services();
        /** @var aSapi $sapi */
        $sapi   = $sc->get('/sapi');
        /** @var DataEntity $config */
        $config = $sapi->config();
        $config = $config->get(\Module\TenderBin\Module::CONF_KEY);

        $r = array();
        if (is_array($config) && isset($config[static::CONF_KEY]))
            $r = $config[static::CONF_KEY];

        return $r;
    }
}
