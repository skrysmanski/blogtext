<?php
namespace MSCL\FileInfo;

/**
 * Indicates that the media file's file format could not be determined or that the data was invalid.
 */
class FileInfoFormatException extends FileInfoException
{
    /**
     * Constructor.
     *
     * @param string $message      the message
     * @param string $filePath     the affected file
     * @param bool   $isRemoteFile whether the affected file is a remote file
     */
    public function  __construct($message, $filePath, $isRemoteFile)
    {
        parent::__construct($message, $filePath, $isRemoteFile);
    }
}
