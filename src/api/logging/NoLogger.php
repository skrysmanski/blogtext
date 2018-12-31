<?php

/**
 * Does not log anything.
 */
class MSCL_NoLogger
{
    // mock functions
    public function on_wordpress_loaded() { }
    public function error($obj, $label) { }
    public function warn($obj, $label) { }
    public function info($obj, $label) { }
    public function log($obj, $label) { }
}
