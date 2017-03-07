<?php
namespace Module\TenderBin\Model;


use Module\TenderBin\Interfaces\Model\iEntityBindata;
use Poirot\Std\Interfaces\Struct\iData;
use Poirot\Std\Struct\DataEntity;
use Poirot\Std\Struct\DataOptionsOpen;
use Psr\Http\Message\UploadedFileInterface;


class Bindata extends DataOptionsOpen
    implements iEntityBindata
{
    protected $identifier;
    protected $title;
    protected $meta;
    protected $content;
    protected $mimeType;
    protected $ownerIdentifier;
    protected $datetimeExpiration;
    protected $dateCreated;
    protected $protected = false;


    /**
     * Set Identifier
     *
     * @param mixed $identifier
     *
     * @return $this
     */
    function setIdentifier($identifier)
    {
        $this->identifier = $identifier;
        return $this;
    }

    /**
     * Get Bindata Unique Identifier
     *
     * @return mixed
     */
    function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * Set Title
     *
     * @param string $title
     *
     * @return $this
     */
    function setTitle($title)
    {
        $this->title = (string) $title;
        return $this;
    }

    /**
     * Get Bin Title
     *
     * @return string
     */
    function getTitle()
    {
        return $this->title;
    }

    /**
     * Set Meta Data
     *
     * note: clean current data
     *
     * @param \Traversable|array $metaData
     *
     * @return $this
     */
    function setMeta($metaData)
    {
        $meta = $this->getMeta();
        $meta->clean()->import($metaData);
        return $this;
    }

    /**
     * Meta Information Of Bin
     *
     * @return iData
     */
    function getMeta()
    {
        if (!$this->meta)
            $this->meta = new DataEntity;

        return $this->meta;
    }

    /**
     * Set Content
     *
     * @param string|\Traversable|UploadedFileInterface $content
     *
     * @return $this
     */
    function setContent($content)
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Content Stored With Bin
     *
     * @return string|\Traversable|UploadedFileInterface
     */
    function getContent()
    {
        return $this->content;
    }

    /**
     * Set Mime Type Of Content Bin
     *
     * @param string $mimeType
     *
     * @return $this
     */
    function setMimeType($mimeType)
    {
        $this->mimeType = (string) $mimeType;
        return $this;
    }

    /**
     * Content Mime Type
     *
     * @return string
     */
    function getMimeType()
    {
        return $this->mimeType;
    }

    /**
     * Set Owner
     *
     * @param BindataOwnerObject $ownerUID
     *
     * @return $this
     */
    function setOwnerIdentifier(BindataOwnerObject $ownerUID)
    {
        $this->ownerIdentifier = $ownerUID;
        return $this;
    }

    /**
     * Get Owner Identifier
     *
     * note: owner uid or object that provide realm
     *       and owner definition.
     *
     * @return mixed
     */
    function getOwnerIdentifier()
    {
        return $this->ownerIdentifier;
    }

    /**
     * Set Date Time Expiration
     *
     * @param \DateTime|null $dateTime
     *
     * @return $this
     */
    function setDatetimeExpiration($dateTime)
    {
        if ($dateTime !== null && !$dateTime instanceof \DateTime)
            throw new \InvalidArgumentException(sprintf(
                'Datetime must instance of \Datetime or null; given: (%s).'
                , \Poirot\Std\flatten($dateTime)
            ));

        $this->datetimeExpiration = $dateTime;
        return $this;
    }

    /**
     * DateTime Expiration
     *
     * @return null|\DateTime
     */
    function getDatetimeExpiration()
    {
        return $this->datetimeExpiration;
    }

    /**
     * Set Date Created
     *
     * @param \DateTime $dateTime
     *
     * @return $this
     */
    function setDateCreated(\DateTime $dateTime)
    {
        $this->dateCreated = $dateTime;
        return $this;
    }

    /**
     * Date Created
     *
     * @return \DateTime
     */
    function getDateCreated()
    {
        if (!$this->dateCreated)
            $this->setDateCreated(new \DateTime());

        return $this->dateCreated;
    }

    /**
     * Set Protected Bindata for it's owner
     *
     * @param bool $bool
     *
     * @return $this
     */
    function setProtected($bool = true)
    {
        $this->protected = (boolean) $bool;
        return $this;
    }

    /**
     * Is Content Protected for owner(s)?
     *
     * @return bool
     */
    function isProtected()
    {
        return $this->protected;
    }
}
