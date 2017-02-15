<?php
use Poirot\Application\Sapi\Server\Http\ListenerDispatch;

return [
    'tenderbin'  => [
        'route' => 'RouteSegment',
        'options' => [
            'criteria'    => '/bin',
            'match_whole' => false,
        ],
        'params'  => [
            ListenerDispatch::CONF_KEY => [
                // This Action Run First In Chains and Assert Validate Token
                //! define array allow actions on matched routes chained after this action
                /*
                 * [
                 *    [0] => Callable Defined HERE
                 *    [1] => routes defined callable
                 *     ...
                 */
                '/module/tenderbin/actions/assertToken',
            ],
        ],

        'routes' => [

            // Create Bin
            'create' => [
                'route' => 'RouteSegment',
                'options' => [
                    'criteria'    => '[/:resource_hash{\w+}]',
                    'match_whole' => true,
                ],
                'routes' => [
                    // When POST something
                    'post' => [
                        'route'   => 'RouteMethod',
                        'options' => [
                            'method' => 'POST',
                        ],
                        'params'  => [
                            ListenerDispatch::CONF_KEY => function() { kd('POST'); },
                        ],
                    ],
                ],
            ],

            'resource' => [
                'route' => 'RouteSegment',
                'options' => [
                    'criteria'    => '/:resource_hash{\w+}',
                    'match_whole' => true, // exactly match with this not trailing paths
                ],
                'routes' => [
                    // When PUT to Update
                    'put' => [
                        'route'   => 'RouteMethod',
                        'options' => [
                            'method' => 'PUT',
                        ],
                        'params'  => [
                            ListenerDispatch::CONF_KEY => function() { kd('PUT'); },
                        ],
                    ],
                    // When DELETE resource
                    'delete' => [
                        'route'   => 'RouteMethod',
                        'options' => [
                            'method' => 'DELETE',
                        ],
                        'params'  => [
                            ListenerDispatch::CONF_KEY => function() { kd('DELETE'); },
                        ],
                    ],
                    // When Retrieve resource
                    'get' => [
                        'route'   => 'RouteMethod',
                        'options' => [
                            'method' => 'GET',
                        ],
                        'params'  => [
                            ListenerDispatch::CONF_KEY => function() { kd('GET'); },
                        ],
                    ],
                ],
            ],

            // Retrieve Bin Meta Info
            'info' => [
                'route' => 'RouteSegment',
                'options' => [
                    'criteria'    => '/inf/:resource_hash{\w+}',
                    'match_whole' => true,
                ],
                'params'  => [
                    ListenerDispatch::CONF_KEY => [
                        function($resource_hash = null, $token = null) {
                            k($resource_hash);
                            kd($token);
                        }
                    ],
                ],
            ],
        ],
    ],
];
