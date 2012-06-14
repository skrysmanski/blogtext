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


require_once(dirname(__FILE__).'/../commons.php');
MSCL_require_once('exceptions.php', __FILE__);

abstract class MSCL_AbstractFileInfo {
  const name = 'MSCL_AbstractFileInfo';

  private static $cached_remote_file_info = array();

  private $file_path;
  private $is_remote_file;
  private $file_size = null;
  private $last_modified_date;

  private $data = '';
  private $downloaded_data_size = 0;

  private $http_status_code = null;
  private $done = false;

  protected function __construct($file_path, $cache_date=null) {
    $this->file_path = $file_path;
    $this->is_remote_file = $this->is_remote_file($file_path);
    if ($this->is_remote_file) {
      // remote file
      if (!self::is_remote_support_available()) {
        throw new MSCL_MediaFileIOException('Remote support is unavailable (CURL is not installed)', $file_path, true);
      }
      $this->open_remote_file($cache_date);
    } else {
      if (!file_exists($file_path)) {
        throw new MSCL_MediaFileNotFoundException($file_path, false);
      }
      $this->open_local_file($cache_date);
    }

    $this->finish_initialization();

    // Everything worked out. Store this information.
    // IMPORTANT: We need to pass "$this" as otherwise the name will always be 'MSCL_AbstractFileInfo'.
    $class_name = get_class($this);
    if (array_key_exists($file_path, self::$cached_remote_file_info)) {
      self::$cached_remote_file_info[$file_path][$class_name] =& $this;
    } else {
      self::$cached_remote_file_info[$file_path] = array($class_name => &$this);
    }
  }

  protected abstract function finish_initialization();

  /**
   * Returns whether remote file info is supported. This required cURL being installed.
   * @return bool
   */
  public static function is_remote_support_available() {
    static $is_supported = null;
    if ($is_supported === null) {
      $is_supported = function_exists('curl_init');
    }
    return $is_supported;
  }

  /**
   * Checks whether the specified protocol (eg. "ftp", "http", "https", ...) is supported. Note that
   * @see is_remote_support_enabled() must return "true" for this method to work.
   *
   * @param string $url the url for which the protocol to be checked. Alternatively the protocol can be passed
   *   directly (ie. the part before "://").
   * @return bool
   */
  public static function is_protocol_supported($url) {
    static $supported_protocols = null;

    $url_parts = explode('://', $url, 2);
    if (count($url_parts) == 2) {
      $protocol = $url_parts[0];
    } else {
      $protocol = $url;
    }
    
    if ($protocol == 'file') {
      return true;
    }

    if ($supported_protocols === null) {
      if (!self::is_remote_support_available()) {
        throw new Exception("Remote file info is not supported on this system.");
      }
      $info = curl_version();
      if (isset($info['protocols'])) {
        $supported_protocols = array_flip($info['protocols']);
      } else {
        // Should never happen
        throw new Exception("Could not determine supported cURL protocols.");
      }
    }

    return isset($supported_protocols[$protocol]);
  }

  /**
   * Returns the installed cURL version.Note that @see is_remote_support_enabled() must return "true" for
   * this method to work.
   *
   * @param bool $as_string if "true", the version will be returned as string (eg. "7.20.0"); if "false", the
   *   version will be returned as 24-bit integer (eg. for 7.20.0 this is 463872).
   *
   * @return string|int
   */
  public static function get_curl_version($as_string=true) {
    static $version_int = null;
    static $version_str = null;
    if ($version_int === null) {
      if (!self::is_remote_support_available()) {
        throw new Exception("Remote file info is not supported on this system.");
      }
      $info = curl_version();
      if (isset($info['version_number']) && isset($info['version'])) {
        $version_int = $info['version_number'];
        $version_str = $info['version'];
      } else {
        // Should never happen
        throw new Exception("Could not determine cURL version.");
      }
    }

    return ($as_string ? $version_str : $version_int);
  }

  public static function get_cached_remote_file_info($file_path, $class_name) {
    if (!array_key_exists($file_path, self::$cached_remote_file_info)) {
      return null;
    }

    $file_info = &self::$cached_remote_file_info[$file_path];
    
    if (!array_key_exists($class_name, $file_info)) {
      return null;
    }

    return $file_info[$class_name];
  }

  /**
   * Returns the path to the image (either an url or a file path).
   */
  public function get_file_path() {
    return $this->file_path;
  }

  /**
   * Returns whether the file is a remote file. This method can be called on an instance or statically. When
   * called statically, the file must be specified as parameter.
   *
   * @param string $file_path the path to check or null, if called from an instance
   *
   * @return bool
   */
  public function is_remote_file($file_path=null) {
    if ($file_path === null) {
      return $this->is_remote_file;
    } else {
      $found = preg_match('/^([a-zA-Z0-9\+\.\-]+)\:\/\/.+/', $file_path, $matches);
      if (!$found) {
        return false;
      }

      return ($matches[1] != 'file');
    }
  }

