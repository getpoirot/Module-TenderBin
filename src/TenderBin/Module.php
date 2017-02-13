<?php
namespace Module\TenderBin;

use Poirot\Application\Interfaces\Sapi;
use Poirot\Router\BuildRouterStack;
use Poirot\Router\Interfaces\iRouterStack;
use Poirot\Std\Interfaces\Struct\iDataEntity;


class Module implements Sapi\iSapiModule
    , Sapi\Module\Feature\iFeatureModuleMergeConfig
    , Sapi\Module\Feature\iFeatureOnPostLoadModulesGrabServices
{
    /**
     * Register config key/value
     *
     * priority: 1000 D
     *
     * - you may return an array or Traversable
     *   that would be merge with config current data
     *
     * @param iDataEntity $config
     *
     * @return array|\Traversable
     */
    function initConfig(iDataEntity $config)
    {
        return \Poirot\Config\load(__DIR__ . '/../../config/mod-tenderbin');
    }

    /**
     * Resolve to service with name
     *
     * - each argument represent requested service by registered name
     *   if service not available default argument value remains
     * - "services" as argument will retrieve services container itself.
     *
     * ! after all modules loaded
     *
     * @param iRouterStack $router
     */
    function resolveRegisteredServices(
        $router = null
    ) {
        # Register Http Routes:
        if ($router) {
            $routes = include __DIR__ . '/../../config/mod-tenderbin.routes.conf.php';
            $buildRoute = new BuildRouterStack;
            $buildRoute->setRoutes($routes);
            $buildRoute->build($router);
        }
    }
}
