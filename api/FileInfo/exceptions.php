<?php
#########################################################################################
#
# Copyright 2010-2014  Maya Studios (http://www.mayastudios.com)
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
#########################################################################################


/**
 * Indicates an error in any of the media info classes.
 */
class MSCL_MediaInfoException extends Exception
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
   * @param string $message  the message
   * @param string $filePath  the affected file
   * @param bool $isRemoteFile  whether the affected file is a remote file
   */
  public function  __construct($message, $filePath, $isRemoteFile)
  {
    parent::__construct($message.' ['.$filePath.']');
    $this->m_filePath = (string)$filePath;
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

/**
 * Indicates an I/O error, usually meaning that the file could not be read.
 */
class MSCL_MediaFileIOException extends MSCL_MediaInfoException
{
  /**
   * Constructor.
   *
   * @param string $message  the message
   * @param string $filePath  the affected file
   * @param bool $isRemoteFile  whether the affected file is a remote file
   */
  public function  __construct($message, $filePath, $isRemoteFile)
  {
    parent::__construct($message, $filePath, $isRemoteFile);
  }
}

/**
 * Indicates that the specified file doesn't exist.
 */
class MSCL_MediaFileNotFoundException extends MSCL_MediaFileIOException
{
  /**
   * Constructor.
   *
   * @param string $filePath  the affected file
   * @param bool $isRemoteFile  whether the affected file is a remote file
   */
  public function  __construct($filePath, $isRemoteFile)
  {
    parent::__construct('File could not be found', $filePath, $isRemoteFile);
  }
}

/**
 * Indicates that the media file's file format could not be determined or that the data was invalid.
 */
class MSCL_MediaFileFormatException extends MSCL_MediaInfoException
{
  /**
   * Constructor.
   *
   * @param string $message  the message
   * @param string $filePath  the affected file
   * @param bool $isRemoteFile  whether the affected file is a remote file
   */
  public function  __construct($message, $filePath, $isRemoteFile)
  {
    parent::__construct($message, $filePath, $isRemoteFile);
  }
}
