<?php
namespace Module\TenderBin\Model\Entity;

use Module\TenderBin\Interfaces\Model\BinData\iObjectVersion;
use Module\TenderBin\Interfaces\Model\iBindata;
use Module\TenderBin\Model\Entity\Bindata\VersionObject;
use Poirot\Std\Hydrator\aHydrateEntity;
use Psr\Http\Message\UploadedFileInterface;


/**
 * Use To Hydrate Bindata Entity From Http Request
 *
 */
class BindataHydrate
    extends aHydrateEntity
    implements iBindata
{
    const FIELD_TITLE        = 'title';
    const FIELD_CONTENT      = 'content';
    const FIELD_CONTENT_TYPE = 'content_type';
    const FIELD_META         = 'meta';
    const FIELD_PROTECTED    = 'protected';
    const FIELD_EXPIRATION   = 'expiration';
    const FIELD_VERSION      = 'version';

    protected $title;
    protected $content;
    protected $mimeType;
    protected $meta;
    protected $protected;
    protected $expiration;
    protected $version;


    /**
     * // TODO meta with [prefix __, is_file, filesize] is system prefixed and not allowed
     * Construct
     *
     * @param array|\Traversable $options
     * @param array|\Traversable $defaults
     */
    function __construct($options = null, $defaults = null)
    {
        if ($defaults !== null)
            $this->with( static::parseWith($defaults) );

        parent::__construct($options);
    }


    // Setter Options:

    function setTitle($title)
    {
        $this->title = $title;
    }

    function setContent($content)
    {
        $this->content = $content;
    }

    function setContentType($contentType)
    {
        $this->mimeType = $contentType;
    }

    function setMeta($meta)
    {
        $this->meta = $meta;
    }

    function setProtected($protected)
    {
        $this->protected = $protected;
    }

    function setExpiration($expiration)
    {
        $this->expiration = $expiration;
    }

    function setVersion($version)
    {
        $this->version = $version;
    }


    // Hydration Getters:

    /**
     * Get Bindata Unique Identifier
     *
     * @return mixed
     */
    function getIdentifier()
    {
        // Not Implemented
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
     * Meta Information Of Bin
     *
     * @return array
     */
    function getMeta()
    {
        return $this->meta;
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
     * Content Mime Type
     *
     * @return string
     */
    function getMimeType()
    {
        return $this->mimeType;
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
        // Not Implemented
    }

    /**
     * DateTime Expiration
     *
     * @return null|\DateTime
     */
    function getDatetimeExpiration()
    {

        if ($this->expiration !== null && !$this->expiration instanceof \DateTime) {

            $expiration = (string) $this->expiration;

            if ($expiration == '0') {
                // Consider infinite
                $this->setExpiration(false);
            } else {

                 if (strlen((string)$expiration)<10)
                    $expiration = time() +(int)$expiration;

                $dtStr = date("c", $expiration);
                $d = new \DateTime($dtStr);
                $this->setExpiration($d);
            }
        }

        return $this->expiration;
    }

    /**
     * Date Created
     *
     * @return \DateTime
     */
    function getDateCreated()
    {
        // Not Implemented
    }

    /**
     * Is Content Protected for owner(s)?
     *
     * @return bool
     */
    function isProtected()
    {
        if ($this->protected !== null && !is_bool($this->protected))
            // Returns TRUE for "1", "true", "on" and "yes". Returns FALSE otherwise.
            $this->setProtected(filter_var($this->protected, FILTER_VALIDATE_BOOLEAN));

        return $this->protected;
    }

    /**
     * Get Version Status
     *
     * @return iObjectVersion
     */
    function getVersion()
    {
        if ($this->version !== null && !$this->version instanceof iObjectVersion)
        {
            $version = new VersionObject;
            $version->setTag( (string) $this->version );

            $this->setVersion($version);
        }

        return $this->version;
    }


}
