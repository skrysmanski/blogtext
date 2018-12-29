<?php
namespace MSCL\FileInfo;

/**
 * Just provides basic information about the file.
 */
class BasicFileInfo extends AbstractFileInfo
{
    const CLASS_NAME = 'BasicFileInfo';

    protected function  __construct($filePath, $cacheDate = null)
    {
        parent::__construct($filePath, $cacheDate);
    }

    protected function finishInitialization() { }

    protected function processHeaderData($data)
    {
        return true;
    }

    public static function getInstance($filePath, $cacheDate = null)
    {
        $info = self::getCachedRemoteFileInfo($filePath, self::CLASS_NAME);
        if ($info === null)
        {
            $info = new BasicFileInfo($filePath, $cacheDate);
        }

        return $info;
    }
}
