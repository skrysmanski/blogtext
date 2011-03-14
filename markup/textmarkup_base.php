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
require_once(dirname(__FILE__).'/table_base.php');


abstract class AbstractTextMarkup {

  public static function generate_error_html($message, $additional_css='') {
    if (!empty($additional_css)) {
      return '<span class="error '.$additional_css.'">'.$message.'</span>';
    }

    return '<span class="error">'.$message.'</span>';
  }


  ////////////////////////////////////////////////////////////////////
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

  /**
   * Registers an interlink handler, that is either a resolver or a macro.
   * @param <type> $interlinks
   * @param <type> $handler
   */
  protected static function register_interlink_handler(&$interlinks, $handler) {
    foreach ($handler->get_handled_prefixes() as $prefix) {
      self::register_interlink($interlinks, $prefix, $handler);
    }
  }


  ////////////////////////////////////////////////////////////////////
  //
  // Convert method
  //
  public abstract function convert_post_to_html($post, $markup_content, $is_rss, $is_excerpt);


  ////////////////////////////////////////////////////////////////////
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


  ////////////////////////////////////////////////////////////////////
  //
  // Tables
  //

  protected function generate_table_code($table) {
    $code = $this->open_table($table->tag_attributes, $table->caption);

    $max_columns = 0;
    foreach ($table->rows as $row) {
      $cell_count = count($row->cells);
      if ($cell_count > $max_columns) {
        $max_columns = $cell_count;
      }
    }

    $has_table_head = true;
    $row_nr = 0;
    foreach ($table->rows as $row) {
      if ($row_nr == 0) {
        // check for table head - ie. the first row being only <th> elements
        foreach ($row->cells as $cell) {
          if ($cell->cell_type != ATM_TableCell::TYPE_TH) {
            $has_table_head = false;
            break;
          }
        }

        if ($has_table_head) {
          $code .= $this->open_table_section('thead');
        }
      } else if ($row_nr == 1 && $has_table_head) {
        $code .= $this->open_table_section('tbody');
      }

      $code .= $this->open_table_row($row->tag_attributes, $row_nr);

      $cell_nr = 0;
      foreach ($row->cells as $cell) {
        $code .= $this->open_table_cell($cell->cell_type, $cell->tag_attributes, $row_nr, $cell_nr)
               . $cell->cell_content
               . $this->close_table_cell($cell->cell_type, $row_nr, $cell_nr);
        $cell_nr++;
      }

      // fill remaining cells for which no content was provided
      for (; $cell_nr < $max_columns; $cell_nr++) {
        $cell_type = ($row_nr == 0 && $has_table_head ? ATM_TableCell::TYPE_TH : ATM_TableCell::TYPE_TD);
        $code .= $this->open_table_cell($cell_type, '', $row_nr, $cell_nr)
               . '&nbsp;'
               . $this->close_table_cell($cell_type, $row_nr, $cell_nr);
      }

      $code .= $this->close_table_row($row_nr);

      if ($row_nr == 0 && $has_table_head) {
        $code .= $this->close_table_section('thead');
      }

      $row_nr++;
    }

    if ($has_table_head && $row_nr > 1) {
      $code .= $this->close_table_section('tbody');
    }

    return $code.$this->close_table($table->caption);
  }

  protected function open_table($tag_attributes, $caption) {
    if (empty($tag_attributes)) {
      return "\n<table>\n";
    } else {
      return "\n<table $tag_attributes>\n";
    }
  }

  protected function close_table($caption) {
    $code = "</table>\n";
    if (!empty($caption)) {
      $code .= '<p class="table-caption">'.$caption."</p>\n";
    }
    return $code;
  }

  protected function open_table_section($section_type) {
    return "<$section_type>\n";
  }

  protected function close_table_section($section_type) {
    return "</$section_type>\n";
  }

  protected function open_table_row($tag_attributes, $row_nr) {
    if (empty($tag_attributes)) {
      return "<tr>\n";
    } else {
      return "<tr $tag_attributes>\n";
    }
  }

  protected function close_table_row($row_nr) {
    return "</tr>\n";
  }

  protected function open_table_cell($type, $tag_attributes, $row_nr, $cell_nr) {
    // NOTE: No "\n" after the tag; we don't want to introduce unnecessary white space to the cell's content
    if (empty($tag_attributes)) {
      return "<$type>";
    } else {
      return "<$type $tag_attributes>";
    }
  }

  protected function close_table_cell($type, $row_nr, $cell_nr) {
    return "</$type>\n";
  }
}
?>
