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


require_once(dirname(__FILE__).'/../api/commons.php');
MSCL_Api::load(MSCL_Api::GESHI);
MSCL_Api::load(MSCL_Api::THUMBNAIL_API);
MSCL_Api::load(MSCL_Api::THUMBNAIL_CACHE);
MSCL_Api::load(MSCL_Api::CACHE_API);

require_once(dirname(__FILE__).'/textmarkup_base.php');
require_once(dirname(__FILE__).'/macros.php');
require_once(dirname(__FILE__).'/resolver.php');


class MarkupException extends Exception {
  public function  __construct($message, $code='', $previous='') {
    parent::__construct($message, $code, $previous);
  }
}

class BlogTextMarkup extends AbstractTextMarkup implements IThumbnailContainer {
  const SINGLE_PAGE_PREFIX = 'single_';
  const LOOP_PAGE_PREFIX = 'loop_';
  const RSS_ITEM_PREFIX = 'rss_';

  const CONTENT_CACHE_PREFIX = 'content_';
  const CONTENT_CACHE_KEY = 'cache_';
  const CONTENT_CACHE_DATE_KEY = 'cache_date_';

  const THUMB_CACHE_PREFIX = 'thumb_check_date';

  const CACHE_PREFIX = 'blogtext_';

  private static $INITIALIZED = false;

  // regular expression rules
  // Syntax:
  // * "\1" : Backreference
  // * "(?:" : non-capturing subpattern (http://www.php.net/manual/en/regexp.reference.subpatterns.php)
  // * "(?<=", "(?<!", "(?=", "(?!" : Assertions (http://www.php.net/manual/en/regexp.reference.assertions.php)
  // * "+?", "*?" : ungreedy versions of "+" and "*" (http://www.php.net/manual/en/regexp.reference.repetition.php)
  // * "(?R)" : recursion
  // * Modifiers: http://php.net/manual/de/reference.pcre.pattern.modifiers.php
  //
  // NOTE: This list is ordered and the order is important.
  private static $RULES = array(
    // heading with optional anchor names
    // NOTE: We need to allow "&#35;" here to allow the user to specify a # sign
    'headings' =>'/^[ \t]*(={1,6})(.*?)(?:(?<!&)#[ \t]*(?![ \t])(.+)(?<![ \t])[ \t]*)?$/m',

    // InterLinks using the [[ ]] syntax
    // NOTE: We don't use just single brackets (ie. [ ]) as this is already use by Wordpress' Shortcode API
    // NOTE: Must run AFTER "headings" and BEFORE the tables, as the tables also use pipes
    // NOTE: Must work with [[...\]]] (resulting in "...\]" being the content
    'interlinks' => '/(?<!\[)\[\[(?!\[)[ \t]*((?:[^\]]|\\\])+)[ \t]*(?<!(?<!\\\\)\\\\)\]\]([[:alpha:]]*(?![[:alpha:]]))/',
    // Interlink without arguments [[[ ]]] (three brackets instead of two)
    // NOTE: For now this must run after "headings" as otherwise the TOC can't be generated (which is done
    //   by this rule.
    'simple_interlinks' => '/\[\[\[([a-zA-Z0-9\-]+)\]\]\]/',

    // complex tables (possibly contained in a list) - MediaWiki syntax
    'complex_table' => '/^\{\|(.*?)(?:^\|\+(.*?))?(^(?:((?R))|.)*?)^\|}/msi',
    // simple tables - Creole syntax
    // NOTE: Need to be done AFTER "complex_tables" as they syntaxes otherwise may collide (eg. on the
    //   table caption)
    'simple_table' => '/\n(\|(?!\+)[^\|]+\|.+(?:\n\|(?!\+)[^\|]+\|.+)*)(?:\n\|\+(.+))?/',
    // Ordered (#) and unordered (*) lists; definition list(;)
    // NOTE: The user can't start a list with "**" (list with sublist).
    'list' => '/\n[\*#;][^\*#;].*?\n(?:(?:[\*#; \t].*?)?\n)*/',
    // Block quotes
    'blockquote' => '/\n>(.*?\n)(?!>)/s',
    // Indention (must be done AFTER lists)
    'indention' => '/\n[ \t]{2,}(.*?\n)(?![ \t]{2})/s',

    // External links (plain text urls)
    'plain_text_urls' => '/(?<=[ \t\n])(([a-zA-Z0-9\+\.\-]+)\:\/\/(\S+))( [[:punct:]])?/',
    // Horizontal lines
    'horizontal' => '/^----[\-]*[ \t]*$/m',

    // Emphasis and bold
    // NOTE: We must check that there's no : before the // in emphasis so that URLs won't be interpreted as
    //   emphasis.
    'bold' => '/(?<!\*)\*\*(.+?)\*\*(?!\*)/',
    'emphasis' => '@(?<![/\:])//(.+?)//(?!/)@',
    // Underline, strike-though, super script, and sub script
    'underline' => '/(?<!_)__(.+?)__(?!_)/',
    'strike_through' => '/(?<!~)~~(.+?)~~(?!~)/',
    'super_script' => '/(?<!\^)\^\^(.+?)\^\^(?!\^)/',
    'sub_script' => '/(?<!,),,(.+?),,(?!,)/',
  );

  // Rules to remove white space at the beginning of line that don't expect this (headings, lists, quotes)
  private static $TRIM_RULE = '/^[ \t]*(?=[=\*#:;>$])/m';

  private static $MARKUP_MODIFICATION_DATE;

  private static $BLOCK_ENCODE_START_DELIM;
  private static $BLOCK_ENCODE_END_DELIM;

  private static $SUPPORTED_GESHI_LANGUAGES;

  private static $interlinks = array();

  private $is_excerpt;

  private $is_rss;

  private $placeholders = array();

  /**
   * This array contains the amount each id has occured in this posting. This is used to alter ids (by
   * appending a number) so that the remain unique. Eg. this will result in "my_id", "my_id_2", "my_id_3", ...
   */
  private $id_suffix = array();
  /**
   * Stores all headings in this post/page.
   * @var <type>
   */
  private $headings = array();

  private $headings_title_map = array();

  /**
   * Contains
   * @var <type>
   */
  private $text_positions = array();

  private $anchor_id_counter = 0;

  private $thumbs_used = array();

  public function __construct() {
    self::static_constructor();
  }

