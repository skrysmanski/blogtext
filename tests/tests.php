<?php
#
# This script converts the BlogText files in this directory into their HTML equivalent. This output is then
# used as regression test against previously converted versions of the same file. This helps to prevent 
# syntax problems resulting from code changes.
#

class BlogTextTests {
  public static function get_test_pages() {
    $base_dir = dirname(__FILE__).'/test-pages';
    $test_names = array();
    foreach (scandir($base_dir) as $name) {
      if ($name == '.' || $name == '..') {
        continue;
      }
      if (!file_exists($base_dir.'/'.$name.'/blogtext.txt')) {
        continue;
      }
      
      $test_names[] = $name;
    }
    return $test_names;
  }
  
  public static function run_tests($only_and_keep = '') {
    require_once(dirname(__FILE__).'/../markup/blogtext_markup.php');
    require_once(dirname(__FILE__).'/../settings.php');
    
    if (empty($only_and_keep)) {
      $page_names = self::get_test_pages();
    } else {
      $page_names = array($only_and_keep);
    }
    
    foreach ($page_names as $name) {
      $base_dir = dirname(__FILE__).'/test-pages/'.$name;
      $filename = $base_dir.'/blogtext.txt';
      $contents = file_get_contents($filename);
      if ($contents === false) {
        die("Couldn't load file: ".$filename);
      }
      
      //
      // Insert the post into the database
      //
      $my_post = array(
         'post_title' => 'BlogText test post: '.$name,
         'post_content' => $contents,
         'post_status' => 'private'
      );

      $post_id = wp_insert_post($my_post);
      if ($post_id === 0) {
        die("Could not create post for page: ".$name);
      }
      
      //
      // Run "loop" through the post we've just created
      //
      
      // IMPORTANT: We can't create a "WP_Query" object here (but need to use "query_posts()") as the
      //   global function "is_singular()" (used by BlogText) only works on the global query object.
      query_posts('p='.$post_id);
      while (have_posts()) {
        the_post();
        global $post;
        
        try {
          $markup = new BlogTextMarkup();
          $output = $markup->convert_post_to_html($post, $contents, AbstractTextMarkup::RENDER_KIND_REGULAR, 
                                                  false);
          
          file_put_contents($base_dir.'/output.html', $output);

          $output = $markup->convert_post_to_html($post, $contents, AbstractTextMarkup::RENDER_KIND_RSS, 
                                                  false);
          
          file_put_contents($base_dir.'/output-rss.xml', $output);

        } catch (Exception $e) {
          print MSCL_ErrorHandling::format_exception($e);
          // exit here as the exception may come from some static constructor that is only executed once
          exit;
        }
        
        unset($output);
        break;
      }
      
      //
      // Get RSS
      //
      
      if (empty($only_and_keep)) {
        # Don't delete the post when it has been requested.
        wp_delete_post($post_id, true);
      } else {
        BlogTextPostSettings::set_use_blogtext($post_id, true);
      }
    }
  }
}
?>
