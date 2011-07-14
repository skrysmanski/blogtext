<?php
#
# This script converts the BlogText files in this directory into their HTML equivalent. This output is then
# used as regression test against previously converted versions of the same file. This helps to prevent 
# syntax problems resulting from code changes.
#

class BlogTextTests {
  public static function run_tests() {
    require_once(dirname(__FILE__).'/../markup/blogtext_markup.php');
    
    $page_names = array('syntax-description');
    
    foreach ($page_names as $name) {
      $filename = dirname(__FILE__).'/'.$name.'.txt';
      $contents = file_get_contents($filename);
      if ($contents === false) {
        die("Couldn't load file: ".$filename);
      }
      
      //
      // Insert the post into the database
      //
      $my_post = array(
         'post_title' => 'My BlogText test post ('.date('H:i:s.u').')',
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
          
          file_put_contents(dirname(__FILE__).'/'.$name.'-output.html', $output);

          $output = $markup->convert_post_to_html($post, $contents, AbstractTextMarkup::RENDER_KIND_RSS, 
                                                  false);
          
          file_put_contents(dirname(__FILE__).'/'.$name.'-rss.xml', $output);

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
      
      wp_delete_post($post_id, true);
    }
  }
}
?>
