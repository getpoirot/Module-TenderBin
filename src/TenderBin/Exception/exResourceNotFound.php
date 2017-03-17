<?php
namespace Module\TenderBin\Exception;

use Poirot\Application\Exception\exRouteNotMatch;

class exResourceNotFound
    extends exRouteNotMatch
{
    protected $code = 404;
}