  private static function static_constructor() {
    if (self::$INITIALIZED) {
      return;
    }
    self::$BLOCK_ENCODE_START_DELIM = '('.md5('%%%');
    self::$BLOCK_ENCODE_END_DELIM = md5('%%%').')';

    // geshi
    $geshi = new GeSHi();
    self::$SUPPORTED_GESHI_LANGUAGES = array_flip($geshi->get_supported_languages());

    // modification date
    self::$MARKUP_MODIFICATION_DATE = max(
        BlogTextPlugin::get_instance()->get_plugin_modification_date(array('markup/', 'util.php')),
        MSCL_Api::get_mod_date()
                                         );
    self::$MARKUP_MODIFICATION_DATE = MarkupUtil::create_mysql_date(self::$MARKUP_MODIFICATION_DATE);

    //
    // interlinks
    //

    // Handles regular links to post (ie. without prefix), as well as attachment and Wordpress links (such
    // as categories, tags, blogroll, and archive).
    self::register_interlink_handler(self::$interlinks, new WordpressLinkProvider());

    // let the custom interlinks overwrite the wordpress link provider, but not the media macro.
    self::register_all_interlink_patterns(self::$interlinks);

    // Media macro (images) - load it as the last one (to overwrite any previously created custom interlinks)
    self::register_interlink_handler(self::$interlinks, new MediaMacro());

    self::$INITIALIZED = true;
  }

  private static function get_content_cache() {
    return new MSCL_PersistentObjectCache(self::CACHE_PREFIX.self::CONTENT_CACHE_PREFIX);
  }

  private static function get_post_content_cache($type, $post_id) {
    return new MSCL_PersistentObjectCache(self::CACHE_PREFIX.self::CONTENT_CACHE_PREFIX.$type.$post_id);
  }

  private static function get_thumbnail_last_checked_cache() {
    return new MSCL_PersistentObjectCache(self::CACHE_PREFIX.self::THUMB_CACHE_PREFIX);
  }

  public function convert_post_to_html($post, $markup_content, $render_type, $is_excerpt) {
    if ($render_type != self::RENDER_KIND_PREVIEW) {
      // We need to have two different cached: one for when a post is displayed alone and one when it's
      // displayed together with other posts (in the loop). HTML IDs may vary and if there's a more link the
      // contents differ dramatically. (The same applies for RSS feed item which can be dramatically trimmed
      // down.)
      if ($render_type == self::RENDER_KIND_RSS) {
        $content_cache = self::get_post_content_cache(self::RSS_ITEM_PREFIX, $post->ID);
        $cache_name = 'rss-item';
      } else if ($this->is_single()) {
        $content_cache = self::get_post_content_cache(self::SINGLE_PAGE_PREFIX, $post->ID);
        $cache_name = 'single-page';
      } else {
        $content_cache = self::get_post_content_cache(self::LOOP_PAGE_PREFIX, $post->ID);
        $cache_name = 'loop-view';
      }

      // reuse cached content; significantly speeds up the whole process
      $cached_content = $content_cache->get_value(self::CONTENT_CACHE_KEY);
      $cached_content_date = $content_cache->get_value(self::CONTENT_CACHE_DATE_KEY);
      if (   !empty($cached_content)
          && $cached_content_date >= $post->post_modified_gmt
          && $cached_content_date >= self::$MARKUP_MODIFICATION_DATE
          && $this->check_thumbnails($post)) {
        $cache_comment = '<!-- Cached "'.$cache_name.'" item from '.$cached_content_date." -->\n";
        return $cache_comment.$cached_content;
      }

      if (!$is_excerpt && !$this->is_single()) {
        // For the regular expression, see "get_the_content()" in "post-template.php".
        $is_excerpt = (preg_match('/<!--more(.*?)?-->/', $post->post_content) == 1);
      }
    }

    $this->reset_data($render_type == self::RENDER_KIND_RSS, $is_excerpt);

    // add blank lines for rules that expect a \n at the beginning of a line (even on the first)
    $markup_content = "\n$markup_content\n";
    // clean up line breaks - convert all to "\n"
    $ret = preg_replace('/\r\n|\r/', "\n", $markup_content);
    $ret = $this->encode_no_markup_texts($ret);
    // remove trailing whitespace
    $ret = preg_replace(self::$TRIM_RULE, '', $ret);

    foreach (self::$RULES as $name => $unused) {
      $ret = $this->execute_regex($name, $ret);
    }

    $ret = $this->decode_placeholders($ret);

    if ($render_type != self::RENDER_KIND_PREVIEW) {
      // update cache
      $mod_date = MarkupUtil::create_mysql_date();
      $content_cache->set_value(self::CONTENT_CACHE_DATE_KEY, $mod_date);
      $content_cache->set_value(self::CONTENT_CACHE_KEY, $ret);

      // NOTE: We need to do the check here as well as it may not have been executed in the condition above.
      $this->check_thumbnails($post);

      log_info("Cache for post $post->ID ($cache_name) has been updated.");

      $generate_comment = '<!-- Generated "'.$cache_name.'" item at '.$cached_content_date." -->\n";
    } else {
      $generate_comment = '';
    }
    return $generate_comment.$ret;
  }
  
  /**
   * Clears the page cache completely or only for the specified post.
   * @param int|null $what  if this is "null", the whole cache will be cleared. Otherwise only the cache for
   *   the specified post/page id will be cleared.
   */
  public static function clear_page_cache($what=null) {
    if ($what === null) {
      self::get_content_cache()->clear_cache();
      log_info("The complete page cache has been cleared.");
    } else {
      if (is_numeric($what)) {
        $what = (int)$what;
      } else {
        // Check this so that not arbitrary thing are deleted here
        throw new Exception("Post id must be an integer, but got: ".print_r($what, true));
      }

      self::get_post_content_cache(self::SINGLE_PAGE_PREFIX, $what)->clear_cache();
      self::get_post_content_cache(self::LOOP_PAGE_PREFIX, $what)->clear_cache();
      self::get_post_content_cache(self::RSS_ITEM_PREFIX, $what)->clear_cache();

      log_info("The page cache for post $what has been cleared.");
    }

    // NOTE: Don't clear the thumbnail info so that we don't loose the information about which thumbs have
    //   already created.
  }

  private function reset_data($is_rss, $is_excerpt) {
    $this->is_rss = $is_rss;
    $this->is_excerpt = $is_excerpt;
    $this->placeholders = array();
    $this->id_suffix = array();
    $this->headings = array();
    $this->headings_title_map = array();
    $this->text_positions = array();
    $this->anchor_id_counter = 0;
    $this->thumbs_used = array();
  }

