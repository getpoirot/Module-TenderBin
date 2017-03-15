<?php
namespace Module\TenderBin\Interfaces\Model\BinData;

interface iObjectVersion
{
    /**
     * Annoying The Bin Is Sub Version Of Which Stored Bin?
     *
     * @return mixed|null
     */
    function getSubversionOf();

    /**
     * Get Current Version Tag Name
     * exp. latest
     *
     * @return string
     */
    function getTag();
}
