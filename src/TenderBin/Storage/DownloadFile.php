<?php
namespace Module\TenderBin\Storage;

use Module\TenderBin\Interfaces\DownloadFileInterface;
use Poirot\Psr7\UploadedFile;


class DownloadFile 
    extends UploadedFile
    implements DownloadFileInterface
{
    /**
     * Get File System Stream Resource
     *
     * @return resource
     */
    function getFSResource()
    {
        return $this->_c_givenResource;
    }
}
