<?php
use Module\TenderBin\Actions\ServiceAssertToken;

return [
    \Module\TenderBin\Module::CONF_KEY => [
        ServiceAssertToken::CONF_KEY => [
            /*
            'debug_mode' => [
                // Not Connect to OAuth Server and Used Asserted Token With OwnerObject Below 
                'enabled' => filter_var(getenv('OAUTH_DEBUG_MODE'), FILTER_VALIDATE_BOOLEAN),
                'owner_identifier' => new \Module\TenderBin\Model\BindataOwnerObject([
                    'realm' => 'test',
                ]),
            ],
            */
            'oauth_server' => [
                'token_endpoint' => '',
                'authorization'  => '',
            ],
        ],
    ],


    # Mongo Driver:

    Module\MongoDriver\Module::CONF_KEY =>
    [
        \Module\MongoDriver\Services\aServiceRepository::CONF_KEY =>
        [
            \Module\TenderBin\Model\Mongo\BindataRepoService::class => [
                'collection' => [
                    // query on which collection
                    'name' => 'bins',
                    // which client to connect and query with
                    'client' => \Module\MongoDriver\Module\MongoDriverManagementFacade::CLIENT_DEFAULT,
                    // ensure indexes
                    'indexes' => [
                        ['key' => ['_id' => 1]],
                        // db.tenderbin.bins.createIndex({"date_created_mongo": 1}, {expireAfterSeconds: 0});
                        [ 'key' => ['date_created_mongo' => 1 ], 'expireAfterSeconds'=> 0],
                    ],],],
        ],
    ],
];
