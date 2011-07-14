<?php
#
# This script converts the BlogText files in this directory into their HTML equivalent. This output is then
# used as regression test against previously converted versions of the same file. This helps to prevent 
# syntax problems resulting from code changes.
#

class BlogTextTests {
  public static function run_tests() {
    require_once(dirname(__FILE__).'/../markup/blogtext_markup.php');
    
    $my_post = array(
       'post_title' => 'My post',
       'post_content' => 'This is my post.',
       'post_status' => 'private'
    );

    // Insert the post into the database
    wp_insert_post($my_post);
  }
}
?>
