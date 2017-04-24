<?php
namespace Module\TenderBin\Storage;

use Poirot\Std\Struct\aValueObject;

class FileEntity
    extends aValueObject
{
    protected $id;
    protected $filename;
    protected $length;
    protected $md5;


    function set_Id($identifier)
    {
        $this->id = $identifier;
    }

    function get_Id()
    {
        return $this->id;
    }

    function getFilename()
    {
        return $this->filename;
    }

    function setFilename($filename)
    {
        $this->filename = $filename;
    }

    function setLength($length)
    {
        $this->length = $length;
    }

    function getLength()
    {
        return $this->length;
    }

    function getMd5()
    {
        return $this->md5;
    }

    function setMd5($md5)
    {
        $this->md5 = $md5;
    }
}
