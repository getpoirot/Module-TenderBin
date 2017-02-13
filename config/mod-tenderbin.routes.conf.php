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
            ListenerDispatch::CONF_KEY => function () {},
            'token' => /* (\Poirot\OAuth2\Interfaces\Server\Repository\iEntityAccessToken) */ null,
        ],

        'routes' => [

            // Create Bin
            'create' => [
                'route' => 'RouteSegment',
                'options' => [
                    'criteria'    => '[/:resource_hash{\w+}]',
                    'match_whole' => false,
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
                    'match_whole' => false,
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
                            ListenerDispatch::CONF_KEY => function() { kd('PUT'); },
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
                    ListenerDispatch::CONF_KEY => function() { kd('Info ...'); },
                ],
            ],
        ],
    ],
];
