<?php
require_once(dirname(__FILE__) . '/../../api/commons.php');

interface IInterlinkMacro
{
    /**
     * Returns the names of all prefixes handled by this resolver.
     *
     * @return array the prefixed (as array of strings)
     */
    public function get_handled_prefixes();

    public function handle_macro($link_resolver, $prefix, $params, $generate_html, $after_text);
}