  /**
   * This method is a trimmed down version of "convert_post_to_html()". It finds all interlinks and processes
   * them to find all thumbnails. Note that this method works on the original post content rather than on the
   * content Wordpress gives us. This is necessary since the content Wordpress gives us may be only an excerpt
   * which in turn won't contain all image links.
   *
   * @return Returns "true", if the thumbnails are up-to-date ("false" otherwise).
   */
  private function check_thumbnails($post) {
    $thumbs_last_checked_cache = self::get_thumbnail_last_checked_cache();
    $thumbs_last_checked_date = $thumbs_last_checked_cache->get_value($post->ID);
    if (   $thumbs_last_checked_date >= $post->post_modified_gmt
        && $thumbs_last_checked_date >= self::$MARKUP_MODIFICATION_DATE) {
      // Last thumbs check is up-to-date. Now check whether thumbs are up-to-date.
      if (!MSCL_ThumbnailCache::are_post_thumbnails_uptodate($post->ID)) {
        log_info("Thumbnails for post $post->ID need to be updated.");
        return false;
      } else {
        return true;
      }
    }
    $this->reset_data(false, false);

    // clean up line breaks - convert all to "\n"
    $ret = preg_replace('/\r\n|\r/', "\n", $post->post_content);
    $ret = $this->encode_no_markup_texts($ret);

    $this->execute_regex('interlinks', $ret);

    // registed used thumbnails
    MSCL_ThumbnailCache::register_post($post->ID, array_keys($this->thumbs_used));

    // Store date of last thumbs check
    $thumbs_last_checked_cache->set_value($post->ID, MarkupUtil::create_mysql_date());

    log_info("Thumbnails for post $post->ID have been checked.");
    return false;
  }

  private function is_single() {
    return is_single() || is_page();
  }

  public function add_used_thumbnail($thumbnail) {
    $token = $thumbnail->get_token();
    $this->thumbs_used[$token] = $thumbnail;
  }

  private function execute_regex($regex_name, $value) {
    return preg_replace_callback(self::$RULES[$regex_name], array($this, $regex_name.'_callback'), $value);
  }

  /**
   * Encode special code blocks that are to be ignore when parsing the markup. This includes code blocks
   * (<code>, <pre> and {{{ ... }}}, `...`) as well as no-markup-blocks ({{! ... !}}). Also encodes tags that
   * contain URLs in their attributes (such as <a> or <img>).
   */
  private function encode_no_markup_texts($markup_code) {
    // comments (%%)
    $pattern = '/(?<!%)%%(.*)$/m';
    $markup_code = preg_replace($pattern, '', $markup_code);

    // IMPORTANT: The implementation of "encode_no_markup_blocks_callback()" depends on the order of the
    //   alternative in this regexp! So don't change the order!
    $pattern = '/<(pre|code)([ \t]+[^>]*)?>(.*?)<\/\1>' // <pre> and <code>
             . '|\{\{\{(.*?)\}\}\}'  // {{{ ... }}} - multi-line or single line code
             . '|((?<!\n)[ \t]+|(?<=[^\*;:#\n \t]))##([^\n]*?)##(?!#)'  // ## ... ## single line code - a little bit more complicated
             . '|\{\{!(!)?(.*?)!\}\}/si';  // {{! ... !}} and {{!! ... !}} - no markup
    $markup_code = preg_replace_callback($pattern, array($this, 'encode_no_markup_blocks_callback'), $markup_code);

    //
    // URLs in tag attributes
    //
    $pattern = '/<[a-zA-Z]+[ \t]+[^>]*[a-zA-Z0-9\+\.\-]+\:\/\/[^>]*>/Us';
    $markup_code = preg_replace_callback($pattern, array($this, 'encode_inner_tag_urls_callback'), $markup_code);

    return $markup_code;
  }

  /**
   * The callback function for encode_no_markup_blocks
   */
  private function encode_no_markup_blocks_callback($matches) {
    // Depending on the last array key we can find out which type of block was escaped.
    $prefix = '';
    switch (count($matches)) {
      case 4:
        // HTML tag
        $value = $this->format_no_markup_block($matches[1], $matches[3], $matches[2]);
        break;
      case 5:
        // {{{ ... }}}
        $parts = explode("\n", $matches[4], 2);
        if (count($parts) == 2) {
          $value = $this->format_no_markup_block('{{{', $parts[1], $parts[0]);
        } else {
          $value = $this->format_no_markup_block('{{{', $parts[0], '');
        }
        break;
      case 7:
        // `...`
        $prefix = $matches[5];
        $value = $this->format_no_markup_block('##', $matches[6], '');
        break;
      case 9:
        // {{! ... !}}} and {{!! ... !}} - ignore syntax
        if ($matches[7] != '!') {
          // Simply return contents - also escape tag brackets (< and >); this way the user can use this
          // syntax to prevent a < to open an HTML tag.
          $value = htmlspecialchars($matches[8]);
        } else {
          // Allow HTML
          $value = $matches[8];
        }
        break;
      default:
        throw new Exception('Plugin error: unexpected match count in "encode_callback()": '.count($matches));

    }
    return $prefix.$this->encode_placeholder($matches[0], $value);
  }

