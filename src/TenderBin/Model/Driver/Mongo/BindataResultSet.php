<?php
namespace Module\TenderBin\Model\Driver\Mongo;

use Module\TenderBin\Interfaces\DownloadFileInterface;
use Module\TenderBin\Interfaces\Model\iBindata;
use Module\TenderBin\Storage\DownloadFile;
use Module\TenderBin\Storage\StorageGridFS;
use MongoDB\Driver\Cursor;
use Psr\Http\Message\UploadedFileInterface;
use Traversable;


class BindataResultSet
    implements \IteratorAggregate
{
    protected $storage;
    protected $cursor;

    /**
     * BindataResultSet constructor.
     *
     * @param StorageGridFS $storage
     * @param Cursor  $cursor
     */
    function __construct($storage, $cursor)
    {
        $this->storage = $storage;
        $this->cursor  = $cursor;
    }


    /**
     * Retrieve an external iterator
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return Traversable An instance of an object implementing <b>Iterator</b> or
     * <b>Traversable</b>
     * @since 5.0.0
     */
    function getIterator()
    {
        /** @var BindataEntity $entity */
        foreach ($this->cursor as $entity) {
            # Check Whether BinData is Associated To File??
            if ( $this->_isFile($entity) )
                // Retrieve File From Storage
                $entity = $this->_loadFileIntoBinData($entity);

            yield $entity;
        }
    }


    // ..

    /**
     * Check Weather Given Entity Is File?
     *
     * @param iBindata $binData
     *
     * @return bool
     */
    private function _isFile(iBindata $binData)
    {
        return (
            $binData->getMeta()->has('is_file')
            || $binData->getContent() instanceof UploadedFileInterface
            || $binData->getContent() instanceof DownloadFileInterface
        );
    }

    /**
     * @param BindataEntity $binDataEntity
     * @return BindataEntity
     */
    private function _loadFileIntoBinData($binDataEntity)
    {
        // Document From Bindata persist
        // will contains content.__storage_id
        // open file from storage with given id
        $content  = $binDataEntity->getContent();
        $resource = $this->storage->open($content['__storage_id']);

        // Determine the file is retrieved from storage itself
        $content = new DownloadFile(array(
            'stream' => $resource,
            'name'   => $binDataEntity->getMeta()->get('filename'),
            'type'   => $binDataEntity->getMimeType(),
            'size'   => $binDataEntity->getMeta()->get('filesize'),
            'error'  => 0,
        ));


        $binDataEntity->setContent($content);
        return $binDataEntity;
    }
}
