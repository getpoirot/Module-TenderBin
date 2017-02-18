<?php
/**
 *
 * @see \Poirot\Ioc\Container\BuildContainer
 */
use Poirot\Ioc\Container\BuildContainer;

return array(
    'services' => array(
        // assertToken(iHttpRequest $request)
        \Module\TenderBin\Actions\ServiceAssertToken::class,
        'createBin' => \Module\TenderBin\Actions\CreateBinAction::class,
    ),
);