  private function format_no_markup_block($block_type, $contents, $attributes) {
    switch ($block_type) {
      case 'pre':
        // No syntax highlighting for <pre>, just for <code>.
        return '<pre'.$attributes.'>'.htmlspecialchars($contents).'</pre>';
        
      case '##':
      case 'code':
      case '{{{':
        $language = '';
        $start_line = false;

        // Special ltrim b/c leading whitespace matters on 1st line of content.
        $code = preg_replace('/^\s*\n/U', '', $contents);
        $code = rtrim($code);
        // NOTE: Use $contents here (instead of $code) to differentiate
        //   "{{{ my code }}}"
        //   from
        //   {{{
        //   my only code line
        //   }}}
        $is_multiline = (strpos($contents, "\n") !== false);
        $additional_html_attribs = '';

        if ($block_type == '{{{' && !$is_multiline) {
          // special case: {{{ lang="php" my code goes here }}}   (single line)
          preg_match('/^[ \t]*(?:lang[ \t]*=[ \t]*"(.+)"[ \t]+)(.*)$/U', $code, $matches);
          if (count($matches) == 3) {
            // found lang attribute
            $language = $matches[1];
            $code = $matches[2];
          }
        } else {
          // default case
          preg_match_all('/(?:^|[ \t]+)(\w+)[ \t]*=[ \t]*"(.*)"/U', $attributes, $matches, PREG_SET_ORDER);
          foreach ($matches as $match) {
            switch ($match[1]) {
              case 'lang':
                $language = strtolower(trim($match[2]));
                break;
              case 'line':
                $start_line = (int)$match[2];
                break;
              default:
                $additional_html_attribs .= ' '.$match[1].'="'.$match[2].'"';
            }
          }
        }

        // shorten this generation process when we're in an RSS feed; don't use syntax highlighting (will
        // most likely not work since the RSS rules aren't available). Also don't use line numbers as tables
        // (used to format line numbers) may have borders (which would be ugly and not what the user wants).
        if ($this->is_rss) {
          // Escape '<', '>', '"', '&'.
          $code = htmlspecialchars($code);

          if ($is_multiline) {
            // Multiline code
            // NOTE: We can't use <code> inside <pre> as <code> is an inline element and can't contain a table
            //   we use for line numbering.
            return '<pre class="code">'.$code.'</pre>';
          } else {
            return '<code>'.$code.'</code>';
          }
        }

        //
        // Options see: http://qbnz.com/highlighter/geshi-doc.html
        //
        $is_highlighted = false;
        if (!empty($language) || ($start_line && $is_multiline)) {
          // Use GeSHi for syntax highlighting and/or line numbering
          if (!empty($language)) {
            $geshi = new GeSHi($code, $language);
          } else {
            // NOTE: We need to specify a non-existing language here, as GeSHi can't handle an empty language
            //   name.
            $geshi = new GeSHi($code, 'probably-non-existing-lang');
          }

          if (empty($language) || !array_key_exists($language, self::$SUPPORTED_GESHI_LANGUAGES)) {
            // disable highlighting for unknown language and when no language has been selected
            $geshi->enable_highlighting(false);
          } else {
            $is_highlighted = true;
          }

          $geshi->enable_classes();
          $geshi->enable_keyword_links(false);
          
          if ($start_line && $is_multiline) {
            // Use table for line numbers. This allows for starting at a line > 1 (which would otherwise
            // break XHTML compliance); furthermore this allows to copy the code without getting the line
            // numbers in the copied text.
            $geshi->set_header_type(GESHI_HEADER_PRE_TABLE);
            $geshi->enable_line_numbers(GESHI_NORMAL_LINE_NUMBERS);
            $geshi->start_line_numbers_at($start_line);
          } else {
            $geshi->set_header_type(GESHI_HEADER_NONE);
          }

          $code = $geshi->parse_code();
        } else {
          // Escape '<', '>', '"', '&'.
          $code = htmlspecialchars($code);
        }

        if ($is_highlighted) {
          $css_classes = 'hl hl-'.$language;
        } else {
          $css_classes = 'not-hl';
        }

        if ($start_line && $is_multiline) {
          $css_classes .= ' code-linenum';
        }

        if ($is_multiline) {
          // Multiline code
          // NOTE: We can't use <code> inside <pre> as <code> is an inline element and can't contain a table
          //   we use for line numbering. Furthermore a <pre> element should not contain a <table> element
          //   (used for line numbering) as this would be invalid HTML5 syntax. So we wrap the table in a
          //   <div> instead.
          if ($start_line) {
            return '<div class="code '.$css_classes.'"'.$additional_html_attribs.'>'.$code.'</div>';
          } else {
            return '<pre class="code '.$css_classes.'"'.$additional_html_attribs.'>'.$code.'</pre>';
          }
        } else {
          // Single line code
          if ($css_classes != '') {
            return '<code class="'.$css_classes.'"'.$additional_html_attribs.'>'.$code.'</code>';
          } else {
            return '<code'.$additional_html_attribs.'>'.$code.'</code>';
          }
        }
        break;
    }

    throw new Exception('Plugin error: invalid block type '.$value[0].' encountered in "format_no_markup_block()"');
  }

  private function encode_inner_tag_urls_callback($matches) {
    return $this->encode_placeholder($matches[0], $matches[0]);
  }

  private function encode_placeholder($key, $value, $value_callback_func=null, $requires_text_pos=false) {
    $key = md5($key);
    $this->placeholders[$key] = array($value, $value_callback_func, $requires_text_pos);
    return self::$BLOCK_ENCODE_START_DELIM.$key.self::$BLOCK_ENCODE_END_DELIM;
  }

  private function decode_placeholders($markup_code) {
    foreach ($this->placeholders as $key => $infos) {
      list($value, $callback_func, $requires_text_pos) = $infos;
      if ($requires_text_pos && $callback_func !== null) {
        // Encode line in the placeholders
        // NOTE: This is highly inefficient but we don't have any alternative for now.
        $search = self::$BLOCK_ENCODE_START_DELIM.$key.self::$BLOCK_ENCODE_END_DELIM;
        $pos = strpos($markup_code, $search);
        if ($pos !== false) {
          $markup_code = str_replace($search,
                  self::$BLOCK_ENCODE_START_DELIM.$key."_{$pos}_".self::$BLOCK_ENCODE_END_DELIM,
                  $markup_code);
        }
      }
    }

    // NOTE: This must be done AFTER encoding the positions in the placeholders as this changes the text.
    $this->determine_text_positions($markup_code);

    // NOTE: MD5 ist 32 hex chars (a - f, 0 - 9)
    $pattern = '/'.preg_quote(self::$BLOCK_ENCODE_START_DELIM).'([a-f0-9]{32})(?:_([0-9]+)_)?'.preg_quote(self::$BLOCK_ENCODE_END_DELIM).'/';
    return preg_replace_callback($pattern, array($this, 'decode_placeholders_callback'), $markup_code);
  }

  /**
   * The callback function for decode_no_markup_blocks
   */
  private function decode_placeholders_callback($matches) {
    list($value, $callback_func, $requires_text_pos) = $this->placeholders[$matches[1]];
    if ($callback_func !== null) {
      if (!$requires_text_pos) {
        return call_user_func($callback_func, $value);
      } else {
        return call_user_func($callback_func, $value, (int)$matches[2]);
      }
    } else {
      return $value;
    }
  }

  private function add_text_position_request($text) {
    $this->text_positions[$text] = -1;
  }

  private function determine_text_positions($markup_code) {
    foreach ($this->text_positions as $text => $unused) {
      $pos = strpos($markup_code, $text);
      if ($pos !== false) {
        $this->text_positions[$text] = $pos;
      }
    }
  }

  private function get_text_position($text) {
    if (isset($this->text_positions[$text])) {
      return $this->text_positions[$text];
    } else {
      return -1;
    }
  }

  //
  // RegExp callbacks
  //

  private function bold_callback($matches) {
    return '<strong>'.$matches[1].'</strong>';
  }

  private function emphasis_callback($matches) {
    return '<em>'.$matches[1].'</em>';
  }

  private function underline_callback($matches) {
    // NOTE: <u> is no longer a valid tag in HTML5. So we use
    //   a <span> together with CSS instead.
    return '<span class="underline">'.$matches[1].'</span>';
  }

  private function strike_through_callback($matches) {
    // NOTE: <strike> is no longer a valid tag in HTML5. So we use
    //   a <span> together with CSS instead.
    return '<span class="strike">'.$matches[1].'</span>';
  }

  private function super_script_callback($matches) {
    return '<sup>'.$matches[1].'</sup>';
  }

  private function sub_script_callback($matches) {
    return '<sub>'.$matches[1].'</sub>';
  }

  /**
   * The callback function for horizontal line
   */
  private function horizontal_callback($matches) {
    return '<hr/>';
  }

  private function blockquote_callback($matches) {
    return '<blockquote>'.str_replace("\n>", "\n", $matches[1]).'</blockquote>';
  }

  private function indention_callback($matches) {
    return '<p class="indented">'.trim($matches[1]).'</p>';
  }

