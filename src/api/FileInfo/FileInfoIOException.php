<?php
namespace MSCL\FileInfo;

/**
 * Indicates an I/O error, usually meaning that the file could not be read.
 */
class FileInfoIOException extends FileInfoException
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
