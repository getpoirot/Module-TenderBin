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
                    'criteria'    => '[/:custom_uid{\w+}]',
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
                            ListenerDispatch::CONF_KEY => [
                                \Module\TenderBin\Actions\CreateBinAction::functorAssertTokenExists(),
                                \Module\TenderBin\Actions\CreateBinAction::functorParseOwnerObjectFromToken(),
                                \Module\TenderBin\Actions\CreateBinAction::functorMakeBindataEntityFromRequest(),
                                '/module/tenderbin/actions/createBinAction',
                            ],
                        ],
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
                    ListenerDispatch::CONF_KEY => [
                        \Module\TenderBin\Actions\SearchBinAction::functorAssertTokenExists(),
                        \Module\TenderBin\Actions\SearchBinAction::functorParseOwnerObjectFromToken(),
                        \Module\TenderBin\Actions\SearchBinAction::functorParseQueryTermFromRequest(),
                        '/module/tenderbin/actions/searchBinAction',
                    ],
                ],
            ],
            
            'resource' => [
                'route' => 'RouteSegment',
                'options' => [
                    'criteria'    => '/:resource_hash{\w+}',
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
                            ListenerDispatch::CONF_KEY => [
                                '/module/tenderbin/actions/getMetaBinAction',
                                \Module\TenderBin\Actions\GetMetaBinAction::functorAssertBinPermissionAccess(),
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
                            ListenerDispatch::CONF_KEY => [
                                '/module/tenderbin/actions/getMetaBinAction',
                                \Module\TenderBin\Actions\GetMetaBinAction::functorAssertBinPermissionAccess(),
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
                            ListenerDispatch::CONF_KEY => [
                                '/module/tenderbin/actions/findBinAction',
                                // only owner can update data from bin store
                                \Module\TenderBin\Actions\UpdateBinAction::functorAssertBinPermissionAccess(true),
                                \Module\TenderBin\Actions\UpdateBinAction::functorParseUpdateFromRequest(),
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
                            ListenerDispatch::CONF_KEY => [
                                '/module/tenderbin/actions/findBinAction',
                                // only owner can delete from bin store
                                \Module\TenderBin\Actions\DeleteBinAction::functorAssertBinPermissionAccess(true),
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
                            ListenerDispatch::CONF_KEY => [
                                '/module/tenderbin/actions/renderBinAction',
                                \Module\TenderBin\Actions\RenderBinAction::functorAssertBinPermissionAccess(),
                                \Module\TenderBin\Actions\RenderBinAction::functorResponseRenderContent(),
                            ],
                        ],
                    ],
                ],
            ],

        ],
    ],
];
