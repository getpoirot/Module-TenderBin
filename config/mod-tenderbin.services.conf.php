<?php
/**
 * Default TenderBin IOC Services
 *
 * @see \Poirot\Ioc\Container\BuildContainer
 *
 * ! These Services Can Be Override By Name (also from other modules).
 *   Nested in IOC here at: /module/tenderbin/services
 *
 *
 * @see \Module\TenderBin::getServices()
 */
return [
    'nested' => [
        'repository' => [
            // Define Default Services
            'services' =>
            [
                \Module\TenderBin\Model\Mongo\BindataRepoService::class,
            ],
        ],
    ],
];
