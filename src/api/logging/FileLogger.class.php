<?php
require_once(dirname(__FILE__).'/logging-api.php');

/**
 * Logs to a file.
 */
class MSCL_FileLogger
{
    private $file;

    public function __construct($file)
    {
        $this->file = $file;
        $this->write_line("\n\n----------------------------------------------------\n");
    }

    private function write_line($text)
    {
        $fh = fopen($this->file, 'a');
        if (!$fh) {
            return;
        }
        fwrite($fh, $text."\n");
        fclose($fh);
    }

    public function error($obj, $label)
    {
        $this->log($obj, '[ERR] '.$label);
        MSCL_Logging::get_instance(false)->error($obj, $label);
    }

    public function warn($obj, $label)
    {
        $this->log($obj, '[WARN] '.$label);
        MSCL_Logging::get_instance(false)->warn($obj, $label);
    }

    public function info($obj, $label)
    {
        $this->log($obj, '[INFO] '.$label);
        MSCL_Logging::get_instance(false)->info($obj, $label);
    }

    public function log($obj, $label)
    {
        $this->write_line($label.': '.print_r($obj, true));
        MSCL_Logging::get_instance(false)->log($obj, $label);
    }
}
