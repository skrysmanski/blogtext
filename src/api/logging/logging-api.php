<?php
class MSCL_Logging
{
    private static $file_logger = null;

    private function __construct() { }

    public static function is_logging_available()
    {
        // NOTE: We need to check for both functions ('current_user_can()' alone is not enough)!
        if (!function_exists('current_user_can') || !function_exists('wp_get_current_user')) {
            // Wordpress isn't loaded. Enable logging (special circumstances).
            return true;
        }

        // Wordpress is loaded - check for user being admin; only admin is allowed to receive logs
        return current_user_can('manage_options');
    }

    private static function create_instance()
    {
        require_once(dirname(__FILE__) . '/ConsoleLogger.class.php');
        return new MSCL_ConsoleLogger();
    }

    public static function enable_file_logging($logfile)
    {
        if (self::$file_logger === null)
        {
            require_once(dirname(__FILE__).'/FileLogger.class.php');
            self::$file_logger = new MSCL_FileLogger($logfile);
        }
    }

    /**
     * Must be called once Wordpress is loaded. Can be called multiple times.
     */
    public static function on_wordpress_loaded()
    {
        self::get_instance(false)->on_wordpress_loaded();
    }

    public static function get_instance($allow_file_logger = true)
    {
        static $instance = null;
        static $mock_instance = null;

        if ($instance === null)
        {
            $instance = self::create_instance();
            require_once(dirname(__FILE__).'/NoLogger.class.php');
            $mock_instance = new MSCL_NoLogger();
        }

        if ($allow_file_logger && self::$file_logger != null)
        {
            return self::$file_logger;
        }

        if (self::is_logging_available())
        {
            return $instance;
        }
        else
        {
            return $mock_instance;
        }
    }
}

function console($obj, $label = null)
{
    MSCL_Logging::get_instance()->log($obj, $label);
}

function log_exception($message)
{
    log_error(debug_backtrace(), $message);
}

function log_error($obj, $label = null)
{
    MSCL_Logging::get_instance()->error($obj, $label);
}

function log_warn($obj, $label = null)
{
    MSCL_Logging::get_instance()->warn($obj, $label);
}

function log_info($obj, $label = null)
{
    MSCL_Logging::get_instance()->info($obj, $label);
}

function log_stacktrace()
{
    log_info(MSCL_ErrorHandling::format_stacktrace(debug_backtrace(), 1, true));
}
