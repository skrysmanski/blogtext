<?php

class TextPostionManager {
  /**
   * Contains positions for each requested text. Is filled by {@link determineTextPositions()}.
   * @var string[]
   */
  private $m_textPositions = array();

  /**
   * Maps from a text id to the actual text to be search. Only contains entries for text that actually have an id that
   * differs from the text to be search.
   * @var string[]
   */
  private $m_textIds = array();

  public function reset() {
    $this->m_textPositions = array();
    $this->m_textIds = array();
  }

  /**
   * Requests that the position of the text passed to this method to be determined when {@link addTextPositionRequest()}
   * is called. Only finds the first occurrence of this text.
   *
   * @param string $text  the text for which the text position is to be determined. Note: This text itself should not
   *   be the id for another text; this way both the text and its id can be used for {@link getTextPosition()}.
   * @param string $textId  optional id for the text; if this is "null" the text itself is used as id. The id is used to
   *   retrieve the text position in {@link getTextPosition()}.
   */
  public function addTextPositionRequest($text, $textId = null) {
    $this->m_textPositions[$text] = -1;
    if (!empty($textId)) {
      $this->m_textIds[$textId] = $text;
    }
  }

  /**
   * Determines the positions for all texts that have been requested via {@link addTextPositionRequest()}. Note that
   * this method must be called before {@link getTextPosition()} can be used.
   *
   * @param string $haystack  the text for which the text positions are to be determined
   */
  public function determineTextPositions($haystack) {
    foreach ($this->m_textPositions as $text => $unused) {
      $pos = strpos($haystack, $text);
      if ($pos !== false) {
        $this->m_textPositions[$text] = $pos;
      }
    }
  }

  /**
   * Returns the text position for the specified text id. Only works after {@link determineTextPositions()} has been
   * called.
   *
   * @param string $textId  the id of the text for which the position is to be returned. Note that this text can be
   *   the text itself, or a text id associated with the text in {@link addTextPositionRequest()}. Note also that even
   *   if an id has been associated with the text, you still can pass the text here directly - unless the text itself
   *   is an id for another text.
   *
   * @return int  the text position or -1, if the position is unknown or the text id isn't registered
   */
  public function getTextPosition($textId) {
    if (isset($this->m_textIds[$textId])) {
      $text = $this->m_textIds[$textId];
    }
    else {
      $text = $textId;
    }

    if (isset($this->m_textPositions[$text])) {
      return $this->m_textPositions[$text];
    }
    else {
      return -1;
    }
  }
}

?>
