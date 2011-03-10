<?php
#########################################################################################
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


MSCL_Api::load(MSCL_Api::USER_API);

class MSCL_WordpressLogging {
  const DATABASE_SCHEMA_VERSION = '1.0';
  const SYS_LEVEL = 'SYS';
  const ERROR_LEVEL = 'ERROR';
  const WARN_LEVEL = 'WARN';
  const INFO_LEVEL = 'INFO';
  const DEBUG_LEVEL = 'DEBUG';
  
  private $table_name;

  private function __construct() {
    global $wpdb;

    if (!isset($wpdb)) {
      throw new Exception("Wordpress database not initialized.");
    }

    $this->table_name = $wpdb->prefix.'logging';
    $this->create_table();
  }

  public static function is_logging_available() {
    global $wpdb;
    return isset($wpdb);
  }

  public static function get_instance() {
    static $instance = null;
    if ($instance === null) {
      $instance = new MSCL_WordpressLogging();
    }
    return $instance;
  }

  private function create_table() {
    // see: http://codex.wordpress.org/Creating_Tables_with_Plugins
    global $wpdb;
    if($wpdb->get_var("SHOW TABLES LIKE '$this->table_name'") != $this->table_name) {
      // create table
      // IMPORTANT: There must be 2 spaces after "PRIMARY KEY"!!!
      // NOTE: We give at lot of space for the log message, so that backtraces can be logged (which are
      //   usually very long). The "text" type gives up to ~ 65000 characters (16 bit length).
      $sql = "CREATE TABLE $this->table_name (
              id bigint(20) NOT NULL AUTO_INCREMENT,
              date datetime NOT NULL,
              level char(5) NOT NULL,
              source varchar(100) NOT NULL,
              message text NOT NULL,
              PRIMARY KEY  (id),
              KEY date (date)
            );";

      require_once(ABSPATH.'wp-admin/includes/upgrade.php');
      dbDelta($sql);

      add_option('wordpress_logging_version', self::DATABASE_SCHEMA_VERSION);

      $this->log(self::SYS_LEVEL, null, 'Logging table created.');
    }
  }

  public function log($level, $source, $message) {
    switch ($level) {
      case self::SYS_LEVEL:
      case self::ERROR_LEVEL:
      case self::WARN_LEVEL:
      case self::INFO_LEVEL:
      case self::DEBUG_LEVEL:
        break;
      default:
        throw new Exception("Invalid log level.");
    }

    //
    // Determine source
    //
    if ($source === null) {
      $source = 0;
    }

    if (is_int($source)) {
      $frame_nr = max(0, $source) + 1;
      $source = 'unknown';
      // automatically determine source
      $stack_trace = debug_backtrace();
      if (count($stack_trace) > $frame_nr) {
        $stack_frame = $stack_trace[$frame_nr];
        // NOTE: File name isn't set for callback functions; see: http://bugs.php.net/bug.php?id=44428
        if (isset($stack_frame['file'])) {
          // NOTE: The filename may either be a single-file plugin (eg. "myplugin.php") or a directory based
          //   plugin file (eg. "myplugin/myplugin-file.php").
          $filename = plugin_basename($stack_frame['file']);
          $pos = strpos($filename, '/');
          if ($pos !== false) {
            // directory base
            $plugin_name = substr($filename, 0, $pos);
          } else {
            // single file - remove ".php"
            $plugin_name = substr($filename, 0, -4);
          }
          $filename = basename($filename);
          $source = "[$plugin_name] $filename";
          if (isset($stack_frame['line'])) {
            $source .= ':'.$stack_frame['line'];
          }
        } else {
          // callback function without filename
          $source = '[callback] '.@$stack_frame['class'].@$stack_frame['type'].$stack_frame['function'].'()';
        }
      }
    }

    // Fix message (in case of array, object, ...)
    $message = print_r($message, true);

    if ($level != self::DEBUG_LEVEL) {
      // Don't log debug level messages to the data base
      global $wpdb;
      $wpdb->insert($this->table_name, array('date' => current_time('mysql'),
                                             'level' => $level,
                                             'source' => $source,
                                             'message' => $message) );
    }

    //
    // Log message directly to the user's browser - but only if he/she is an admin
    // Uses the "wordpress logger" plugin. See:
    //   http://www.turingtarpit.com/2009/05/wordpress-logger-a-plugin-to-display-php-log-messages-in-safari-and-firefox/
    //
    global $wplogger;
    if (isset($wplogger) && MSCL_UserApi::can_manage_options(false)) {
      switch ($level) {
        case self::ERROR_LEVEL:
          $wp_log_level = WPLOG_ERR;
          break;
        case self::WARN_LEVEL:
          $wp_log_level = WPLOG_WARNING;
          break;
        case self::DEBUG_LEVEL:
          $wp_log_level = WPLOG_DEBUG;
          break;
        default:
          // NOTE: This includes INFO and SYS (and all other level we forgot here ;) )
          $wp_log_level = WPLOG_INFO;
          break;
      }
      $wplogger->log($source.' - '.$message, $wp_log_level);
    }
  }
}

if (MSCL_WordpressLogging::is_logging_available()) {
  // Make sure the database table is created here and not when the first logging is to be happen.
  MSCL_WordpressLogging::get_instance();
}

/**
 * Checks whether logging is available. Logging is only not available, if Wordpress hasn't been loaded.
 */
function is_logging_available() {
  return MSCL_WordpressLogging::is_logging_available();
}

/**
 * Logs an error.
 *
 * @param string $message the error message
 */
function log_error($message) {
  MSCL_WordpressLogging::get_instance()->log(MSCL_WordpressLogging::ERROR_LEVEL, 0, $message);
}

/**
 * Logs a warning.
 *
 * @param string $message the warning message
 */
function log_warn($message) {
  MSCL_WordpressLogging::get_instance()->log(MSCL_WordpressLogging::WARN_LEVEL, 0, $message);
}

/**
 * Logs an info message.
 *
 * @param string $message the info message
 */
function log_info($message) {
  MSCL_WordpressLogging::get_instance()->log(MSCL_WordpressLogging::INFO_LEVEL, 0, $message);
}

/**
 * Logs a debug message.
 *
 * @param string $message the debug message
 */
function log_debug($message) {
  MSCL_WordpressLogging::get_instance()->log(MSCL_WordpressLogging::DEBUG_LEVEL, 0, $message);
}
?>
