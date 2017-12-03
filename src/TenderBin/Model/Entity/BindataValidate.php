<?php
namespace Module\TenderBin\Model\Entity;

use Poirot\Std\aValidator;
use Module\TenderBin\Interfaces\Model\iBindata;
use Poirot\Std\Exceptions\exUnexpectedValue;
use Psr\Http\Message\UploadedFileInterface;


class BindataValidate
    extends aValidator
{
    protected $entity;


    protected $allowed_mime_types = [
        '*'
    ];

    protected $denied_mime_types = [
        // 'image/*'
    ];


    /**
     * Construct
     *
     * $options:
     * [
     *    'allowed_mime_types' => 'images/*',
     *    'denied_mime_types'  => '*',
     * ]
     *
     * @param iBindata $entity
     * @param array    $options
     *
     */
    function __construct(iBindata $entity = null, array $options = null)
    {
        $this->entity = $entity;

        foreach ($options as $key => $val)
            $this->{$key} = $val;
    }


    /**
     * Assert Validate Entity
     *
     * @throws exUnexpectedValue
     */
    function doAssertValidate()
    {
        $exceptions = [];

        $content = $this->entity->getContent();

        if (!$content)
            $exceptions[] = new exUnexpectedValue('Parameter %s is required.', 'content');
        else {
            // Content Must Provide With Version Tag
            if (null === $this->entity->getVersion())
                $exceptions[] = new exUnexpectedValue('Parameter %s is required in combination with content.', 'version');
        }

        if ($content instanceof UploadedFileInterface) {
            // Content-Type can be retrieved from uploaded file
            // File Upload With No Error
            /** @var UploadedFileInterface $file */
            $file = $content;
            if ($file->getError())
                $exceptions[] = new exUnexpectedValue('Error Uploading File; The File Not Received.', 'content');

            $mimeType = $content->getClientMediaType();
            if ($mimeType == '*/*')
                $mimeType = $this->entity->getMimeType();

        } else {
            if (! $this->entity->getMimeType() )
                $exceptions[] = new exUnexpectedValue('Parameter %s is required.', 'MimeType');

            $mimeType = $this->entity->getMimeType();
        }


        $exceptions += $this->_assertMimeType($mimeType);
        return $exceptions;
    }

    /**
     * Check Given Mime Type Is Allowed?
     *
     * @param $mimeType
     *
     * @return exUnexpectedValue []
     */
    private function _assertMimeType($mimeType)
    {
        $r = [];

        if ( in_array($mimeType, $this->denied_mime_types) ) {
            // Exactly This Mime Type Is Denied For Upload ...
            $r[] = new exUnexpectedValue(sprintf('Mime Type (%s) Not Allowed.', $mimeType));
            return $r;
        }


        // Check Allowed MimeTypes
        //
        if (false === \Poirot\Std\isMimeMatchInList($this->allowed_mime_types, $mimeType) ) {
            $r[] = new exUnexpectedValue(sprintf('Mime Type (%s) Not Allowed.', $mimeType));
            return $r;
        }


        // Check Denied MimeTypes
        //
        if ($mimeType === \Poirot\Std\isMimeMatchInList($this->denied_mime_types, $mimeType)) {
            $r[] = new exUnexpectedValue(sprintf('Mime Type (%s) Not Allowed.', $mimeType));
            return $r;
        }


        return $r;
    }
}
