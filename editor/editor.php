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


require_once(dirname(__FILE__).'/../docu.php');

class BlogTextEditor {
  private function  __construct() {
    if (self::is_editor_present()) {
      // Editor present on the current page. Don't add the javascript, if no editor is present.
      add_action('admin_head', array($this, 'insert_editor_javascript'));
      add_filter('the_editor', array($this, 'add_blogtext_syntax_link'));
    }

    // NOTE: We need to specify a high value (30) for priority here as otherwise we won't be able to replace
    //  the HTML code.
    // NOTE 2: We can't use "is_editor_present()" here as the media uploader is an extra page (not directly
    //  containing an editor).
    add_filter('media_send_to_editor', array($this, 'fix_media_browser_code'), 30, 3);
  }

  private static function is_editor_present() {
    if (!is_admin()) {
      return false;
    }
    // NOTE: We can't simply compare the two as wordpress may be located in a sub directory.
    return strpos($_SERVER['REQUEST_URI'], 'wp-admin/post.php')
        || strpos($_SERVER['REQUEST_URI'], 'wp-admin/post-new.php');
  }

  public static function init() {
    static $instance = null;
    if ($instance === null) {
      $instance = new BlogTextEditor();
    }
  }

  public static function insert_css_files($plugin) {
    $plugin->add_backend_stylesheet('editor/editor.css');
    if (BlogTextSettings::enable_monospace_editor_font()) {
      $plugin->add_backend_stylesheet('editor/editor-monospace.css');
    }
  }

  public function insert_editor_javascript() {
    // Replace buttons (quick tags) in Wordpress' HTML editor
    echo '<script type="text/javascript" src="'.BlogTextPlugin::get_instance()->get_plugin_url().'/editor/quicktags.js"></script>';
  }

  public function add_blogtext_syntax_link($editor_html) {
    $syntax_link = '<div class="blogtext_syntax_link"><a href="'.BlogTextDocumentation::SYNTAX_DESC.'" target="_blank">BlogText Syntax</a></div>';
    $editor_html = preg_replace('/<\/textarea>(.*)<\/div>/is', '</textarea>\1'.$syntax_link.'</div>', $editor_html, 1);
    return $editor_html;
  }

  /**
   * Wordpress callback function. Called from "wp-admin/includes/media.php".
   *
   * @param string $html HTML code for the media to be filtered
   * @param int $attachment_id Id of this attachment
   * @param array $attachment contains information that was specified in the media browser HTML form (such
   *   as title, alignment, and size).
   * @return string the filtered editor code
   */
  public function fix_media_browser_code($html, $attachment_id, $attachment) {
    if (wp_attachment_is_image($attachment_id)) {
      // Use the attachment's filename as identification. This uniquely identifies the image (like the id).
      // If the same file is uploaded again, its filename gets a number appended (eg. "myfile2.png").
      $code = '[[image:'.MarkupUtil::get_attachment_filename($attachment_id);
      if (isset($attachment['align']) && strtolower($attachment['align']) != 'none') {
        $code .= '|'.strtolower($attachment['align']);
      }

      $link = '';
      $link_to_source = false;
      if (isset($attachment['url']) && !empty($attachment['url'])) {
        if ($attachment['url'] == wp_get_attachment_url($attachment_id)) {
          $link_to_source = true;
        } else {
          $link = $attachment['url'];
        }
      }

      if (isset($attachment['image-size']) && strtolower($attachment['image-size']) != 'full') {
        $size = strtolower($attachment['image-size']);
        if ($size == 'thumb' || $size == 'thumbnail') {
          $size = 'small';
        }
      } else {
        $size = '';
      }

      if ($size == 'small' && $link_to_source) {
        // thumbnail
        $code .= '|thumb';
      } else {
        if (!empty($size)) {
          $code .= '|'.$size;
        }

        if ($link_to_source) {
          $code .= '|link=source';
        } else if (!empty($link)) {
          if ($link == get_attachment_link($attachment_id)) {
            // link to attachment page (ie. page containing the attachment) instead of attachment itself
            $code .= '|link='.get_post($attachment_id)->post_name;
          } else {
            $code .= '|link='.$link;
          }
        }
      }

      $code .= ']]';
    } else {
      // not an image
      if (isset($attachment['url']) && $attachment['url'] == get_attachment_link($attachment_id)) {
        // link to the attachment description page
        $code = '[['.get_post($attachment_id)->post_name.']]';
      } else {
        // link directly to the file
        $code = '[[file:'.MarkupUtil::get_attachment_filename($attachment_id).']]';
      }
    }
    
    return $code;
  }
}
BlogTextEditor::init();
?>