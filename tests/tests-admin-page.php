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
MSCL_Api::load(MSCL_Api::OPTIONS_API);

class BlogTextTestActionButtonsForm extends MSCL_ButtonsForm {
  const CLEAR_CACHE_BUTTON_NAME = 'run_tests';

  public function __construct() {
    parent::__construct('blogtext_test_action_buttons');

    $this->add_button(self::CLEAR_CACHE_BUTTON_NAME, 'Run Tests');
  }

  /**
   * Is being called, if the specified button has been clicked in the buttons form.
   */
  protected function on_button_clicked($button_id) {
    if ($button_id == self::CLEAR_CACHE_BUTTON_NAME) {
      MSCL_require_once('tests.php', __FILE__);
      if (array_key_exists('test_type', $_REQUEST)) {
        BlogTextTests::run_tests($_REQUEST['test_type'] == 'all' ? '' : $_REQUEST['test_type']);
      } else {
        BlogTextTests::run_tests();
      }
      
      $this->add_settings_error('tests_executed', __("All tests have been run."), 'updated');
      return;
    }
  }

  public function print_form_items() {
    MSCL_require_once('tests.php', __FILE__);
?>
  <input type="radio" name="test_type" value="all" checked="checked"> Run <b>all tests</b> but also remove the pages from the database again<br>
  <?php foreach (BlogTextTests::get_test_pages() as $page_name): ?>
  <input type="radio" name="test_type" value="<?php echo $page_name; ?>"> Run only test for <b>"<?php echo $page_name; ?>"</b> but keep the page<br>
  <?php endforeach; ?>    
<?php
  }
}

class BlogTextTestExecutionPage extends MSCL_OptionsPage {
  const PAGE_ID = 'blogtext_test_exec';
  const PAGE_NAME = 'BlogText Tests';
  const PAGE_TITLE = 'Execute BlogText Tests';

  public function __construct() {
    parent::__construct(self::PAGE_ID, self::PAGE_NAME, self::PAGE_TITLE, self::DEFAULT_CAPABILITY,
                        self::PARENT_CAT_TOOLS);
    $this->add_form(new BlogTextTestActionButtonsForm());
  }

  protected function print_forms() {
?>
<p class="description">Inserts posts and pages into this blog, runs BlogText on them, and stores the result.</p>
<?php
    MSCL_OptionsPage::print_forms();
  }
}

?>
