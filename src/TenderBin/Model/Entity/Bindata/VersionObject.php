<?php
namespace Module\TenderBin\Model\Entity\Bindata;

use Module\TenderBin\Interfaces\Model\BinData\iObjectVersion;
use Poirot\Std\Struct\aValueObject;


class VersionObject
     extends aValueObject
    implements iObjectVersion
{
    /** @var mixed Determine this entity is subversion of this parent */
    protected $subversionOf;
    protected $tagName = 'latest';


    /**
     * @param mixed $parentVersionId
     * @return $this
     */
    function setSubversionOf($parentVersionId)
    {
        $this->subversionOf = $parentVersionId;
        return $this;
    }
    
    function getSubversionOf()
    {
        return $this->subversionOf;
    }

    /**
     * @param mixed $tagName
     * @return $this
     */
    function setTag($tagName)
    {
        $this->tagName = $tagName;
        return $this;
    }
    
    function getTag()
    {
        return $this->tagName;
    }
}
