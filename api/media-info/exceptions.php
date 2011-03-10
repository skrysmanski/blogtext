<?php
#########################################################################################
#
# Copyright 2010-2011  Maya Studios (http://www.mayastudios.com)
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
class MSCL_MediaInfoException extends Exception {
  private $orig_msg;
  private $file_path;
  private $is_remote_file;

  public function  __construct($message, $file_path, $is_remote_file) {
    parent::__construct($message.' ['.$file_path.']');
    $this->orig_msg = $message;
    $this->file_path = (string)$file_path;
    $this->is_remote_file = $is_remote_file;
  }

  /**
   * Returns the file path (URL or local path) of the affected file.
   * @return string
   */
  public function get_file_path() {
    return $this->file_path;
  }

  public function is_remote_file() {
    return $this->is_remote_file;
  }
}

/**
 * Indicates an I/O error, usually meaning that the file could not be read.
 */
class MSCL_MediaFileIOException extends MSCL_MediaInfoException {
  public function  __construct($message, $file_path, $is_remote_file) {
    parent::__construct($message, $file_path, $is_remote_file);
  }
}

/**
 * Indicates that the specified file doesn't exist.
 */
class MSCL_MediaFileNotFoundException extends MSCL_MediaFileIOException {
  public function  __construct($file_path, $is_remote_file) {
    parent::__construct('File could not be found', $file_path, $is_remote_file);
  }
}

/**
 * Indicates that the media file's file format could not be determined or that the data was invalid.
 */
class MSCL_MediaFileFormatException extends MSCL_MediaInfoException {
  public function  __construct($message, $file_path, $is_remote_file) {
    parent::__construct($message, $file_path, $is_remote_file);
  }
}

?>