  //
  // Links
  //
  private function plain_text_urls_callback($matches) {
    $protocol = $matches[2];
    $url = $matches[1];
    if (count($matches) == 5) {
      // if punctuation is found, there has been a blank added between the url and the punctionation;
      // eg.: (my link: http://en.wikipedia.org/wiki/Portal_(Game) )
      // so we remove the blank so that the result looks like expected
      $punctuation = substr($matches[4], 1);
    } else {
      $punctuation = '';
    }

    $title = $this->get_plain_url_name($url);

    // Replace "+" and "." for the css name as they have special meaning in CSS.
    $protocol_css_name = str_replace(array('+', '.'), '-', $protocol);
    return $this->generate_link_tag($url, $title,
                                    array('external', "external-$protocol_css_name", $protocol_css_name))
           .$punctuation;
  }

  private function get_plain_url_name($url) {
    if (!BlogTextSettings::remove_common_protocol_prefixes()) {
      return $url;
    }

    $url_info = parse_url($url);
    if ($url_info['scheme'] != 'http' && $url_info['scheme'] != 'https') {
      // we only handle http and https
      return $url;
    }

    if (   isset($url_info['path'])
        || isset($url_info['query']) || isset($url_info['fragment'])
        || isset($url_info['user']) || isset($url_info['pass'])) {
      // If any of the above mentioned "advanced" URL parts is in the URL, don't shorten the URL.
      return $url;
    }

    // Only shorten URLs that don't have a path (or any other URL parts)
    return $url_info['host'];
  }

  private function generate_link_tag($url, $name, $css_classes, $new_window=true, $is_attachment=false) {
    if ($this->is_rss) {
      // no css classes in RSS feeds
      $css_classes = '';
    } else {
      $css_classes = trim(implode(' ', $css_classes));
    }
    $target_attr = $new_window ? ' target="_blank"' : '';
    $target_attr .= $is_attachment ? ' rel="attachment"' : '';
    $css_classes = !empty($css_classes) ? ' class="'.$css_classes.'"' : '';
    return '<a'.$css_classes.' href="'.$url.'"'.$target_attr.'>'.$name.'</a>';
  }

  private function interlinks_callback($matches) {
    // split at | (but not at \| but at \\|)
    $params = preg_split('/(?<!(?<!\\\\)\\\\)\|/', $matches[1]);
    // unescape \|, \[, and \] - don't escape \\ just yet, as it may still be used in \:
    $params = str_replace(array('\\[', '\\]', '\\|'), array('[', ']', '|'), $params);
    // find prefix (allow \: as escape for :)
    $prefix_parts = preg_split('/(?<!(?<!\\\\)\\\\):/', $params[0], 2);
    if (count($prefix_parts) == 2) {
      $prefix = $prefix_parts[0];
      $params[0] = $prefix_parts[1];
    } else {
      $prefix = '';
      $params[0] = $prefix_parts[0];
    }
    $params = str_replace('\\\\', '\\', $params);

    $text_after = $matches[2]; // like in [[syntax]]es
    return $this->resolve_link($prefix, $params, true, $text_after);
  }

  private function interlink_params_callback($matches) {
    $key = $matches[1] - 1;
    if (array_key_exists($key, $this->cur_interlink_params)) {
      return $this->cur_interlink_params[$key];
    } else {
      return '';
    }
  }

  public static function get_prefix($link) {
    // determine prefix
    $parts = explode(':', $link, 2);
    if (count($parts) == 2) {
      return $parts;
    } else {
      return array('', $link);
    }
  }

