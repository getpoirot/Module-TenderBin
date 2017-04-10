<?php
namespace Module\TenderBin\Model\Entity;

use Module\TenderBin\Interfaces\Model\BinData\iObjectVersion;
use Module\TenderBin\Interfaces\Model\iEntityBindata;
use Poirot\Http\HttpMessage\Request\Plugin\ParseRequestData;
use Poirot\Http\Interfaces\iHttpRequest;
use Poirot\Std\ConfigurableSetter;
use Poirot\Std\Hydrator\HydrateGetters;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Use To Hydrate Bindata Entity From Http Request
 *
 */
class HydrateBindata
    extends ConfigurableSetter
    implements \IteratorAggregate
    , iEntityBindata
{
    const FIELD_TITLE        = 'title';
    const FIELD_CONTENT      = 'content';
    const FIELD_CONTENT_TYPE = 'content_type';
    const FIELD_META         = 'meta';
    const FIELD_PROTECTED    = 'protected';
    const FIELD_EXPIRATION   = 'expiration';

    protected $title;
    protected $content;
    protected $mimeType;
    protected $meta;
    protected $protected;
    protected $expiration;


    /**
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
                $this->setExpiration(null);
            } else {
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
        // Not Implemented
    }


    // Implement Configurable

    /**
     * @inheritdoc
     *
     * @param array|\Traversable|iHttpRequest $optionsResource
     * @param array       $_
     *        usually pass as argument into ::with if self instanced
     *
     * @throws \InvalidArgumentException if resource not supported
     * @return array
     */
    static function parseWith($optionsResource, array $_ = null)
    {
        if (!static::isConfigurableWith($optionsResource))
            throw new \InvalidArgumentException(sprintf(
                'Invalid Configuration Resource provided; given: (%s).'
                , \Poirot\Std\flatten($optionsResource)
            ));


        // ..
        if ($optionsResource instanceof iHttpRequest)
            # Parse and assert Http Request
            $optionsResource = ParseRequestData::_($optionsResource)->parseBody();

        return parent::parseWith($optionsResource);
    }

    /**
     * Is Configurable With Given Resource
     *
     * @param mixed $optionsResource
     *
     * @return boolean
     */
    static function isConfigurableWith($optionsResource)
    {
        return $optionsResource instanceof iHttpRequest || parent::isConfigurableWith($optionsResource);
    }


    // Implement IteratorAggregate

    /**
     * @ignore Ignore from getter hydrator
     *
     * Retrieve an external iterator
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return \Traversable An instance of an object implementing <b>Iterator</b> or
     * <b>Traversable</b>
     * @since 5.0.0
     */
    public function getIterator()
    {
        return new HydrateGetters($this);
    }
}
