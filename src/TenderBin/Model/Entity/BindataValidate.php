<?php
namespace Module\TenderBin\Model\Entity;

use Module\TenderBin\Interfaces\Model\iBindata;
use Poirot\Std\aValidator;
use Poirot\Std\Exceptions\exUnexpectedValue;
use Psr\Http\Message\UploadedFileInterface;


class BindataValidate
    extends aValidator
{
    protected $entity;


    /**
     * Construct
     * @param iBindata $entity
     */
    function __construct(iBindata $entity = null)
    {
        $this->entity = $entity;
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

        } else {
            if (!$this->entity->getMimeType())
                $exceptions[] = new exUnexpectedValue('Parameter %s is required.', 'MimeType');
        }


        return $exceptions;
    }
}