  /**
   * Resolves and returns the specified link.
   *
   * @param string $prefix the links prefix; use "get_prefix()" to obtain the prefix.
   * @param array $params the params of this link; note that the first element must not contain the prefix
   * @param bool $generate_html if this is "true", HTML code will be generated for this link. This is usually
   *   a <a> tag, but may be any other tag (such as "<div>", "<img>", "<span>", ...). If this is "false", only
   *   the link to the specified element will be returned. May be "null", if the link target could not be
   *   found or the prefix doesn't allow direct linking.
   * @param string $text_after text that comes directly after the link; ie. the text isn't separated from
   *   the link by a space (like "[wiki:URL]s"). Not used when "$generate_html = false".
   * 
   * @return string HTML code or the link (which may be "null")
   */
  public function resolve_link($prefix, $params, $generate_html, $text_after) {
    $post_id = MarkupUtil::get_post(null, true);

    $link = null;
    $title = null;
    $is_external = false;
    $is_attachment = false;
    $link_type = null;

    $not_found_reason = '';

    $prefix_lowercase = strtolower($prefix);

    if (isset(self::$interlinks[$prefix_lowercase])) {
      // NOTE: The prefix may even be empty.
      $prefix_handler = self::$interlinks[$prefix_lowercase];

      if ($prefix_handler instanceof IInterlinkMacro) {
        // Let the macro create the HTML code and return it directly.
        return $prefix_handler->handle_macro($this, $prefix_lowercase, $params, $generate_html, $text_after);
      }

      if ($prefix_handler instanceof IInterlinkLinkResolver) {
        try {
          list($link, $title, $is_external, $link_type) = $prefix_handler->resolve_link($post_id, $prefix_lowercase, $params);
          $is_attachment = ($link_type == IInterlinkLinkResolver::TYPE_ATTACHMENT);
        } catch (LinkNotFoundException $e) {
          $not_found_reason = $e->get_reason();
          $title = $e->get_title();
        }
      } else if (is_array($prefix_handler)) {
        // Simple text replacement
        // Unfortunately as a hack we need to store the current params in a member variable. This is necessary
        // because we can't pass them directly to the callback method, nested functions can't be used as
        // callback functions and anonymous function are only available in PHP 5.3 and higher.
        $this->cur_interlink_params = $params;
        $link = preg_replace_callback('/\$(\d+)/', array($this, 'interlink_params_callback'), 
                                      self::$interlinks[$prefix_lowercase]['pattern']);
        $is_external = self::$interlinks[$prefix_lowercase]['external'];
      } else {
        throw new Exception("Invalid prefix handler: ".gettype($prefix_handler));
      }
    } else {
      // Unknown prefix; in most cases this is a url like "http://www.lordb.de" where "http" is the prefix
      // and "//www.lordb.de" is the first parameter.
      if (empty($prefix)) {
        // Special case: if the user (for some reasons) has removed the interlink handler for the empty
        // prefix.
        $not_found_reason = LinkNotFoundException::REASON_DONT_EXIST;
      } else {
        if (substr($params[0], 0, 2) == '//') {
          // URL
          $link = $prefix.':'.$params[0];
          $is_external = true;
          if (count($params) == 1 && substr($params[0], 0, 2) == '//') {
            $title = $this->get_plain_url_name($link);
          }
        } else {
          // not an url - assume wrong prefix
          $not_found_reason = 'unknown prefix';
          if (count($params) == 1) {
            $title = "$prefix:$params[0]";
          }
        }
      }
    }

    if (!$generate_html) {
      return $link;
    }

    // new window for external links - if enabled in the settings
    $new_window = ($is_external && BlogTextSettings::new_window_for_external_links());

    //
    // CSS classes
    // NOTE: We store them as associative array to prevent inserting the same CSS class twice.
    //
    if ($is_attachment) {
      // Attachments are a special case.
      $css_classes = array('attachment' => true);
    } else if ($link_type == IInterlinkLinkResolver::TYPE_SAME_PAGE_ANCHOR) {
      // Link on the same page - add text position requests to determine whether the heading is above or
      // below the link's position.
      // NOTE: We can't check whether the heading already exists in our headings array to determine whether
      //   it's above; this would only be possible, if we parsed character after character. We, however,
      //   execute rule after rule; so at this point all headings are already known.
      $anchor_name = substr($link, 1);
      if ($this->heading_name_exists($anchor_name)) {
        $this->add_text_position_request('"'.$anchor_name.'"');
        // NOTE: We need to append a counter to the anchor name as otherwise all links to the same anchor will
        //   get the same position calculated.
        $placeholder = $this->encode_placeholder('section-link'.$anchor_name.$this->anchor_id_counter,
                                                 $anchor_name,
                                                 array($this, 'resolve_heading_relative_pos'), true);
        $this->anchor_id_counter++;
        $css_classes = array('section-link-'.$placeholder => true);
      } else {
        $not_found_reason = 'not existing';
      }
    } else {
      if ($is_external) {
        $css_classes = array('external' => true);
      } else {
        $css_classes = array('internal' => true);
      }

      if (!empty($prefix)) {
        // Replace "+" and "." for the css name as they have special meaning in CSS.
        // NOTE: When this is just an URL the prefix will be the protocol (eg. "http", "ftp", ...)
        $css_name = ($is_external ? 'external-' : 'internal-')
                  . str_replace(array('+', '.'), '-', $prefix);
        $css_classes[$css_name] = true;
      }

      if (!empty($link_type)) {
        // Replace "+" and "." for the css name as they have special meaning in CSS.
        $css_name = ($is_external ? 'external-' : 'internal-')
                  . str_replace(array('+', '.'), '-', $link_type);
        $css_classes[$css_name] = true;
      }
    }


    if (!empty($not_found_reason)) {
      // Page not found
      $link = '#';
      // NOTE: Create title as otherwise "#" (the link) will be used as title
      if (empty($title) && count($params) == 1) {
        $title = $params[0];
      }
    }

    //
    // Determine link name
    //
    if (empty($title)) {
      if (count($params) > 1) {
        // if there's more than one parameter, the last parameter is the link's name
        // NOTE: For "[[wiki:Portal|en]]" this would create a link to the wikipedia articel "Portal" and at the
        // same time name the link "Portal"; this is quite clever. If this interlink had only one parameter,
        // one would use "[[wiki:Portal|]]" (note the empty last param).
        $title = $params[count($params) - 1];
        if (empty($title)) {
          // an empty name is a shortcut for using the first param as name
          $title = $params[0];
        }
      }

      // No "else if" here as (although unlikely) the first parameter may be empty
      if (empty($title)) {
        if ($link_type == IInterlinkLinkResolver::TYPE_SAME_PAGE_ANCHOR) {
          $anchor_name = substr($link, 1); // remove leading #
          $title = $this->resolve_heading_name($anchor_name, true);
        } else {
          // If no name has been specified explicitly, we use the link instead.
          $title = $link;
        }
      }
    }

    if (!empty($not_found_reason)) {
      // Page not found
      $title .= '['.$not_found_reason.']';
      if ($link_type != IInterlinkLinkResolver::TYPE_SAME_PAGE_ANCHOR) {
        $css_classes = array('not-found' => true);
      } else {
        $css_classes = array('section-link-not-existing' => true);
      }
    } else if ($is_attachment || $is_external) {
      // Check for file extension
      if ($is_attachment) {
        $filename = basename($link);
      } else {
        // we need to extract the path here, so that query (everything after ?) or the domain name doesn't
        // "confuse" basename.
        $filename = basename(parse_url($link, PHP_URL_PATH));
      }
      $dotpos = strrpos($filename, '.');
      if ($dotpos !== false) {
        $suffix = strtolower(substr($filename, $dotpos + 1));
        if ($suffix == 'jpeg') {
          $suffix = 'jpg';
        }

        switch ($suffix) {
          case 'htm':
          case 'html':
          case 'php':
          case 'jsp':
          case 'asp':
          case 'aspx':
            // ignore common html extensions
            break;

          default:
            if (!$is_attachment) {
              $css_classes = array('external-file' => true);
            }
            if ($suffix == 'txt') {
              // certain file types can't be uploaded by default (eg. .php). A common fix would be to add the
              // ".txt" extension (eg. "phpinfo.php.txt"). Wordpress converts this file name to
              // "phpinfo.php_.txt").
              $olddotpos = $dotpos;
              $dotpos = strrpos($filename, '.', -5);
              if ($dotpos !== false) {
                $real_suffix = strtolower(substr($filename, $dotpos + 1, $olddotpos - $dotpos - 1));
                if (strlen($real_suffix) > 2) {
                  if ($real_suffix[strlen($real_suffix) - 1] == '_') {
                    $real_suffix = substr($real_suffix, 0, -1);
                  }
                  
                  switch ($real_suffix) {
                    case 'htm':
                    case 'html':
                    case 'php':
                    case 'jsp':
                    case 'asp':
                    case 'aspx':
                      $suffix = $real_suffix;
                      break;
                  }
                }
              }
            }
            $css_classes['file-'.$suffix] = true;
            break;
        }

        // Force new window for certain suffixes. Note most suffix will trigger a download, so for those
        // there's no need to open them in a new window. Only open files in a new window that the browser
        // usually displays "in-browser".
        switch ($suffix) {
            // images
          case 'png':
          case 'jpg':
          case 'gif':
            // video files
          case 'ram':
          case 'rb':
          case 'rm':
          case 'mov':
          case 'mpg':
          case 'wmv':
          case 'avi':
          case 'divx':
          case 'xvid':
            $new_window = true;
            break;

            // special case for MacOSX, where PDFs are usually not displayed in the browser
          case 'pdf':
            if (strpos($_SERVER['HTTP_USER_AGENT'], 'Mac OS X') === false) {
              $new_window = true;
            }
            break;
        }
      }
    }

    $title = $title.$text_after;

    return $this->generate_link_tag($link, $title, array_keys($css_classes), $new_window, $is_attachment);
  }


  //
  // Headings
  //

  /**
   * The callback function for headings (=====)
   */
  private function headings_callback($matches) {
    $level = strlen($matches[1]);
    $text = trim($matches[2]);
    // Remove trailing equal signs
    $text = trim(rtrim($text, '='));

    if (count($matches) == 4) {
      // Replace spaces and tabs in the anchor name. IMO this is the best way to deal with whitespace in
      // the anchor name (although it's not recommended).
      $id = str_replace(array(' ', "\t"), '_', trim($matches[3]));
    } else {
      $id = '';
    }
    return $this->generate_heading($level, $text, $id);
  }

