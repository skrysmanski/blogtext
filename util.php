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


class MarkupUtil {
  private static $fileicon_extensions = null;
  private static $fileicon_extensions_assoc = null;

  /**
   * Checks whether the specified string is a url.
   *
   * @param string $string
   */
  public static function is_url($string) {
    return (preg_match('/^([a-zA-Z0-9\+\.\-]+)\:\/\/(.*)$/', $string) == 1);
  }

  /**
   * Checks whether the specified string is a url.
   *
   * @param string $string
   * @param array $matches if specified, it'll contain the matches for the protocol and everything after ://
   */
  public static function parse_url($string, &$matches) {
    return (preg_match('/^([a-zA-Z0-9\+\.\-]+)\:\/\/(.*)$/', $string, $matches) == 1);
  }

  public static function is_attachment_type($type) {
    return ($type == 'attachment');
  }

  public static function create_mysql_date($date=null) {
    if ($date === null) {
      $date = time();
    }
    // For this format string, see "current_time()" in "functions.php".
    return gmdate('Y-m-d H:i:s', $date);
  }

  /**
   * Checks whether the specified id is an attachment.
   *
   * @param int $id the id
   */
  public static function is_attachment($id) {
    global $wpdb;
    $type = $wpdb->get_var($wpdb->prepare("SELECT post_type FROM $wpdb->posts WHERE ID='%d'", $id));
    return self::is_attachment_type($type);
  }

  /**
   * Returns the post id for the attachment for specified filename.
   *
   * @param string $filename filename (eg. "myfile.jpg") or attachment name (eg. "myfile")
   * @param int $post_id the id of the post the attachment belongs to; can be "null".
   * 
   * @return int the id or "null", if the attachment could not be found
   */
  public static function get_attachment_id($filename, $post_id) {
    global $wpdb;
    $ids = $wpdb->get_col($wpdb->prepare(
      "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_wp_attached_file' AND meta_value LIKE '%%%s'", $filename));
    
    if (count($ids) == 0) {
      // no attachment found; filename may not contain the file extension
      $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT ID FROM $wpdb->posts WHERE post_name='%s' AND post_type='attachment'", $filename));
      if (count($ids) == 0) {
        // no attachment found with the specified post_name either
        return null;
      }
    }

    if (count($ids) == 1) {
      // only one attachment found; use this.
      return (int)$ids[0];
    }

    if ($post_id === null) {
      // use the latest attachment, which should have the highest post id
      return (int)max($ids);
    }

    // there multiple files with the same file name; use the one belonging to the specified post
    $attachment_ids = $wpdb->get_col($wpdb->prepare(
      "SELECT ID FROM $wpdb->posts WHERE post_parent='%d' AND post_type='attachment'", $post_id));
    // find ids that are in both arrays
    $attachment_ids = array_intersect($ids, $attachment_ids);
    switch (count($attachment_ids)) {
      case 0:
        // no direct attachments for this post. use the latest attachment, which should have the highest post id
        return (int)max($ids);
      case 1:
        // only one - use it
        return (int)$attachment_ids[0];
      default:
        // multiple possibilities; use the latest attachment, which should have the highest post id
        return (int)max($attachment_ids);
    }
  }

  /**
   * Returns the title for an attachment.
   *
   * @param int|object $attachment Attachment ID or attachment (post) object.
   * @return string the title; never null or empty
   */
  public static function get_attachment_title($attachment) {
		if (is_object($attachment)) {
			$att_id = $attachment->ID;
    } else {
			$att_id = $attachment;
    }

    // For images, use the alt text as primary source. Note that this only exists for images.
    $img_alt = get_post_meta($att_id, '_wp_attachment_image_alt', true);
    if (!empty($img_alt)) {
      return $img_alt;
    }

    if (!is_object($attachment)) {
      $attachment = get_post($attachment);
    }

    // Secondary is the "caption" field
    if (!empty($attachment->post_excerpt)) {
      return $attachment->post_excerpt;
    }

    // last resort is the title, which is usually the file name without file extension. But in this case use
    // the full file name (including extension).
    $filename = self::get_attachment_filename($att_id);
    if (!empty($filename)) {
      $dotpos = strrpos($filename, '.');
      if ($dotpos !== false && $attachment->post_title == substr($filename, 0, $dotpos)) {
        return $filename;
      }
    }
    return $attachment->post_title;
  }

  /**
   * Returns the filename (eg. "myimage.jpg") for the specified attachment.
   *
   * @param int $att_id the attachment id
   * @return string
   */
  public static function get_attachment_filename($att_id) {
    return basename(get_post_meta($att_id, '_wp_attached_file', true));
  }

  public static function get_fileicon_available_extensions($as_assoc_arr=false) {
    if (self::$fileicon_extensions === null) {
      $fileicons_dir = dirname(__FILE__).'/style/fileicons';
      self::$fileicon_extensions = array();
      foreach(scandir($fileicons_dir) as $filename) {
        if (substr($filename, -4) != '.png') {
          continue;
        }

        self::$fileicon_extensions[] = substr($filename, 0, -4);
      }
      self::$fileicon_extensions_assoc = array_flip(self::$fileicon_extensions);
    }

    return $as_assoc_arr ? self::$fileicon_extensions_assoc : self::$fileicon_extensions;
  }
}

?>
