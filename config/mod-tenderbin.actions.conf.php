<?php
/**
 *
 * @see \Poirot\Ioc\Container\BuildContainer
 */
use Poirot\Ioc\Container\BuildContainer;

return array(
    'services' => array(
        'renderBinAction'  => \Module\TenderBin\Actions\RenderBinAction::class,
        'createBinAction'  => \Module\TenderBin\Actions\CreateBinAction::class,
        'updateBinAction'  => \Module\TenderBin\Actions\UpdateBinAction::class,
        'deleteBinAction'  => \Module\TenderBin\Actions\DeleteBinAction::class,
        'searchBinAction'  => \Module\TenderBin\Actions\SearchBinAction::class,
        'getMetaBinAction' => \Module\TenderBin\Actions\GetMetaBinAction::class,
    ),
);
