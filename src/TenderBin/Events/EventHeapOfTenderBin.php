<?php
namespace Module\TenderBin\Events;

use Module\TenderBin\Interfaces\Model\iBindata;
use Poirot\Events\Event;
use Poirot\Events\EventHeap;


class EventHeapOfTenderBin
    extends EventHeap
{
    const BIN_CREATED = 'bindata.created';


    /**
     * Initialize
     *
     */
    function __init()
    {
        $this->collector = new DataCollector;

        // attach default event names:
        $this->bind( new Event(self::BIN_CREATED) );
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
