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

// See: http://www.firephp.org/HQ/Use.htm
class MSCL_WordpressLogging {
  private function __construct() { }

  public static function is_logging_available() {
    // NOTE: We need to check for both functions ('current_user_can()' alone is not enough)!
    if (!function_exists('current_user_can') || !function_exists('wp_get_current_user')) {
      // Wordpress isn't loaded. Enable logging (special circumstances).
      return true;
    }

    // Wordpress is loaded - check for user being admin; only admin is allowed to receive logs
    return current_user_can('manage_options');
  }

  private static function create_instance() {
    require_once(dirname(__FILE__).'/FirePHP.class.php');

    return FirePHP::getInstance(true);
  }

  public static function get_instance() {
    static $instance = null;
    static $mock_instance = null;

    if ($instance === null) {
      $instance = self::create_instance();
      $mock_instance = new MSCL_WordpressLogging();
    }

    if (self::is_logging_available()) {
      return $instance;
    }
    else {
      return $mock_instance;
    }
  }

  // mock functions
  public function error($obj, $label) { }
  public function warn($obj, $label) { }
  public function info($obj, $label) { }
  public function log($obj, $label) { }
}

function console($obj, $label = null) {
  MSCL_WordpressLogging::get_instance()->log($obj, $label);
}

function log_error($obj, $label = null) {
  MSCL_WordpressLogging::get_instance()->error($obj, $label);
}

function log_warn($obj, $label = null) {
  MSCL_WordpressLogging::get_instance()->warn($obj, $label);
}

function log_info($obj, $label = null) {
  MSCL_WordpressLogging::get_instance()->info($obj, $label);
}
?>
