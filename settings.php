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


/*
 * This file contains all of the plugin's settings.
 */
require_once(dirname(__FILE__).'/api/commons.php');
MSCL_Api::load(MSCL_Api::OPTIONS_API);

require_once(dirname(__FILE__).'/docu.php');

// TODO: Add descriptions for options
class BlogTextSettings {
  const OWN_GESHI_STYLE = 'own';

  /**
   * Callback function.
   */
  public static function check_top_level_heading_level(&$input) {
    return ($input >= 1 && $input <= 6);
  }

  public static function get_top_level_heading_level($get_option=false) {
    static $option = null;
    if ($option == null) {
      $option = new MSCL_IntOption('blogtext_top_level_heading_level', 'Top Level Heading Level', 2, 
              'Specifies which heading level (1 - 6) the top-level heading represents. For example, '
              .'specifying "3" here, will result in "= Heading =" be converted into '
              .'"&lt;h3&gt;Heading&lt;/h3&gt;".',
              'BlogTextSettings::check_top_level_heading_level');
    }
    return $get_option ? $option : $option->get_value();
  }

  public static function new_window_for_external_links($get_option=false) {
    static $option = null;
    if ($option == null) {
      $option = new MSCL_BoolOption('blogtext_new_window_for_external_links', 
              'Open external links in a new window/tab', true);
    }
    return $get_option ? $option : $option->get_value();
  }

  public static function get_default_small_img_alignment($get_option=false) {
    static $option = null;
    if ($option == null) {
      $option = new MSCL_ChoiceOption('blogtext_default_small_img_alignment', 'Default alignment of small images',
                                 array('left' => 'left aligned', 'right' => 'right aligned'), 0,
              'Specifies how images with size "small" are to be aligned if no alignment is specified.');
    }
    return $get_option ? $option : $option->get_value();
  }

  public static function use_frame_for_thumbs($get_option=false) {
    static $option = null;
    if ($option == null) {
      $option = new MSCL_BoolOption('blogtext_use_frame_for_thumbs', 'Use a frame for thumbnails', false,
              'Specifies whether images specified as "[[image:myimage.jpg|thumb]]" are to be wrapped '
              .'in a frame &lt;div&gt; tag.');
    }
    return $get_option ? $option : $option->get_value();
  }

  ////////////////////////////////////////////////////////////////////////////

  public static function use_default_filetype_icons($get_option=false) {
    static $option = null;
    if ($option == null) {
      $option = new MSCL_BoolOption('blogtext_use_default_filetype_icons', 'Use default filetype icons', true,
              'Specifies whether the .css file containing icons for several filetypes (such as PDFs or '
              .'images) shall be included in the output. Without this, no file icons will be displayed on '
              .'links unless the Wordpress theme provides its own.');
    }
    return $get_option ? $option : $option->get_value();
  }

  public static function use_default_css($get_option=false) {
    static $option = null;
    if ($option == null) {
      $option = new MSCL_BoolOption('blogtext_use_default_css', 'Use default CSS file', true,
              'The BlogText plugin comes with a .css file that contains the default style definitions used '
              .'by the BlogText plugin. This way this plugin works out-of-the-box. However, if you (ie. your '
              .'Wordpress theme) want to specify your own styles, you can disable the default styles with '
              .'this option.');
    }
    return $get_option ? $option : $option->get_value();
  }

  public static function get_geshi_theme($get_option=false) {
    static $option = null;
    if ($option == null) {
      $option = new MSCL_ChoiceOption('blogtext_geshi_theme', 'Theme for syntax highlighting',
                                 array('dawn' => "Dawn (bright)",
                                       'mac-classic' => "Mac Classic (bright)",
                                       'twilight' => "Twilight (dark)",
                                       'vibrant-ink' => "Vibrant Ink (dark)",
                                       self::OWN_GESHI_STYLE => "Don't use built-in style"),
                                 1,
              'The BlogText plugin comes with some default styles that are used to style the syntax '
              .'highlighting of code blocks. You can select the style to be used with this option. If you '
              .'want to specify your own style, choose "Don\'t use built-in style" here.');
    }
    return $get_option ? $option : $option->get_value();
  }

  ////////////////////////////////////////////////////////////////////////////

  public static function disable_fix_invalid_xhtml_warning($get_option=false) {
    static $option = null;
    if ($option == null) {
      $option = new MSCL_BoolOption('blogtext_disable_fix_unbalanced_tags_warning',
                               'Disable warning about invalid XHTML nesting correction',
                               false);
    }
    return $get_option ? $option : $option->get_value();
  }

  ////////////////////////////////////////////////////////////////////////////

  public static function get_interlinks($get_option=false, $parse=true) {
    static $option = null;
    if ($option == null) {
      $option = new MSCL_TextareaOption('blogtext_interlinks', 'Interlinks', 80, 8,
                                    "search = http://www.google.com/search?q=$1\n"
                                   ."wiki = http://$1.wikipedia.org/wiki/$2\n",
              'Interlinks are prefixes associated with an (external) URL. Each URL can have any number of '
              .'parameters (represented by "$number"; eg. "$1", "$2", ...). Interlinks are used in BlogText '
              .'like this: <code>[[prefix:param1|param2|...]]</code>.<br/>For example using '
              .'<code>[[wiki:en|Portal]]</code> with <code>wiki = http://$1.wikipedia.org/wiki/$2</code> '
              .'will create this link: http://en.wikipedia.org/wiki/Portal<br/>For more information, see '
              .'<a href="'.BlogTextDocumentation::INTERLINKS_HELP.'" target="_blank">Interlinks Help</a>.');
    }
    if ($get_option) {
      return $option;
    }

    $option = $option->get_value();
    if ($parse) {
      $option = self::parse_interlinks($option);
    }
    return $option;
  }

  public static function parse_interlinks($interlinks_text) {
    $interlinks = array();
    preg_match_all('/^([a-zA-Z0-9\-_]+)[ \t]*=[ \t]*(.+)$/m', $interlinks_text, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
      // TODO: Add ability to specify links as internal
      // NOTE: We need to trim away the line break here for match[2].
      $interlinks[$match[1]] = array(trim($match[2]), true);
    }

    return $interlinks;
  }
}
?>
