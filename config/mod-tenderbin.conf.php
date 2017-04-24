<?php
use Module\OAuth2Client\Actions\ServiceAssertTokenAction;

return [

    ## ----------------------------------- ##
    ## OAuth2Client Module Must Configured ##
    ## to assert tokens ...                ##
    ## ----------------------------------- ##

    \Module\OAuth2Client\Module::CONF_KEY => [
        // Configure module ....
    ],


    # Mongo Driver:

    Module\MongoDriver\Module::CONF_KEY =>
    [
        \Module\MongoDriver\Services\aServiceRepository::CONF_REPOSITORIES =>
        [
            \Module\TenderBin\Model\Driver\Mongo\BindataRepoService::class => [

                // Generate Unique ID While Persis Bin Data
                // with different situation we need vary id generated;
                // when we use storage as URL shortener we need more comfortable hash_id
                // but consider when we store lots of files
                'unique_id_generator' => function($id = null, $self = null)
                {
                    /** @var $self \Module\TenderBin\Model\Driver\Mongo\BindataRepo */
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
                    'client' => 'master',
                    // ensure indexes
                    'indexes' => [
                        ['key' => ['_id' => 1]],
                        ['key' => ['meta' => 1]],
                        ['key' => ['mime_type' => 1]],
                        ['key' => ['owner_identifier' => 1]],
                        // db.tenderbin.bins.createIndex({"datetime_expiration_mongo": 1}, {expireAfterSeconds: 0});
                        [ 'key' => ['datetime_expiration_mongo' => 1 ], /*'expireAfterSeconds'=> 0*/],
                        [ 'key' => ['version.subversion_of' => 1 ], 'version.tag'=> 1],
                    ],],],
        ],
    ],
];
