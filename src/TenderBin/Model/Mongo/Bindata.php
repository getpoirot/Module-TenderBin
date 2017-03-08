<?php
namespace Module\TenderBin\Model\Mongo;


use Module\TenderBin\Interfaces\Model\iEntityBindata;

use Module\MongoDriver\Model\tPersistable;
use Module\TenderBin\Model\BindataOwnerObject;
use MongoDB\BSON\Persistable;
use MongoDB\BSON\UTCDatetime;


class Bindata
    extends \Module\TenderBin\Model\Bindata
    implements iEntityBindata
    , Persistable
{
    use tPersistable;


    /**
     * @override Set as Mongo _id
     *
     * Set Identifier
     *
     * @param mixed $identifier
     *
     * @return $this
     */
    function setIdentifier($identifier)
    {
        $this->set_Id($identifier);
        return $this;
    }

    /**
     * @override Ignore from persistence
     * @ignore
     *
     * Get Bindata Unique Identifier
     *
     * @inheritdoc
     */
    function getIdentifier()
    {
        return $this->get_Id();
    }


    // Mongonize DateCreated

    /**
     * Set Created Date
     *
     * @param UTCDatetime $date
     *
     * @return $this
     */
    function setDateCreatedMongo(UTCDatetime $date)
    {
        $this->setDateCreated($date->toDateTime());
        return $this;
    }

    /**
     * Get Created Date
     * note: persist when serialize
     *
     * @return UTCDatetime
     */
    function getDateCreatedMongo()
    {
        $dateTime = $this->getDateCreated();
        return new UTCDatetime($dateTime->getTimestamp() * 1000);
    }

    /**
     * @override Ignore from persistence
     * @ignore
     *
     * Date Created
     *
     * @return \DateTime
     */
    function getDateCreated()
    {
        return parent::getDateCreated();
    }


    // Mongonize DateExpiration

    /**
     * Set Date Time Expiration
     *
     * @param UTCDatetime|null $dateTime
     *
     * @return $this
     */
    function setDatetimeExpirationMongo($dateTime)
    {
        if ($dateTime !== null && !$dateTime instanceof UTCDatetime)
            throw new \InvalidArgumentException(sprintf(
                'Datetime must instance of UTCDatetime or null; given: (%s).'
                , \Poirot\Std\flatten($dateTime)
            ));


        if ($dateTime instanceof UTCDatetime)
            $dateTime = $dateTime->toDateTime();

        $this->setDatetimeExpiration($dateTime);
        return $this;
    }

    /**
     * DateTime Expiration
     *
     * @return null|UTCDatetime
     */
    function getDatetimeExpirationMongo()
    {
        $dateTime = $this->getDatetimeExpiration();
        if ($dateTime !== null)
            $dateTime = new UTCDatetime($dateTime->getTimestamp() * 1000);

        return $dateTime;
    }

    /**
     * @override Ignore from persistence
     * @ignore
     *
     * DateTime Expiration
     *
     * @return null|\DateTime
     */
    function getDatetimeExpiration()
    {
        return parent::getDatetimeExpiration();
    }


    // ...

    /**
     * Constructs the object from a BSON array or document
     * Called during unserialization of the object from BSON.
     * The properties of the BSON array or document will be passed to the method as an array.
     * @link http://php.net/manual/en/mongodb-bson-unserializable.bsonunserialize.php
     * @param array $data Properties within the BSON array or document.
     */
    function bsonUnserialize(array $data)
    {
        if (isset($data['owner_identifier']))
            // Unserialize BsonDocument to Required BindataOwnerObject from Persistence
            $data['owner_identifier'] = new BindataOwnerObject($data['owner_identifier']);

        $this->import($data);
    }
}
