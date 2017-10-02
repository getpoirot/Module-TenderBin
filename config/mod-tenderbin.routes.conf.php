<?php
use Module\HttpFoundation\Events\Listener\ListenerDispatch;

return [
    'tenderbin'  => [

        'route'    => 'RouteSegment',
        'priority' => -10, // force host match before others.
        'options' => [
            'criteria'    => '/bin',
            'match_whole' => false,
        ],

        /*
        'route'    => 'RouteHostname',
        'priority' => -10, // force host match before others.
        'options' => [
            'criteria'    => 'storage.~.+~',
        ],
        */

        'params'  => [
            ListenerDispatch::ACTIONS => [
                // This Action Run First In Chains and Assert Validate Token
                //! define array allow actions on matched routes chained after this action
                /*
                 * [
                 *    [0] => Callable Defined HERE
                 *    [1] => routes defined callable
                 *     ...
                 */
                '/module/oauth2client/actions/AssertToken' => 'token',
            ],
        ],

        'routes' => [

            // Cleanup Bins
            // This endpoint must secured from direct access ....
            // TODO cleanup moved and used as cli-command
            'cleanup' => [
                'route' => 'RouteSegment',
                'options' => [
                    'criteria'    => '/federate/cleanup',
                    'match_whole' => true,
                ],
                'params'  => [
                    ListenerDispatch::ACTIONS => [
                        '/module/tenderbin/actions/cleanupBinsAction',
                    ],
                ],
            ],

            // Create Bin
            'create' => [
                'route' => 'RouteMethodSegment',
                'options' => [
                    'criteria'    => '/<:custom_uid~\w+~>',
                    'method'      => 'POST',
                    'match_whole' => true,
                ],
                'params'  => [
                    ListenerDispatch::ACTIONS => [
                        '/module/tenderbin/actions/createBinAction',
                    ],
                ],
            ],

            'search' => [
                'route' => 'RouteSegment',
                'options' => [
                    'criteria'    => '/search',
                    'match_whole' => true,
                ],
                'params'  => [
                    ListenerDispatch::ACTIONS => [
                        '/module/tenderbin/actions/searchBinAction',
                    ],
                ],
            ],
            
            'resource' => [
                'route' => 'RouteSegment',
                'options' => [
                    'criteria'    => '/:resource_hash~\w+~</:filename~.+/~>',
                    'match_whole' => false, // exactly match with this not trailing paths
                ],
                'routes' => [
                    // Retrieve Bin Meta Info
                    'meta' => [
                        'route' => 'RouteSegment',
                        'options' => [
                            'criteria'    => '/meta',
                            'match_whole' => true,
                        ],
                        'params'  => [
                            ListenerDispatch::ACTIONS => [
                                '/module/tenderbin/actions/getMetaBinAction',
                                \Module\TenderBin\Actions\GetMetaBinAction::functorResponseGetInfoResult(),
                            ],
                        ],
                    ],
                    // Meta Data
                    'head' => [
                        'route'   => 'RouteMethod',
                        'options' => [
                            'method' => 'HEAD',
                        ],
                        'params'  => [
                            ListenerDispatch::ACTIONS => [
                                '/module/tenderbin/actions/getMetaBinAction',
                                \Module\TenderBin\Actions\GetMetaBinAction::functorResponseHeadInfoResult(),
                            ],
                        ],
                    ],

                    // When PUT to Update
                    'put' => [
                        'route'   => 'RouteMethod',
                        'options' => [
                            'method' => 'PUT',
                        ],
                        'params'  => [
                            ListenerDispatch::ACTIONS => [
                                '/module/tenderbin/actions/updateBinAction',
                            ],
                        ],
                    ],
                    // When DELETE resource
                    'delete' => [
                        'route'   => 'RouteMethod',
                        'options' => [
                            'method' => 'DELETE',
                        ],
                        'params'  => [
                            ListenerDispatch::ACTIONS => [
                                '/module/tenderbin/actions/deleteBinAction',
                            ],
                        ],
                    ],
                    // When Retrieve resource
                    'get' => [
                        'route'   => 'RouteMethod',
                        'options' => [
                            'method' => 'GET',
                        ],
                        'params'  => [
                            ListenerDispatch::ACTIONS => [
                                '/module/tenderbin/actions/renderBinAction',
                            ],
                        ],
                    ],
                ],
            ],

        ],
    ],
];
