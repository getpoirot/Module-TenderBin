<?php
namespace Module\TenderBin\Model;


use Poirot\Std\Struct\aDataOptions;


class BindataOwnerObject
     extends aDataOptions
{
    protected $realm;
    protected $uid;


    /**
     * Realm
     *
     * @return string|null
     */
    function getRealm()
    {
        return $this->realm;
    }

    /**
     * Set Realm
     *
     * note: it can be oauth app identification
     *
     * @param string $realm
     *
     * @return $this
     */
    function setRealm($realm)
    {
        $this->realm = (string) $realm;
        return $this;
    }

    /**
     * User Identification
     *
     * @return string
     */
    function getUid()
    {
        return $this->uid;
    }

    /**
     * Set User Identifier
     *
     * @param string $uid
     *
     * @return $this
     */
    function setUid($uid)
    {
        $this->uid = (string) $uid;
        return $this;
    }
}
