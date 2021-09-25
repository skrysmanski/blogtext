<?php
class ATM_TableCell {
  const TYPE_TD = 'td';
  const TYPE_TH = 'th';

  public $cell_type;
  public $cell_content;
  public $tag_attributes = '';

  public function __construct($cell_type, $cell_content) {
    $this->cell_type = $cell_type;
    $this->cell_content = trim(strval($cell_content));
  }

  public function isEmpty() {
    return $this->cell_content === "";
  }
}

class ATM_TableRow {
  public $cells = array();
  public $tag_attributes = '';
}

class ATM_Table {
  public $rows = array();
  public $tag_attributes = '';
  public $caption = '';
}
?>
