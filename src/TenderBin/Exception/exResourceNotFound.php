<?php
namespace Module\TenderBin\Exception;


class exResourceNotFound
    extends \RuntimeException
{
    protected $code = 404;
}