  /**
   * Last modification date of the file. May be "null" for remote files when the server doesn't report this
   * information.
   * @return int|null seconds since Linux epoc or "null"
   */
  public function get_last_modified_date() {
    return $this->last_modified_date;
  }

  /**
   * Returns the file's size in bytes. May be "null" for remote files when the server doesn't report the
   * file's size.
   * @return int|null
   */
  public function get_file_size() {
    return $this->file_size;
  }

  /**
   * Returns the number of bytes downloaded to determine this image's info. Just for information purposes.
   * @return int
   */
  public function get_downloaded_data_size() {
    return $this->downloaded_data_size;
  }

  /**
   * Returns the content of the specified file. If it's a remote file, the file is being downloaded.
   *
   * @param string $file_path the file
   *
   * @return string
   */
  public static function get_file_contents($file_path) {
    $is_remote = self::is_remote_file($file_path);
    if ($is_remote) {
      if (!self::is_remote_support_available()) {
        throw new MSCL_MediaFileIOException('Remote support is unavailable (CURL is not installed)', $file_path, true);
      }

      $ch = self::prepare_curl_handle($file_path, null);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

      $result = curl_exec($ch);
      if ($result === false) {
        self::handle_curl_error($ch, $file_path);
      }

      $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      switch ($status_code) {
        case 200: // OK
          break;
        case 404:
          throw new MSCL_MediaFileNotFoundException($file_path, true);
        default:
          throw new MSCL_MediaFileIOException("Invalid HTTP status code: ".$status_code, $file_path, true);
      }

      curl_close($ch);
    } else {
      if (!is_file($file_path)) {
        throw new MSCL_MediaFileNotFoundException($file_path, false);
      }
      $result = file_get_contents($file_path);
    }

    return $result;
  }

  private static function prepare_curl_handle($file_path, $cache_date) {
    $ch = curl_init($file_path);
    if ($cache_date !== null) {
      // The date needs to be formatted as RFC 1123, eg. "Sun, 06 Nov 1994 08:49:37 GMT"
      // See: http://www.w3.org/Protocols/rfc2616/rfc2616-sec3.html#sec3.3.1
      // NOTE: We can't use "DATE_RFC1123" here, as this won't produce the correct timezone (will produce
      //   "+0000" instead of "GMT").
      curl_setopt($ch, CURLOPT_HTTPHEADER,
                  array('If-Modified-Since: '.gmdate('D, d M Y H:i:s \G\M\T', $cache_date))
                 );
    }
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
    // NOTE: This option can't be enabled in safe mode. But that's not a big problem, since most files won't
    //  have a redirect.
    @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);

