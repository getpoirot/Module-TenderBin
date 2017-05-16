<?php
namespace Module\TenderBin\Storage;

use Poirot\Psr7\UploadedFile;
use Psr\Http\Message\UploadedFileInterface;


class UploadFile
    extends UploadedFile
    implements UploadedFileInterface
{

}
