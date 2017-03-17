<?php
namespace Module\TenderBin\Interfaces;

use Psr\Http\Message\UploadedFileInterface;


/**
 * File while retrieved from storage
 * 
 */
interface DownloadFileInterface
    extends UploadedFileInterface
{
    /**
     * Get File System Stream Resource
     * 
     * @return resource
     */
    function getFSResource();
}
