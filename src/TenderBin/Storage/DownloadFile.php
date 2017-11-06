<?php
namespace Module\TenderBin\Storage;

use Module\TenderBin\Interfaces\DownloadFileInterface;
use Poirot\Psr7\UploadedFile;


// TODO ability to set upstream such as temporary or caching stream to rewind file
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
