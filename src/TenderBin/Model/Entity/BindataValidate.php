<?php
namespace Module\TenderBin\Model\Entity;

use Module\TenderBin\Interfaces\Model\iBindata;
use Poirot\Std\Exceptions\exUnexpectedValue;
use Psr\Http\Message\UploadedFileInterface;


class BindataValidate
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
    function assertValidate()
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


        // ..

        $_f__chainExceptions = function (exUnexpectedValue $ex, &$list) use (&$_f__chainExceptions)
        {
            if (empty($list))
                return $ex;

            $exception = array_pop($list);

            $r = new exUnexpectedValue(
                $exception->getMessage()
                , $exception->getParameterName()
                , $exception->getCode()
                , $_f__chainExceptions($ex, $list)
            );

            return $r;
        };

        if (!empty($exceptions)) {
            $ex = $_f__chainExceptions(new exUnexpectedValue('Validation Error', ''), $exceptions);
            throw $ex;
        }
    }
}
