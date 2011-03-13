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


class LinkNotFoundException extends Exception {
  const REASON_DONT_EXIST = 'don\'t exist';
  const REASON_NOT_PUBLISHED = 'unpublished';

  private $reason;
  private $title;

  /**
   * Constructor.
   *
   * @param string $reason  the more detailed reason why the link could not be found. Should only be a few
   *    words as they are added to the link title. Defaults to @see REASON_DONT_EXIST. When possible you
   *    should use one of the constants provided by this class.
   * @param string $title  the title of the link that could not be resolved, if known and not explicitely
   *   provided in the interlink. Otherwise "null".
   */
  public function __construct($reason=null, $title=null) {
    parent::__construct();
    if ($reason === null || empty($reason)) {
      $this->reason = self::REASON_DONT_EXIST;
    } else {
      $this->reason = $reason;
    }
    $this->title = $title;
  }

  public function get_reason() {
    return $this->reason;
  }

  public function get_title() {
    return $this->title;
  }
}

interface IInterlinkLinkResolver {
  /**
   * Represents a post in the current blog. The value equals to "$post->post_type".
   */
  const TYPE_POST = 'post';
  /**
   * Represents a page in the current blog. The value equals to "$post->post_type".
   */
  const TYPE_PAGE = 'page';
  /**
   * Represents an attachment. The value equals to "$post->post_type".
   */
  const TYPE_ATTACHMENT = 'attachment';

  /**
   * Returns the names of all prefixes handled by this resolver.
   *
   * @return array the prefixed (as array of strings)
   */
  public function get_handled_prefixes();

  /**
   * Resolves the specified interlink. Throws a "LinkNotFoundException" when the specified link target doesn't
   * exist.
   *
   * @param int $post_id  the id of the post/page the interlink is contained in
   * @param string $prefix  the prefix to be handled. Will only be one of the prefixes returned by
   *   @see get_handled_prefixes().
   * @param array $params  the parameters as specified in the interlink.
   *
   * @return array Returns an array containing the following items (in this order):
   *   url/link, title, is_external, type
   *
   *   "title" can be "null", if the last parameter is to be used as title.
   *   This is a convention and should not be violated. The exception would be when it's absolutely sure that
   *   the last parameter is actually a parameter and not the title. In this case the title should be returned
   *   (as non null).
   *
   *   "is_external" indicates whether the link is an external link or not. External links usually are opened
   *   in a new window while internal ones aren't. External usually means "outside of the current blog".
   *
   *   "type" should be one of this class' constants (eg. @see TYPE_POST). It can also be any other type but
   *   this type may not be recognized by the user of this class. Usually just added to the link's CSS
   *   classes.
   */
  public function resolve_link($post_id, $prefix, $params);
}


class WordpressLinkProvider implements IInterlinkLinkResolver {
  const TYPE_CATEGORY = 'category';
  const TYPE_TAG = 'tag';
  const TYPE_ARCHIVE = 'archive';
  const TYPE_BLOGROLL = 'blogroll';

  /**
   * Implements @see IInterlinkLinkResolver::get_handled_prefixes().
   */
  public function get_handled_prefixes() {
    return array('', 
                 'attachment', 'attach', 'att', 'file',
                 'category', 'tag');
  }
  
  /**
   * Implements @see IInterlinkLinkResolver::resolve_link().
   */
  public function resolve_link($post_id, $prefix, $params) {
    // TODO: Add support for blogroll (links; see get_bookmarks()) and archive (see get_year_link())
    switch ($prefix) {
      case '':
        return $this->resolve_regular_link($params);

      case 'att':
      case 'attach':
      case 'attachment':
      case 'file':
        return $this->resolve_attachment_link($params, $post_id);

      case 'category':
        return $this->resolve_category_link($params);

      case 'tag':
        return $this->resolve_tag_link($params);

      default:
        throw new Exception('Unexpected prefix: '.$prefix);
    }
  }

