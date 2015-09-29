<?php
require_once(dirname(__FILE__) . '/../../api/commons.php');

/**
 * Represents a BlogText short code (i.e. something like <c>[[some_prefix]]</c>).
 * They work like Wordpress' short codes, just with two brackets instead of just one.
 */
interface IMacroShortCode
{
    /**
     * Returns the names of all prefixes handled by this resolver.
     *
     * @return array the prefixed (as array of strings)
     */
    public function get_handled_prefixes();

    public function handle_macro($link_resolver, $prefix, $params, $generate_html, $after_text);
}
