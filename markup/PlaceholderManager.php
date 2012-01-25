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


class PlaceholderManager {
  /**
   * Identifies the beginning of a masked text section. Text sections are masked by surrounding an id with this and
   * {@link $SECTION_MASKING_END_DELIM}.
   * @var string
   * @see encode_placeholder()
   */
  private static $SECTION_MASKING_START_DELIM;
  /**
   * Identifies the end of a masked text section. Text sections are masked by surrounding an id with this and
   * {@link $SECTION_MASKING_START_DELIM}.
   * @var string
   * @see encode_placeholder()
   */
  private static $SECTION_MASKING_END_DELIM;

  /**
   *
   * @var mixed[]
   */
  private $m_placeholders = array();

  /**
   * Contains
   * @var <type>
   */
  private $text_positions = array();

  /**
   * Used to prevent the static constructor from running multiple times.
   * @var bool
   */
  private static $IS_STATIC_INITIALIZED = false;

  private static function static_constructor() {
    if (self::$IS_STATIC_INITIALIZED) {
      # Static constructor has already run.
      return;
    }

    # Adding some characters (here: "@@") to the delimiters gives us the ability to distinguish them both in the markup
    # text and also prevents the misinterpretation of real MD5 hashes that might be contained in the markup text.
    #
    # NOTE: The additional character(s) (@) must neither have a meaning in BlogText (so that it's not parsed by
    #   accident) nor must it have a meaning in a regular expression (again so that it's not parsed by accident).
    self::$SECTION_MASKING_START_DELIM = '@@'.md5('%%%');
    self::$SECTION_MASKING_END_DELIM = md5('%%%').'@@';

    self::$IS_STATIC_INITIALIZED = true;
  }

  public function __construct() {
    self::static_constructor();
  }

  public function clear() {
    $this->m_placeholders = array();
    $this->text_positions = array();
  }

  /**
   * Registers some text to be masked and returns a placeholder text. Only registered texts can be unmasked later. The
   * text to be masked must be replaced with the placeholder text that is returned.
   *
   * Text needs to be masked when it contains (or may contain) characters that form BlogText markup. Usually this
   * applies to programming code and URL in HTML attributes.
   *
   * @param string   $textToMask  the text to be masked
   * @param string   $textId  the id of the text. The placeholder returned by this method is based on this value.
   *   Defaults to the text itself. TODO: Why would we need this?
   * @param callback $textPostProcessingCallback  while unmasking this text, this callback function will be called to
   *   further process the text before putting it back in the whole text
   * @param bool     $determineTextPos  if this is true, the text position of the placeholder text will be determined
   *   and passed as second argument to the text post-processing callback. Has no effect, if no callback has been
   *   defined. Defaults to false.
   *
   * @return string  the placeholder text to replace the masked text until its unmasking
   *
   * @see unmaskAllTextSections()
   */
  public function registerMaskedText($textToMask, $textId = '', $textPostProcessingCallback=null,
                                      $determineTextPos=false) {
    if (empty($textId)) {
      $textId = $textToMask;
    }
    # Creating an MD5 hash from the text results in a unique textual representation of the masked text that doesn't
    # contain any BlogText markup.
    $placeholderId = md5($textId);

    # Register the masked text so that it can be unmasked later.
    $this->m_placeholders[$placeholderId] = array($textToMask, $textPostProcessingCallback, $determineTextPos);

    # Create and return the placeholder. Wrap it in the delimiter so that we can find it more easily and make it even
    # more unique.
    return self::$SECTION_MASKING_START_DELIM.$placeholderId.self::$SECTION_MASKING_END_DELIM;
  }

  /**
   * Unmasks all previously masked text section, i.e. restore their original text. Texts need to have been registered
   * with {@link registerMaskedText()} to be restored.
   *
   * @param string $markupText  the markup text for which text sections are to be unmasked
   *
   * @return string  the the markup text with all masked text sections now unmasked
   *
   * @see registerMaskedText()
   */
  public function unmaskAllTextSections($markupText) {
    foreach ($this->m_placeholders as $placeholderId => $maskedTextInfo) {
      list($unused, $callbackFunc, $requiresTextPos) = $maskedTextInfo;

      if ($requiresTextPos && $callbackFunc !== null) {
        // Encode line in the placeholders
        // NOTE: This is highly inefficient but we don't have any alternative for now.
        $search = self::$SECTION_MASKING_START_DELIM.$placeholderId.self::$SECTION_MASKING_END_DELIM;
        $pos = strpos($markupText, $search);
        if ($pos !== false) {
          $markupText = str_replace($search,
                  self::$SECTION_MASKING_START_DELIM.$placeholderId."_{$pos}_".self::$SECTION_MASKING_END_DELIM,
                  $markupText);
        }
      }
    }

    // NOTE: This must be done AFTER encoding the positions in the placeholders as this changes the text.
    $this->determine_text_positions($markupText);

    // NOTE: MD5 ist 32 hex chars (a - f, 0 - 9)
    $pattern = '/'.self::$SECTION_MASKING_START_DELIM.'([a-f0-9]{32})(?:_([0-9]+)_)?'.self::$SECTION_MASKING_END_DELIM.'/';
    return preg_replace_callback($pattern, array($this, 'unmaskTextSectionReplaceCallback'), $markupText);
  }

  /**
   * The callback function for {@link unmaskAllTextSections()}.
   *
   * @param string[] $matches
   *
   * @return string  the replacement text
   */
  private function unmaskTextSectionReplaceCallback($matches) {
    list($value, $callback_func, $requires_text_pos) = $this->m_placeholders[$matches[1]];
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

  public function add_text_position_request($text) {
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

  public function get_text_position($text) {
    if (isset($this->text_positions[$text])) {
      return $this->text_positions[$text];
    } else {
      return -1;
    }
  }

}
?>
