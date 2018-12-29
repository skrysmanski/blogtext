<?php
namespace MSCL\FileInfo;

/**
 * Indicates that the specified file doesn't exist.
 */
class FileNotFoundException extends FileInfoIOException
{
    /**
     * Constructor.
     *
     * @param string $filePath     the affected file
     * @param bool   $isRemoteFile whether the affected file is a remote file
     */
    public function  __construct($filePath, $isRemoteFile)
    {
        parent::__construct('File could not be found', $filePath, $isRemoteFile);
    }
}
