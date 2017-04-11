<?php
namespace Module\TenderBin\Interfaces\Model;

use Module\TenderBin\Interfaces\Model\BinData\iObjectVersion;
use Poirot\Std\Interfaces\Struct\iData;


interface iBindata
{
    /**
     * Get Bindata Unique Identifier
     *
     * @return mixed
     */
    function getIdentifier();

    /**
     * Get Bin Title
     *
     * @return string
     */
    function getTitle();

    /**
     * Meta Information Of Bin
     *
     * @return iData
     */
    function getMeta();

    /**
     * Content Stored Within Bin
     *
     * @return mixed
     */
    function getContent();

    /**
     * Content Mime Type
     *
     * @return string
     */
    function getMimeType();

    /**
     * Get Owner Identifier
     *
     * note: owner uid or object that provide realm
     *       and owner definition.
     *
     * @return mixed
     */
    function getOwnerIdentifier();

    /**
     * DateTime Expiration
     *
     * @return null|\DateTime
     */
    function getDatetimeExpiration();

    /**
     * Date Created
     *
     * @return \DateTime
     */
    function getDateCreated();

    /**
     * Is Content Protected for owner(s)?
     *
     * @return bool
     */
    function isProtected();

    /**
     * Get Version Status
     * 
     * @return iObjectVersion
     */
    function getVersion();
}
