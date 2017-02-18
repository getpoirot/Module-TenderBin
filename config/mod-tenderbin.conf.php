<?php
use Module\TenderBin\Actions\ServiceAssertToken;

return [
    \Module\TenderBin\Module::CONF_KEY => [
        ServiceAssertToken::CONF_KEY => [
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

                    ],],],

        ],
    ],
];
