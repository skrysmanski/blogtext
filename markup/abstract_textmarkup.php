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

class ATM_ListItem {
  const LIST_ITEM_TYPE_LI = 'li';
  const LIST_ITEM_TYPE_DT = 'dt';
  const LIST_ITEM_TYPE_DD = 'dd';

  public $item_type;
  public $contents = array();

  public function __construct($item_type) {
    $this->item_type = $item_type;
  }

  public function append(&$content) {
    if (is_string($content)) {
      // text - no sublist; check whether to append to the previous text section or open a new text section
      if ($content == "\n") {
        // new paragraph
        $content = '';
      } else if (count($this->contents) != 0 && is_string($this->contents[count($this->contents) - 1])) {
        // last contents was text - so append it there
        $this->contents[count($this->contents) - 1] .= $content;
        return;
      }
      $content = trim($content);
    }
    $this->contents[] = &$content;
  }
}

class ATM_List {
  const LIST_TYPE_OL = 'ol';
  const LIST_TYPE_UL = 'ul';
  const LIST_TYPE_DL = 'dl';

  /**
   * The type of this list. One of the LIST_TYPE constants.
   * @var string
   */
  public $list_type;
  public $items = array();

  public function  __construct($list_type) {
    $this->list_type = $list_type;
  }

  public function append_new_item(&$item) {
    // Check for mismatching types
    switch ($this->list_type) {
      case self::LIST_TYPE_UL:
      case self::LIST_TYPE_OL:
        if ($item->item_type != ATM_ListItem::LIST_ITEM_TYPE_LI) {
          throw new Exception("Invalid list nesting. <$item->item_type> not allowed in <$this->list_type>.");
        }
        break;
      case self::LIST_TYPE_DL:
        if (   $item->item_type != ATM_ListItem::LIST_ITEM_TYPE_DT
            && $item->item_type != ATM_ListItem::LIST_ITEM_TYPE_DD) {
          throw new Exception("Invalid list nesting. <$item->item_type> not allowed in <$this->list_type>.");
        }
        break;
      default:
        throw new Exception("Missing type: $this->list_type");
    }

    $this->items[] = &$item;
  }

  public function append_to_last_item(&$content) {
    if ($content instanceof ATM_ListItem) {
      throw new Exception("You can't append items to items. Use append_new_item() instead.");
    }
    if (count($this->items) == 0) {
      switch ($this->list_type) {
        case self::LIST_TYPE_UL:
        case self::LIST_TYPE_OL:
          $last_item = new ATM_ListItem(ATM_ListItem::LIST_ITEM_TYPE_LI);
          break;
        case self::LIST_TYPE_DL:
          $last_item = new ATM_ListItem(ATM_ListItem::LIST_ITEM_TYPE_DT);
          break;
        default:
          throw new Exception("Missing type: $this->list_type");
      }
      $this->items[] = $last_item;
    } else {
      $last_item = $this->items[count($this->items) - 1];
    }
    $last_item->append($content);
  }
}

class ATM_ListStack {
  const UNIQUE_ITEM_TYPE_UL = 'ul';
  const UNIQUE_ITEM_TYPE_OL = 'ol';
  const UNIQUE_ITEM_TYPE_DT = 'dt';
  const UNIQUE_ITEM_TYPE_DD = 'dd';

  public $root_items = array();

  private $list_stack = array();

  private static function get_list_type($unique_item_type) {
    switch ($unique_item_type) {
      case self::UNIQUE_ITEM_TYPE_UL:
        return ATM_List::LIST_TYPE_UL;
      case self::UNIQUE_ITEM_TYPE_OL:
        return ATM_List::LIST_TYPE_OL;
      case self::UNIQUE_ITEM_TYPE_DT:
      case self::UNIQUE_ITEM_TYPE_DD:
        return ATM_List::LIST_TYPE_DL;
    }
    throw new Exception(); // missing/invalid char
  }

  private static function list_type_eq($unique_item_type, $list_type) {
    return (self::get_list_type($unique_item_type) == $list_type);
  }

  private static function get_list_item_type($unique_item_type) {
    switch ($unique_item_type) {
      case self::UNIQUE_ITEM_TYPE_UL:
      case self::UNIQUE_ITEM_TYPE_OL:
        return ATM_ListItem::LIST_ITEM_TYPE_LI;
      case self::UNIQUE_ITEM_TYPE_DT:
        return ATM_ListItem::LIST_ITEM_TYPE_DT;
      case self::UNIQUE_ITEM_TYPE_DD:
        return ATM_ListItem::LIST_ITEM_TYPE_DD;
    }
    throw new Exception(); // missing/invalid char
  }

