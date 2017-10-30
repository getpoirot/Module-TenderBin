<?php
namespace Module\TenderBin
{
    use Poirot\Application\Interfaces\Sapi;
    use Poirot\Application\ModuleManager\Interfaces\iModuleManager;
    use Poirot\Application\Sapi\Module\ContainerForFeatureActions;
    use Poirot\Ioc\Container;
    use Poirot\Ioc\Container\BuildContainer;
    use Poirot\Router\BuildRouterStack;
    use Poirot\Router\Interfaces\iRouterStack;
    use Poirot\Std\Interfaces\Struct\iDataEntity;


    class Module implements Sapi\iSapiModule
        , Sapi\Module\Feature\iFeatureModuleInitModuleManager
        , Sapi\Module\Feature\iFeatureModuleMergeConfig
        , Sapi\Module\Feature\iFeatureModuleNestServices
        , Sapi\Module\Feature\iFeatureOnPostLoadModulesGrabServices
        , Sapi\Module\Feature\iFeatureModuleNestActions
    {
        const CONF_KEY = 'module.tenderbin';


        /**
         * Initialize Module Manager
         *
         * priority: 1000 C
         *
         * @param iModuleManager $moduleManager
         *
         * @return void
         */
        function initModuleManager(iModuleManager $moduleManager)
        {
            // ( ! ) ORDER IS MANDATORY

            if (!$moduleManager->hasLoaded('MongoDriver'))
                // MongoDriver Module Is Required.
                $moduleManager->loadModule('MongoDriver');

            if (!$moduleManager->hasLoaded('OAuth2Client'))
                // Load OAuth2 Client To Assert Tokens.
                $moduleManager->loadModule('OAuth2Client');
        }

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
            return \Poirot\Config\load(__DIR__ . '/../../config/mod-tenderbin.actions');
        }

        /**
         * Get Nested Module Services
         *
         * it can be used to manipulate other registered services by modules
         * with passed Container instance as argument.
         *
         * priority not that serious
         *
         * @param Container $moduleContainer
         *
         * @return null|array|BuildContainer|\Traversable
         */
        function getServices(Container $moduleContainer = null)
        {
            $conf = \Poirot\Config\load(__DIR__ . '/../../config/mod-tenderbin.services');
            return $conf;
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
}


namespace Module\TenderBin
{
    use Module\TenderBin\Actions\Helper\CreateBin;
    use Module\TenderBin\Interfaces\Model\iBindata;
    use Module\TenderBin\Model\Driver\Mongo\BindataEntity;


    /**
     * @see CreateBin
     * @method static BindataEntity CreateBin(iBindata $entity = null)
     * ...............................................................
     */
    class Actions extends \IOC
    { }
}
