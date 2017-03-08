<?php
use Module\TenderBin\Actions\ServiceAssertTokenAction;

return [
    \Module\TenderBin\Module::CONF_KEY => [
        ServiceAssertTokenAction::CONF_KEY => [
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

                // Generate Unique ID While Persis Bin Data
                // with different situation we need vary id generated;
                // when we use storage as URL shortener we need more comfortable hash_id
                // but consider when we store lots of files
                'unique_id_generator' => function($id = null, $self = null)
                {
                    /** @var $self \Module\TenderBin\Model\Mongo\BindataRepo */
                    // note: currently generated hash allows 14,776,336 unique entry
                    do {
                        $id = \Poirot\Std\generateShuffleCode(4, \Poirot\Std\CODE_NUMBERS | \Poirot\Std\CODE_STRINGS);
                    } while ($self->findOneByHash($id));

                    // TODO iObjectID interface
                    return $id;
                }, // (callable) null = using default

                // ..

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