  private function generate_heading($level, $text, $id='') {
    // Check whether a heading with the exact same text already exists. If so, then add a counter.
    // NOTE: Actually we don't check the text but the ID generated from it, because headings with slightly
    //   different special chars (like white space or punctuation) may generate the same id.
    if ($id == '') {
      $id = $this->sanitize_html_id($text);
    }
    $this->register_html_id($id);

    global $post;
    if (is_single() || is_page()) {
      $permalink = '';
    } else {
      // Post listing, ie. not a single posting/page
      // Therefor we need to append the post's ID to the heading ID, because there may be multiple headings
      // with the same text of multiple posts in the listing.
      $permalink = get_permalink($post->ID).'#'.$id;
      $id = $post->ID.'_'.$id;
    }

    // adjust level; this helps to be able to use the top level heading (= heading =) without having to worry
    // about which is the correct level (which is usually <h2> instead of <h1>).
    // But only, if not in the RSS feed. In a RSS feed we can use <h1> without any problems.
    if (!$this->is_rss) {
      $level += (BlogTextSettings::get_top_level_heading_level() - 1);
    }
    if ($level > 6) {
      $level = 6;
    }

    $this->headings[] = array(
      'level' => $level,
      'id' => $id,
      'text' => $text
    );
    $this->headings_title_map[$id] = $text;

    return $this->format_heading($level, $text, $id, $permalink);
  }

  private function format_heading($level, $text, $id, $id_link='', $add_anchor=true) {
    if (empty($id_link)) {
      $id_link = '#'.$id;
    }
    if ($add_anchor) {
      $anchor = " <a class=\"heading-link\" href=\"$id_link\" title=\"Link to this section\">¶</a>";
    } else {
      $anchor = '';
    }

    return "<h$level id=\"$id\">$text$anchor</h$level>";
  }

  /**
   * Registers and possibly adjusts the specified id (id attribute in a HTML tag). The id will be adjusted
   * when there is another id with the same name.
   *
   * @param string $id
   */
  private function register_html_id(&$id) {
    if (array_key_exists($id, $this->id_suffix)) {
      $this->id_suffix[$id]++;
      $id .= '_'.$this->id_suffix[$id];
    } else {
      // just register the id here. Don't add the counter.
      $this->id_suffix[$id] = 1;
    }
  }

  /**
   * Convert illegal chars in an id (id attribute in a HTML tag).
   */
  private function sanitize_html_id($id) {
    $ret = str_replace(' ', '_', strtolower($id));
    return str_replace('%', '.', rawurlencode($ret));
  }

  private function simple_interlinks_callback($matches) {
    // TODO: Make this "real" plugins - not just hardcoded TOC
    switch (strtolower($matches[1])) {
      case 'toc':
        return $this->generate_toc();
      default:
        return self::generate_error_html('Plugin "'.$matches[1].'" not found.');
    }
  }

  /**
   * This is a callback function. Don't call it directly.
   */
  public function resolve_heading_relative_pos($anchor_name, $pos) {
    $heading_pos = $this->get_text_position('"'.$anchor_name.'"');
    if ($heading_pos == -1) {
      return 'not-existing';
    }

    return ($heading_pos < $pos ? 'above' : 'below');
  }
  
  private function heading_name_exists($anchor_name) {
    return isset($this->headings_title_map[$anchor_name]);
  }

  private function resolve_heading_name($anchor_name) {
    // Called from decode_placeholders().
    if (isset($this->headings_title_map[$anchor_name])) {
      return $this->headings_title_map[$anchor_name];
    } else {
      // Section doesn't exist.
      return '#'.$anchor_name;
    }
  }

  /**
   * Generates and returns the TOC (table of contents) for this post/page.
   */
  private function generate_toc() {
    global $post;

    if (empty($this->headings)) {
      return '';
    }

    // Don't display the TOC if this is just an excerpt (either "real" excerpt or more link).
    if ($this->is_excerpt) {
      return '';
    }

    $min = $this->headings[0]['level'];
    $level = array();
    $prev = 0;
    $toc = '';
    foreach ($this->headings as $k => $h) {
      $depth = $h['level'] - $min + 1;
      $depth = $depth < 1 ? 1 : $depth;

      if ($depth > $prev) { // add one level
        $toclevel = count($level) + 1;
        $toc .= "<ul>\n<li class=\"toclevel-$toclevel\">";
        $open = true;
        array_push($level, 1);
      } else if ($depth == $prev || $depth >= count($level)) { // no change
        $toclevel = count($level);
        $toc .= "</li>\n<li class=\"toclevel-$toclevel\">";
        $level[count($level) - 1] = ++$level[count($level) - 1];
      } else {
        $toclevel = $depth;
        while(count($level) > $depth) {
          $toc .= "</li>\n</ul>";
          array_pop($level);
        }
        $level[count($level) - 1] = ++$level[count($level) - 1];
        $toc .= "</li>\n<li class=\"toclevel-$toclevel\">";
      }
      $prev = $depth;

      $toc .= "<a href=\"#".$h['id']."\"><span class=\"tocnumber\">".implode('.', $level)."</span> "
           .  "<span class=\"toctext\">".$h['text']."</span></a>";
    }

    // close left
    while(count($level) > 0) {
      $toc .= "</li>\n</ul>\n";
      array_pop($level);
    }

    return "<div class=\"toc\">\n<div class=\"toc-title\">".BlogTextSettings::get_toc_title()
           .' <span class="toc-toggle">[<a id="_toctoggle_'.$post->ID.'" href="javascript:toggle_toc('.$post->ID.');">hide</a>]</span>'
           ."</div>\n<div id=\"_toclist_$post->ID\">$toc\n</div></div>";
  }

  //
  // Lists
  //

  private static function convert_to_unique_list_types($list_stack_str) {
    // replace the two-character symbols (ie. ";!" and ";:") with single characters, so that this can be
    // be used more easily
    $list_stack_str = str_replace(array(';!', ';:'), array('t', 'd'), $list_stack_str);
    $unique_list_types = array();
    for ($i = 0; $i < strlen($list_stack_str); $i++) {
      switch ($list_stack_str[$i]) {
        case '*':
          $unique_list_types[] = ATM_ListStack::UNIQUE_ITEM_TYPE_UL;
          break;
        case '#':
          $unique_list_types[] = ATM_ListStack::UNIQUE_ITEM_TYPE_OL;
          break;
        case 't':
          $unique_list_types[] = ATM_ListStack::UNIQUE_ITEM_TYPE_DT;
          break;
        case 'd':
          $unique_list_types[] = ATM_ListStack::UNIQUE_ITEM_TYPE_DD;
          break;
        default:
          throw new Exception();
      }
    }
    return $unique_list_types;
  }

