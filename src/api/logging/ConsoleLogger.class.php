<?php

/**
 * Logs message onto the JavaScript console of the user's browser.
 */
class MSCL_ConsoleLogger
{
    // NOTE: We don't use "FirePHP" here because it requires "ob_start()" to be called and Wordpress doesn't do this
    //   automatically. Also, this logger only requires the JavaScript "console" present in most browsers nowadays.

    private $is_output_script_registered = false;

    private $log_messages = array();

    public function on_wordpress_loaded()
    {
        $this->register_script();
    }

    public function error($obj, $label)
    {
        $this->add_log('error', $obj, $label);
    }

    public function warn($obj, $label)
    {
        $this->add_log('warn', $obj, $label);
    }

    public function info($obj, $label)
    {
        $this->add_log('info', $obj, $label);
    }

    public function log($obj, $label)
    {
        $this->add_log('log', $obj, $label);
    }

    public function register_script()
    {
        if (!$this->is_output_script_registered)
        {
            add_action('wp_print_footer_scripts', array($this, 'do_log_output'));
            $this->is_output_script_registered = true;
        }
    }

    // Wordpress Action Callback - do not call directly
    public function do_log_output()
    {
        if (count($this->log_messages) == 0)
        {
            return;
        }

        ?>
<script language="JavaScript">
    if (console) {
<?php
        foreach ($this->log_messages as $message)
        {
            echo 'console.'.$message[0].'("'.str_replace('"', '\\"', $message[1]).'");';
        }
?>
    }
</script>
        <?php
    }

    private function add_log($func_name, $obj, $label)
    {
        if (!$this->is_output_script_registered && function_exists('add_action'))
        {
            $this->register_script();
        }

        $msg = print_r($obj, true);
        if (!empty($label))
        {
            $msg = $label.': '.$msg;
        }
        $this->log_messages[] = array($func_name, $msg);
    }
}
