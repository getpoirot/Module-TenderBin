<?php
namespace Module\TenderBin\Events;

use Module\TenderBin\Interfaces\Model\iBindata;
use Poirot\Events\Event;
use Poirot\Events\EventHeap;


class EventHeapOfTenderBin
    extends EventHeap
{
    const BEFORE_CREATE_BIN   = 'bindata.before.create';
    const AFTER_BIN_CREATED   = 'bindata.after.created';


    /**
     * Initialize
     *
     */
    function __init()
    {
        $this->collector = new DataCollector;

        // attach default event names:
        $this->bind( new Event(self::BEFORE_CREATE_BIN) );
        $this->bind( new Event(self::AFTER_BIN_CREATED) );
    }


    /**
     * @override ide auto info
     * @inheritdoc
     *
     * @return DataCollector
     */
    function collector($options = null)
    {
        return parent::collector($options);
    }
}

class DataCollector
    extends \Poirot\Events\Event\DataCollector
{
    /** @var iBindata */
    protected $binObject;


    /**
     * @return iBindata
     */
    function getBinObject()
    {
        return $this->binObject;
    }

    /**
     * @param iBindata $binObject
     */
    function setBinObject($binObject)
    {
        $this->binObject = $binObject;
    }
}
