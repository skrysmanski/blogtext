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

require_once(dirname(__FILE__).'/list_base.php');



abstract class AbstractTextMarkup {
  //
  // Interlinks
  //

  protected static function register_interlink(&$interlinks, $prefix, $handler) {
    $interlinks[$prefix] = $handler;
  }

  protected static function register_interlink_pattern(&$interlinks, $prefix, $pattern, $is_external,
                                                       $highest_para_num) {
    $interlinks[$prefix] = array('pattern' => $pattern, 'external' => $is_external,
                                 'highest' => $highest_para_num);
  }

  protected static function register_all_interlink_patterns(&$interlinks) {
    foreach (BlogTextSettings::get_interlinks() as $prefix => $data) {
      $pattern = $data[0];

      // find the hightest parameter number
      // NOTE: This doesn't need to be the same as the number of parameters as the user may has an interlink
      //   like this: http://www.mydomain/$3 (which only has one parameter but the highest number is three).
      $highest_para_num = 0;
      preg_match_all('/\$([0-9]+)/', $pattern, $matches, PREG_SET_ORDER);
      foreach ($matches as $match) {
        $num = (int)$match[1];
        if ($num > $highest_para_num) {
          $highest_para_num = $num;
        }
      }

      self::register_interlink_pattern($interlinks, $prefix, $pattern, $data[1], $highest_para_num);
    }
  }

  protected static function register_interlink_resolver(&$interlinks, $resolver) {
    foreach ($resolver->get_handled_prefixes() as $prefix) {
      self::register_interlink($interlinks, $prefix, $resolver);
    }
  }


  //
  // Convert method
  //
  public abstract function convert_post_to_html($post, $markup_content, $is_rss, $is_excerpt);

  //
  // Lists
  //
  
  /**
   * Generates the HTML code for the specified list.
   * 
   * @param ATM_List $list the list
   */
  protected function generate_list_code($list, $ignore_empty_items=true) {
    $code = $this->open_list($list->list_type, '');
    $counter = 0;
    foreach ($list->items as $item) {
      $css = '';
      if (count($list->items) > 1) {
        if ($counter == 0) {
          $css = ' class="first-item"';
        } else if ($counter == count($list->items) - 1) {
          $css = ' class="last-item"';
        }
      }
      $counter++;

      if (count($item->contents) == 0) {
        if ($ignore_empty_items) {
          continue;
        }
        $inner_code = '';
      } else if (count($item->contents) == 1) {
        if ($item->contents[0] instanceof ATM_List) {
          $inner_code = $this->generate_list_code($item->contents[0], $ignore_empty_items);
        } else {
          // Don't surround list items containing only one paragraph with <p> tags. This keeps the list
          // more "dense", since <p> tags usually have a margin.
          $inner_code = trim($item->contents[0]);
        }
      } else {
        // more than one element
        $inner_code = '';
        $prepared_content = array();
        for ($i = 0; $i < count($item->contents) - 1; $i++) {
          // special handling for items with more than one content - we only want paragraph (one empty line)
          // if the user added it explicitly. We also assume that <p> tags use margin-bottom rather than
          // margin-top to achieve their margin.
          $cur_content = $item->contents[$i];
          if (is_string($cur_content) && is_string($item->contents[$i + 1])) {
            // the user added a paragraph explicitely or the next content is text. add a paragraph
            if (!empty($cur_content)) {
              // only add this string if it's not empty; we don't need the empty marker strings in our
              // prepared content
              $prepared_content[] = array($cur_content, true);
            }
          } else {
            $prepared_content[] = array($cur_content, false);
          }
        }
        // add last element as it isn't added in the loop
        if (!empty($item->contents[count($item->contents) - 1])) {
          $prepared_content[] = array($item->contents[count($item->contents) - 1], false);
        }

        foreach ($prepared_content as $content_arr) {
          list($content, $use_para) = $content_arr;
          if ($content instanceof ATM_List) {
            $inner_code .= $this->generate_list_code($content, $ignore_empty_items);
          } else {
            if ($use_para) {
              $inner_code .= '<p>'.$content.'</p>';
            } else {
              // NOTE: Wordpress will screw this code up, if no <p> tags are inserted; and although I'm not
              //   sure about this, adding <p> tags seems to be the right thing to do when mixing inline text
              //   with block tags (ie. sublists).
              $inner_code .= '<p class="no-margin">'.$content.'</p>';
            }
          }
        }
      }

      if ($ignore_empty_items && empty($inner_code)) {
        continue;
      }

      $code .= $this->open_list_item($item->item_type, $css)
            .  $inner_code
            .  $this->close_list_item($item->item_type);
    }
    $code .= $this->close_list($list->list_type);

    return $code;
  }

  /**
   * Return the open tag of a list
   */
  protected function open_list($type, $css) {
    switch ($type) {
      case ATM_List::LIST_TYPE_UL:
        return "<ul$css>";
      case ATM_List::LIST_TYPE_OL:
        return "<ol$css>";
      case ATM_List::LIST_TYPE_DL:
        return "<dl$css>";
    }
    throw new Exception();
  }

  /**
   * Return the closing tag of a list
   */
  protected function close_list($type) {
    switch ($type) {
      case ATM_List::LIST_TYPE_UL:
        return '</ul>';
      case ATM_List::LIST_TYPE_OL:
        return '</ol>';
      case ATM_List::LIST_TYPE_DL:
        return '</dl>';
    }
    throw new Exception();
  }

  /**
   * Return the open tag for list item
   */
  protected function open_list_item($type, $css) {
    switch ($type) {
      case ATM_ListItem::LIST_ITEM_TYPE_LI:
        return "<li$css>";
      case ATM_ListItem::LIST_ITEM_TYPE_DT:
        return "<dt$css>";
      case ATM_ListItem::LIST_ITEM_TYPE_DD:
        return "<dd$css>";
    }
    throw new Exception();
  }

  /**
   * Return the closing tag for list item
   */
  protected function close_list_item($type) {
    switch ($type) {
      case ATM_ListItem::LIST_ITEM_TYPE_LI:
        return '</li>';
      case ATM_ListItem::LIST_ITEM_TYPE_DT:
        return '</dt>';
      case ATM_ListItem::LIST_ITEM_TYPE_DD:
        return '</dd>';
    }
    throw new Exception();
  }
}
?>