  /**
   * The callback function for lists
   */
  private function list_callback($matches) {
    $list_stack = new ATM_ListStack();

    preg_match_all('/^(?:(?:((?:\*|#|;[\:\!])+)(\^|;|\!|)[ \t]*|(;)(?![\:\!])|[ \t]+)(.*?)|)$/m',
                   $matches[0], $list, PREG_SET_ORDER);
    foreach ($list as $val) {
      if (count($val) == 1) {
        // Add paragraph; useful to make list wider, like:
        //
        //  * item 1
        //
        //  * item 2
        //
        // Though this isn't the best pratice, we still let the user decide whether he/she wants a dense or
        // a wide list.
        $list_stack->append_para();
        continue;
      }
      $text = $val[4];

      // contains either:
      // * for example "**#" for a three level list
      // * is empty, if the line starts with spaces and/or tabs or in case of a inline definition
      $new_list_stack_str = $val[1];

      $inline_dl = ($val[2] == ';' || $val[3] == ';');

      if (empty($new_list_stack_str) && !$inline_dl) {
        // continue the previous list level
        if (trim($text) == '') {
          // empty line - see note above
          $list_stack->append_para();
        } else {
          $list_stack->append_text("\n".$text);
        }
        continue;
      }

      $continue_list = ($val[2] == '^');
      $restart_list = ($val[2] == '!');

      if ($restart_list && $list_stack->has_open_lists()) {
        // restart the list; useful only for ordered list in which the numbering starts again at 1
        // NOTE: You can only restart the deepest nested list (ie. the right most). I doubt that there's a
        //   use case in which one would need to restart a list lower in the list stack.
        // NOTE 2: We close the deepest list here. If the new list doesn't match the closed list, no harm is
        //   done since the old list would have been closed anyway. In any case a new list is opened.
        $list_stack->close_lists(1);
      }

      $new_list_stack_types = self::convert_to_unique_list_types($new_list_stack_str);
      if ($inline_dl) {
        // inline definition line: "term : definition"
        $parts = explode(': ', $text, 2);
        if (count($parts) == 2) {
          $term = $parts[0];
          $text = $parts[1];
        } else {
          $term = $text;
          $text = '';
        }
        $new_list_stack_types[] = ATM_ListStack::UNIQUE_ITEM_TYPE_DT;
        $list_stack->append_new_item($new_list_stack_types, false, $term);
        array_pop($new_list_stack_types);
        $new_list_stack_types[] = ATM_ListStack::UNIQUE_ITEM_TYPE_DD;
        $list_stack->append_new_item($new_list_stack_types, false, $text);
      } else {
        $list_stack->append_new_item($new_list_stack_types, $continue_list, $text);
      }

      $prev_was_empty_line = false;
    }

    //
    // generate code
    //
    $code = '';
    foreach ($list_stack->root_items as $root_item) {
      if ($root_item instanceof ATM_List) {
        $code .= $this->generate_list_code($root_item);
      } else {
        $code .= $root_item;
      }
    }

    return $code;
  }

  //
  // Tables
  //

  /**
   * The callback function for simple tables
   */
  private function simple_table_callback($matches) {
    $table_code = $matches[1];
    $caption = @$matches[2];

    $table = new ATM_Table();
    $table->caption = $caption;

    foreach (explode("\n", $table_code) as $row_code) {
      $row = new ATM_TableRow();

      foreach (explode('|', $row_code) as $cell_code) {
        // NOTE: DON'T trim the cell code here as we need to differentiate between "|= text" and "| =text".
        if (empty($cell_code)) {
          // can only be happening on the last element - still we need to add it so that we can remove the
          // last element below in a secure way
          $row->cells[] = new ATM_TableCell(ATM_TableCell::TYPE_TD, '');
        } else {
          if ($cell_code[0] == '=') {
            $row->cells[] = new ATM_TableCell(ATM_TableCell::TYPE_TH, trim(substr($cell_code, 1)));
          } else {
            $row->cells[] = new ATM_TableCell(ATM_TableCell::TYPE_TD, trim($cell_code));
          }
        }

        $last_cell = $row->cells[count($row->cells) - 1];
        if (empty($last_cell->cell_content) && $last_cell->cell_type == ATM_TableCell::TYPE_TD) {
          // Remove the last cell, if it's empty. This is the result of "| my cell |" (which would otherwise
          // result in two cells).
          array_pop($row->cells);
        }
      }

      $table->rows[] = $row;
    }

    return $this->generate_table_code($table, true);
  }

  /**
   * The callback function for complex tables
   */
  private function complex_table_callback($matches) {
    $attrs = trim($matches[1]);
    $table_caption = trim($matches[2]);
    $rows = $matches[3];

    if (array_key_exists(4, $matches)) {
      // nested tables
      $rows = $this->execute_regex('complex_table', $rows);
    }

    $table = new ATM_Table();
    $table->tag_attributes = $attrs;
    $table->caption = $table_caption;

    $rregex = '/(?:^(\||!)-|\G)(.*?)^(.*?)(?=(?:\|-|!-|\z))/msi';
    preg_match_all($rregex, $rows, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
      if (empty($match[0])) {
        continue;
      }
      $table->rows[] = $this->handle_complex_table_row($match);
    }

    // Don't fill up table cells for complex tables. If the table uses colspan or rowspan (especially through
    // CSS classes) we can't determine the number of missing cells per row. So don't try.
    return $this->generate_table_code($table, false);
  }

  /**
   * The callback function for rows in tables
   */
  private function handle_complex_table_row($matches) {
    $attrs = trim($matches[2]);
    $cells = $matches[3];

    $row = new ATM_TableRow();
    $row->tag_attributes = $attrs;

    $cregex = '#((?:\||!|\|\||!!|\G))(?:([^|\n]*?)\|(?!\|))?(.+?)(?=\||!|\|\||!!|\z)#msi';
    preg_match_all($cregex, $cells, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
      if (empty($match[0])) {
        continue;
      }
      $row->cells[] = $this->handle_complex_table_cell($match);
    }

    return $row;
  }

  /**
   * The callback function for cols in rows
   */
  private function handle_complex_table_cell($matches) {
    $type = $matches[1];
    $attrs = trim($matches[2]);
    if ($type == '!') {
      // TODO: The regex above seems to be wrong. For "!! text" it matches only the first ! and places the
      //   second here in the content. This is not how it should work as the syntax requires !! for table
      //   headings on the same line.
      // For now we simply trim the !
      $content = trim(ltrim($matches[3], '!'));
    } else {
      $content = trim($matches[3]);
    }

    $cell = new ATM_TableCell($type == '!' ? ATM_TableCell::TYPE_TH : ATM_TableCell::TYPE_TD, $content);
    $cell->tag_attributes = $attrs;

    return $cell;
  }
}
?>