  private function resolve_regular_link($params) {
    $link = null;
    $title = null;
    $is_external = false;
    $type = null;

    $ref_parts = explode('#', $params[0], 2);
    if (count($ref_parts) == 2) {
      $page_id = $ref_parts[0];
      $anchor = $ref_parts[1];
    } else {
      $page_id = $params[0];
      $anchor = '';
    }

    $post = MarkupUtil::get_post($page_id);
    if ($post === null) {
      // post not found
      throw new LinkNotFoundException();
    }

    $is_attachment = MarkupUtil::is_attachment_type($post->post_type);

    // Determine title - but only if the title wasn't specified explicitely.
    if (count($params) == 1) {
      if ($is_attachment) {
        $title = MarkupUtil::get_attachment_title($post);
      } else {
        $title = apply_filters('the_title', $post->post_title);
      }

      if(empty($title)) {
        $title = $page_id;
      }
    }

    // Posting must be published. Ignore the status for attachments.
    if ($is_attachment) {
      // attachment
      // NOTE: Unlike the "attachment:" prefix this doesn't link directly to the attached file but to a
      //   description page for this attachment.
      $link = get_attachment_link($post->ID);
      $type = IInterlinkLinkResolver::TYPE_ATTACHMENT;
    } else if ($post->post_status == 'publish') {
      // post or page
      $link = get_permalink($post->ID);
      // post_type: post|page|attachment
      $type = $post->post_type;
    } else {
      throw new LinkNotFoundException(LinkNotFoundException::REASON_NOT_PUBLISHED, $title);
    }

    if (!empty($anchor) && !$is_attachment) {
      // append anchor - but not for attachments
      $link .= '#'.$anchor;
    }

    return array($link, $title, $is_external, $type);
  }

  /**
   * Resolves a "attachment:" link. Note that the difference to "resolve_regular_link()" is that this method
   * also allows for the full filename to be used, checks whether the specified link is actually an
   * attachment, and allows a # in the name (what "resolve_regular_link()" interprets as HTML anchor).
   */
  private function resolve_attachment_link($params, $post_id) {
    $link = null;
    $title = null;
    $is_external = false;
    $type = IInterlinkLinkResolver::TYPE_ATTACHMENT;

    $att_id = MarkupUtil::get_attachment_id($params[0], $post_id);
    if ($att_id === null) {
      // attachment not found
      throw new LinkNotFoundException();
    }

    // Determine title - but only if the title wasn't specified explicitely.
    if (count($params) == 1) {
      $title = MarkupUtil::get_attachment_title($att_id);
    }

    $link = wp_get_attachment_url($att_id);

    return array($link, $title, $is_external, $type);
  }

  private function resolve_category_link($params) {
    $link = null;
    $title = null;
    $is_external = false;
    $type = self::TYPE_CATEGORY;

    // Get the ID of a given category
    if (is_numeric($params[0])) {
      $category_id = (int)$params[0];
      if (!is_category($category_id)) {
        throw new LinkNotFoundException();
      }
    } else {
      $category_id = get_cat_ID($params[0]);
      if ($category_id == 0) {
        throw new LinkNotFoundException();
      }
    }

    // Get the URL of this category
    $link = get_category_link($category_id);
    if (count($params) == 1) {
      $title = get_cat_name($category_id);
    }

    return array($link, $title, $is_external, $type);
  }

  private function resolve_tag_link($params) {
    $link = null;
    $title = null;
    $is_external = false;
    $type = self::TYPE_TAG;

    // Get the ID of a given category
    if (is_numeric($params[0])) {
      $tag_id = (int)$params[0];
      $tag = get_tag($tag_id);
      if ($tag === null) {
        throw new LinkNotFoundException();
      }
    } else {
      $tag = get_term_by('name', $params[0], 'post_tag');
      if ($tag == false) {
        throw new LinkNotFoundException();
      }
      $tag_id = $tag->term_id;
    }

    // Get the URL of this category
    $link = get_tag_link($tag_id);
    if (count($params) == 1) {
      $title = $tag->name;
    }

    return array($link, $title, $is_external, $type);
  }
}
?>