  private static function is_def_list($unique_item_type) {
    switch ($unique_item_type) {
      case self::UNIQUE_ITEM_TYPE_DT:
      case self::UNIQUE_ITEM_TYPE_DD:
        return true;
    }
    return false;
  }

  private function append(&$content) {
    if (count($this->list_stack) == 0) {
      $this->root_items[] = &$content;
    } else {
      $this->list_stack[count($this->list_stack) - 1][1]->append_to_last_item($content);
    }
  }

  /**
   * Appends a new list item to the list specified by the list types. If the list specified by the list types
   * isn't open yet, it'll be created. Currently open list that don't match the specified list will be closed.
   *
   * @param array $level_item_types array of UNIQUE_ITEM_TYPES that represent the list nesting (eg.
   *   array(UL, UL, OL)).
   * @param string $text text for this list item; can be empty.
   */
  public function append_new_item($new_level_item_types, $continue_list, $text='') {
    // different lists/list items
    $cur_lvl = count($new_level_item_types);
    $prev_lvl = count($this->list_stack);
    $min_lvl = min($cur_lvl, $prev_lvl);

    // search for max common prefix
    // eg. for:
    // UL,OL,DT,OL
    // UL,OL,DD,OL
    // this would be UL,OL,DT/DD
    $common_lvl = 0;
    while (   $common_lvl < $min_lvl
           && $new_level_item_types[$common_lvl] == $this->list_stack[$common_lvl][0]) {
      $common_lvl++;
    }
    if (   $common_lvl < $min_lvl
        && self::is_def_list($new_level_item_types[$common_lvl])
        && self::is_def_list($this->list_stack[$common_lvl][0])) {
      // special case: most common element is a definition list but the new item is a <dd> where the last item
      // was a <dt> (or the other way around). So don't close and reopen the same definition list.
      // NOTE: This must only be check for the last(!) common element; ie. the lists UL,DT,DT and UL,DD,DD
      //   only have the common list UL,DT/DD (and not UL,DT/DD,DT/DD; ie. the third level definition list
      //   is not continued over the second level def list (which would not make sense))
      $common_lvl++;
    }

    // close previous non-common suffix
    // with common prefix "**#" and current type "**##" close "#"
    while(count($this->list_stack) > $common_lvl) {
      array_pop($this->list_stack);
    }

    if ($common_lvl == $cur_lvl) {
      // same level (and same list types)
      $opened_unique_item_type = $new_level_item_types[$common_lvl - 1];
      if (!$continue_list || $opened_unique_item_type != $this->list_stack[$common_lvl - 1][0]) {
        // only add new item, if not continuing or the types don't match (ie. you can't continue a <ul>, if
        // the currently open list is <ol> list)
        $this->list_stack[$common_lvl - 1][1]->append_new_item(new ATM_ListItem(self::get_list_item_type($opened_unique_item_type)));
      }
    } else if ($common_lvl != 0 && self::is_def_list($new_level_item_types[$common_lvl - 1])) {
      // Special case: The most common element is a definition list, but the previous and the new item types
      // aren't the same (ie. <dt> -> <dd> or <dd> -> <dt>). (If they were the same, we would go in the
      // if-branch instead of here.)
      //
      // Now we need to open the the new item type before opening the nested lists.
      $opened_unique_item_type = $new_level_item_types[$common_lvl - 1];
      $this->list_stack[$common_lvl - 1][1]->append_new_item(new ATM_ListItem(self::get_list_item_type($opened_unique_item_type)));
    }

    if ($common_lvl < $cur_lvl) {
      // open current non-common suffix (new list type)
      // with common prefix "**#" and current type "**#*" open new "*"
      for ($i = $common_lvl; $i < $cur_lvl; $i++) {
        $opened_unique_item_type = $new_level_item_types[$i];
        $cur_open_list = new ATM_List(self::get_list_type($opened_unique_item_type));
        $cur_open_list->append_new_item(new ATM_ListItem(self::get_list_item_type($opened_unique_item_type)));
        $this->append($cur_open_list);
        $this->list_stack[] = array($opened_unique_item_type, &$cur_open_list);
      }
    }

    // add text, if any
    if (!empty($text)) {
      $this->append_text($text);
    }
  }

  /**
   * Appends either a text string to the last open item. If no list are currently open, the content will be
   * added as root element.
   *
   * @param string $text the new content
   */
  public function append_text($text) {
    $this->append($text);
  }

  public function append_para() {
    $this->append_text("\n");
  }
  
  public function close_lists($count) {
    if (count($this->list_stack) < $count) {
      throw new Exception("You're closing too many lists.");
    }
    
    for ($i = 0; $i < $count; $i++) {
      array_pop($this->list_stack);
    }
  }

  public function has_open_lists() {
    return count($this->list_stack) != 0;
  }
}
?>
