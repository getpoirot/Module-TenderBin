<?php
namespace Module\TenderBin;

use Poirot\Application\Interfaces\Sapi;
use Poirot\Application\Sapi\Module\ContainerForFeatureActions;
use Poirot\Ioc\Container\BuildContainer;
use Poirot\Router\BuildRouterStack;
use Poirot\Router\Interfaces\iRouterStack;
use Poirot\Std\Interfaces\Struct\iDataEntity;

/*
Response:
The status_code is 200 for a successful request,
403 when rate limited,
503 for temporary unavailability,
404 to indicate not-found responses,
and 400 for all other invalid requests or responses.
*/

class Module implements Sapi\iSapiModule
    , Sapi\Module\Feature\iFeatureModuleMergeConfig
    , Sapi\Module\Feature\iFeatureModuleNestActions
    , Sapi\Module\Feature\iFeatureOnPostLoadModulesGrabServices
{
    const CONF_KEY = 'module.tenderbin';


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
     * Get Action Services
     *
     * priority not that serious
     *
     * - return Array used to Build ModuleActionsContainer
     *
     * @return array|ContainerForFeatureActions|BuildContainer|\Traversable
     */
    function getActions()
    {
        return \Poirot\Config\load(__DIR__ . '/../../config/mod-tenderbin_actions');
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
