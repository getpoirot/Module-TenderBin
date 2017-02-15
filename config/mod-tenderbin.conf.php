<?php
use Module\TenderBin\Actions\ServiceAssertToken;

return [
    \Module\TenderBin\Module::CONF_KEY => [
        ServiceAssertToken::CONF_KEY => [
            'oauth_server' => [
                'token_endpoint' => '',
                'authorization'  => '',
            ],
        ]
    ]
];
