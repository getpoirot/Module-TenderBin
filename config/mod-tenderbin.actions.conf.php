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
        'createBinAction' => \Module\TenderBin\Actions\CreateBinAction::class,
        'updateBinAction' => \Module\TenderBin\Actions\UpdateBinAction::class,
        'findBinAction'   => \Module\TenderBin\Actions\FindBinAction::class,
    ),
);
