<?php
namespace Module\TenderBin\Storage;

use Module\TenderBin\Interfaces\DownloadFileInterface;
use MongoDB\BSON\ObjectID;
use MongoDB\GridFS\Exception\FileNotFoundException;
use Poirot\Stream\Interfaces\iStreamable;
use Poirot\Stream\Psr\StreamBridgeInPsr;
use Poirot\Stream\ResourceStream;
use Poirot\Stream\Streamable;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;


class StorageGridFS
{
    /**
     * StorageGridFS constructor.
     *
     * @param \MongoDB\Database $gateWay
     */
    function __construct(\MongoDB\Database $gateWay)
    {
        $this->gateway = $gateWay;
    }

    /**
     * Write Stream File To GridFS
     *
     * @param UploadedFileInterface|DownloadFileInterface|iStreamable|StreamInterface $content
     *
     * @return FileEntity
     * @throws \Exception
     */
    function write($content)
    {
        if ($content instanceof UploadedFileInterface) {
            if ($err = $content->getError())
                throw new \Exception(sprintf(
                    'Uploaded File has error (%s).'
                    , \Poirot\Psr7\getUploadErrorMessageFromCode($err)
                ));

            $content = $content->getStream();

        } elseif ($content instanceof DownloadFileInterface)
            $content = $content->getStream();

        if (!$content instanceof StreamInterface && !$content instanceof iStreamable)
            $content = new Streamable\STemporary((string) $content);

        if ($content instanceof iStreamable)
            $content = new StreamBridgeInPsr($content);


        if ($content->isSeekable())
            $content->rewind();


        # Store file in storage

        $gridFS   = $this->gateway->selectGridFSBucket();

        $filename  = date("Y-m-d_His_").uniqid();
        $storageID = new ObjectID;
        $uploadStream = $gridFS->openUploadStream(
            $filename
            , array('_id' => $storageID)
        );

        $streamGrid = new Streamable( new ResourceStream($uploadStream) );
        $size       = 0;
        while (! $content->eof() ) {
            $buff = $content->read(2097152);
            $streamGrid->write($buff);
            $size += $streamGrid->getTransCount();
        }

        $streamGrid->resource()->close(); // Close upload stream and save file

        /* {
            _id:
            chunkSize:
            filename:
            length:
            md5:
            uploadDate:
        }*/
        $fileDocument = $gridFS->findOne(['_id' => $storageID]);
        $fileEntity   = new FileEntity($fileDocument);

        return $fileEntity;
    }

    /**
     * Open File Stream With Given ID
     *
     * @param ObjectID $storageId
     *
     * @return resource
     */
    function open(ObjectID $storageId)
    {
        $gridFS   = $this->gateway->selectGridFSBucket();
        try {
            $res      = $gridFS->openDownloadStream($storageId);
        } catch (\MongoDB\GridFS\Exception\FileNotFoundException $e) {
            // New Tagged Version Of File May Deleted The Resource.
            throw new \RuntimeException('File Not Found.');
        }

        return $res;
    }

    /**
     * Delete Given Object ID
     *
     * @param ObjectID $storageId
     *
     * @return bool
     * @throws \Exception
     */
    function delete(ObjectID $storageId)
    {
        $gridFS  = $this->gateway->selectGridFSBucket();

        try {
            $gridFS->delete($storageId);
        } catch (FileNotFoundException $e) {
            return false;
        }

        return true;
    }

    /**
     * Attain Storage ID From Given Stream
     *
     * @param resource $resStream
     *
     * @return ObjectID
     */
    function getFileIDFromStream($resStream)
    {
        $gridFS  = $this->gateway->selectGridFSBucket();
        return $gridFS->getFileIdForStream( $resStream );
    }

    /**
     * Get File Document
     *
     * @param resource $resStream
     *
     * @return FileEntity
     */
    function getFileDocument($resStream)
    {
        $r = $this->gateway->selectGridFSBucket()
            ->getFileDocumentForStream($resStream);

        $fileEntity = new FileEntity($r);
        return $fileEntity;
    }
}
