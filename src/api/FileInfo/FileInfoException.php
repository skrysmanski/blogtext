<?php
namespace MSCL\FileInfo;

/**
 * Indicates an error in any of the media info classes.
 */
class FileInfoException extends \Exception
{
    /**
     * @var string
     */
    private $m_filePath;
    /**
     * @var bool
     */
    private $m_isRemoteFile;

    /**
     * Constructor.
     *
     * @param string $message      the message
     * @param string $filePath     the affected file
     * @param bool   $isRemoteFile whether the affected file is a remote file
     */
    public function  __construct($message, $filePath, $isRemoteFile)
    {
        parent::__construct($message . ' [' . $filePath . ']');
        $this->m_filePath     = (string) $filePath;
        $this->m_isRemoteFile = $isRemoteFile;
    }

    /**
     * Returns the file path (URL or local path) of the affected file.
     * @return string
     */
    public function getFilePath()
    {
        return $this->m_filePath;
    }

    /**
     * Whether the affect file is a remote file.
     * @return bool
     */
    public function isRemoteFile()
    {
        return $this->m_isRemoteFile;
    }
}