    // Fix some problems with some broken IPv6 installations and self-signed SSL certs
    // See: http://bugs.php.net/47739
    if (defined('CURLOPT_IPRESOLVE')) {
      // Only in PHP 5.3
      curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    }
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    return $ch;
  }

  private static function handle_curl_error($ch, $file_path) {
    $error_number = curl_errno($ch);
    if ($error_number == 60 || $error_number == 6) {
      // Treat "domain not found" (6) and "no route to host" (60) as file not found
      throw new MSCL_MediaFileNotFoundException($file_path, true);
    }
    throw new MSCL_MediaInfoException("Could not execute cURL request. Reason: ".curl_error($ch).' ['.$error_number.']',
                                      $file_path, true);
  }
  
  private function open_remote_file($cache_date) {
    $ch = self::prepare_curl_handle($this->file_path, $cache_date);
    // attempt to retrieve the modification date
    curl_setopt($ch, CURLOPT_FILETIME, true);

    curl_setopt($ch, CURLOPT_WRITEFUNCTION, array($this, 'curl_callback'));

    // NOTE: We need to check "$this->done" here, because when returning "-1" in "curl_callback()",
    //   "curl_exec()" will return "false".
    if (@curl_exec($ch) === false && $this->done === false) {
      self::handle_curl_error($ch, $this->file_path);
    }

    if ($this->http_status_code === null) {
      // For certain status codes the write callback isn't used but "curl_exec()" returns directly. In this
      // case the status code hasn't been checked. The code 304 NOT MODIFIED is such an example.
      $this->http_status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      if ($this->http_status_code == 304 && $cache_date !== null) {
        throw new MSCL_NotModifiedNotification();
      }
    }

    $this->last_modified_date = curl_getinfo($ch, CURLINFO_FILETIME);
    if ($this->last_modified_date == -1) {
      $this->last_modified_date = null;
    }

    $this->file_size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    if ($this->file_size == 0) {
      $this->file_size = null;
    }

    curl_close($ch);
    // NOTE: Handling failure of check_data is done in the constructor.
  }

  private function curl_callback($ch, $chunk) {
    if ($this->http_status_code === null) {
      $this->http_status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      // see: http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
      switch($this->http_status_code) {
        case 200: // OK
          break;

        case 304: // NOT MODIFIED
          // NOTE: We usually don't get here as this method isn't called if the 304 status is returned.
          //  But to be on the safe side ...
          throw new MSCL_NotModifiedNotification();

        case 404:
          throw new MSCL_MediaFileNotFoundException($this->file_path, true);

        default:
          throw new MSCL_MediaFileIOException("Invalid HTTP status code: ".$this->http_status_code, $this->file_path, true);
      }
    }

    if ($this->handle_data_prep($chunk)) {
      // we're done; break curl connection
      $this->done = true;
      return -1;
    }

    return strlen($chunk);
  }

  private function open_local_file($cache_date) {
    $mod_date = @filemtime($this->file_path);
    if ($mod_date === false) {
      throw new MSCL_MediaFileIOException("Could not determine file modification date.", $this->file_path, false);
    }
    if ($cache_date !== null && $cache_date >= $mod_date) {
      throw new MSCL_NotModifiedNotification();
    }
    $this->last_modified_date = $mod_date;

    $this->file_size = filesize($this->file_path);
    if ($this->file_size === false) {
      throw new MSCL_MediaFileIOException("Could not determine file size.", $this->file_path, false);
    }

    $file_handle = @fopen($this->file_path, 'rb');
    if ($file_handle === false) {
      throw new MSCL_MediaFileIOException("Could not open file.", $this->file_path, false);
    }
    while (!feof($file_handle)) {
      $chunk = fread($file_handle, 2048);
      if ($chunk === false) {
        throw new MSCL_MediaFileIOException("Could not read file.", $this->file_path, false);
      }
      if ($this->handle_data_prep($chunk)) {
        break;
      }
    }
    fclose($file_handle);
    // NOTE: Handling failure of check_data is done in the constructor.
  }

  private function handle_data_prep($chunk) {
    $this->data .= $chunk;

    if (!$this->handle_data($this->data)) {
      return false;
    }

    $this->downloaded_data_size = strlen($this->data);
    // free data
    $this->data = null;
    return true;
  }

  /**
   * Handles the data.
   *
   * @param string $data the data which has already been downloaded
   *
   * @return bool returns "true" if enought data has been processed and no more data needs to be
   *   read/downloaded. If this returns "false", more data will be read/downloaded.
   */
  protected abstract function handle_data($data);

  ////////////////////////////////////////////////////////////////////////////////////////
  //
  // Helper functions
  //

  protected static function starts_with($str, $with, $offset=0) {
    return (substr($str, $offset, strlen($with)) == $with);
  }

  protected static function unpack_short($data, $pos, $use_big_endian=true) {
    // NOTE: (int)$data parses the character while ord($data) converts it.
    if ($use_big_endian) {
      return ord($data[$pos]) * 0x100 + ord($data[$pos + 1]);
    } else {
      return ord($data[$pos]) + ord($data[$pos + 1]) * 0x100;
    }
  }

  protected static function unpack_int($data, $pos, $use_big_endian=true) {
    // NOTE: (int)$data parses the character while ord($data) converts it.
    if ($use_big_endian) {
      return ord($data[$pos]) * 0x1000000 + ord($data[$pos + 1]) * 0x10000
           + ord($data[$pos + 2]) * 0x100 + ord($data[$pos + 3]);
    } else {
      return ord($data[$pos]) + ord($data[$pos + 1]) * 0x100
           + ord($data[$pos + 2]) * 0x10000 + ord($data[$pos + 3]) * 0x10000;
    }
  }
}

/**
 * Just provides basic information about the file.
 */
class MSCL_SimpleFileInfo extends MSCL_AbstractFileInfo {
  const name = 'MSCL_SimpleFileInfo';

  protected function  __construct($file_path, $cache_date = null) {
    parent::__construct($file_path, $cache_date);
  }

  protected function finish_initialization() { }

  protected function handle_data($data) {
    return true;
  }

  public static function get_instance($file_path, $cache_date=null) {
    $info = self::get_cached_remote_file_info($file_path, self::name);
    if ($info === null) {
      $info = new MSCL_SimpleFileInfo($file_path, $cache_date);
    }
    return $info;
  }
}

/**
 * This "notification" is thrown by any media info class, if the specified file hasn't been modified and
 * its cached counterpart is still valid.
 */
class MSCL_NotModifiedNotification extends Exception {
  // This class is a "hack" but it's the easiest way to handle this situation.
}
?>
