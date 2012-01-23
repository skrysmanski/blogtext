<?php
#
# This script converts the BlogText files in this directory into their HTML equivalent. This output is then
# used as regression test against previously converted versions of the same file. This helps to prevent 
# syntax problems resulting from code changes.
#

class BlogTextTests {
  /**
   * Returns the names of all available test cases.
   * 
   * @return array  the names as array of strings.
   */
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
    // you must first include the image.php file
    // for the function wp_generate_attachment_metadata() to work
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    
    // Unset the theme's content width as this may change from theme to theme. Will be reset at the end.
    global $content_width;
    $old_content_width = $content_width;
    $content_width = 0;
    
    if (empty($only_and_keep)) {
      $page_names = self::get_test_pages();
    } else {
      $page_names = array($only_and_keep);
    }
    
    $uploads = wp_upload_dir();
    $uploads_dir = $uploads['basedir'].'/blogtexttests';
    
    $added_attachment_ids = array();
    
    // Insert sample post so that we can query two to simulate a "multi-post" view.
    $my_post = array(
       'post_title' => '[DELETE ME] BlogText dummy test post',
       'post_content' => 'Just a test.',
       'post_status' => 'private'
    );

    $dummy_post_id = wp_insert_post($my_post);
    if ($dummy_post_id === 0) {
      die("Could not create dumy post");
    }
    
    
    foreach ($page_names as $page_name) {
      list($post_id, $post_attach_ids) = self::create_test_post($page_name, $uploads_dir);
      $added_attachment_ids = array_merge($added_attachment_ids, $post_attach_ids);
      
      self::write_output($post_id, $page_name, $dummy_post_id);
      
      if (empty($only_and_keep)) {
        # Don't delete the post when it has been requested.
        wp_delete_post($post_id, true);
        foreach ($added_attachment_ids as $attach_id) {
          wp_delete_attachment($attach_id, true);
        }
      } else {
        BlogTextPostSettings::set_use_blogtext($post_id, true);
      }
      
      $content_width = $old_content_width;
    }
    
    # Delete dummy post again
    wp_delete_post($dummy_post_id, true);
  }
  
  /**
   * Returns the directory containing all files of the specified test case.
   * 
   * @param string $page_name  the name of the test case/test page
   * @return string  the directory as string
   */
  private static function get_base_dir_for_page($page_name) {
    return dirname(__FILE__).'/test-pages/'.$page_name;
  }
  
  /**
   * Creates the test page for the specified test case. The test page's content is taken from the file
   * "blogtext.txt" in the directory belonging to the test case. It also inserts all files from the 
   * "uploads" directory into the Wordpress installation.
   * 
   * @param string $page_name  the noame of the test case/test page
   * @param string $uploads_dir
   * @return array  returns ($post_id, $added_attachment_ids) where "$post_id" is the id of the newly created
   *   post and "$added_attachment_ids" is an array containing the ids (type: int) of all the attachments 
   *   that were inserted.
   */
  private static function create_test_post($page_name, $uploads_dir) {
    $base_dir = self::get_base_dir_for_page($page_name);
    $filename = $base_dir.'/blogtext.txt';
    $contents = file_get_contents($filename);
    if ($contents === false) {
      die("Couldn't load file: ".$filename);
    }

    //
    // Insert the post into the database
    //
    // NOTE: We need to escape backslashes (\) when inserting the content. Otherwise they will be removed by
    //   "wp_insert_post()".
    $my_post = array(
       'post_title' => '[DELETE ME] BlogText test post: '.$page_name,
       'post_content' => str_replace('\\', '\\\\', $contents),
       'post_status' => 'private'
    );

    $post_id = wp_insert_post($my_post);
    if ($post_id === 0) {
      die("Could not create post for page: ".$page_name);
    }


    //
    // Insert media (images, attachments)
    //
    $media_dir = $base_dir.'/uploads';
    $added_attachment_ids = array();
    if (is_dir($media_dir)) {
      foreach (scandir($media_dir) as $media_name) {
        if ($media_name == '.' || $media_name == '..' 
            || substr($media_name, strlen($media_name) - 4) == '.txt') {
          continue;
        }

        $added_attachment_ids[] = self::insert_attachment($post_id, $media_dir, $media_name, 
                                                          $uploads_dir.'/'.$page_name);
      }
    }
    
    return array($post_id, $added_attachment_ids);
  }
  
  /**
   * Inserts the specified files as attachement into the Wordpress installation and links it to the specified
   * post.
   * 
   * If a file called "$src_filename.txt" exists, it'll be used as description for the file. If a file called
   * "$src_filename.alt.txt" exists, it'll be used as alt text for images.
   * 
   * @param int $post_id  the id of the newly created test post
   * @param string $src_dir  the directory from which to take the file to be inserted
   * @param string $src_filename  the file name of the file to be inserted. The file must exist in the source
   *   directory. The file will be copied to the dest directory (so that the source file isn't deleted when
   *   the attachment is deleted).
   * @param string $dest_dir  the directory where to copy the source file.
   * 
   * @return int  the id of the newly inserted attachement 
   */
  private static function insert_attachment($post_id, $src_dir, $src_filename, $dest_dir) {
    $src_file = $src_dir.'/'.$src_filename;
    $desc = @file_get_contents($src_file.'.txt');

    $wp_filetype = wp_check_filetype($src_filename, null);
    $attachment = array(
      'post_mime_type' => $wp_filetype['type'],
      'post_title' => preg_replace('/\.[^.]+$/', '', $src_filename),
      'post_content' => '',
      'post_excerpt' => $desc,
      'post_status' => 'inherit'
    );

    @mkdir($dest_dir);
    $dest_file = $dest_dir.'/'.$src_filename;
    # Copy the file so that it isn't deleted when we delete the attachment.
    copy($src_file, $dest_file);
    $attach_id = wp_insert_attachment($attachment, $dest_file, $post_id);
    $attach_data = wp_generate_attachment_metadata($attach_id, $dest_file);
    wp_update_attachment_metadata($attach_id, $attach_data); 

    // Add guid - otherwise the image won't have a file name. Also note that this
    // is probably a bug (in WP 3.1.4) that the GUID isn't set automatically.
    $my_post = array(
        'ID' => $attach_id,
        'guid' => wp_get_attachment_url($attach_id)
        );
    // Update the post into the database
    wp_update_post( $my_post );

    // Add ALT text for images
    $alt_text = @file_get_contents($src_file.'.alt.txt');
    if (!empty($alt_text)) {
      update_post_meta($attach_id, '_wp_attachment_image_alt', $alt_text);
    }
   
    return $attach_id;
  }
  
  /**
   * Converts the content of the specified post using BlogText and writes the result (HTML code) into a file.
   * The post will be converted with RENDER_KIND_REGULAR (single page; HTML output) and RENDER_KIND_RSS. 
   * 
   * If there is a file called "template.html" in the test case's directory, it'll be used as template for the 
   * HTML output. The keywords "###page_name###" and "###page_content###" will be replaced by the test case's
   * name and converted content respectively.
   * 
   * @param int $post_id  the id of the create test page
   * @param string $page_name  the name of the test case/test page
   * @param int  id of another (dummy) post to simulate a multi-post view
   */
  private static function write_output($post_id, $page_name, $dummy_post_id) {
    $base_dir = self::get_base_dir_for_page($page_name);

    // Obtain HTML template
    $template_code = '';
    if (file_exists($base_dir.'/template.html')) {
      $template_code = file_get_contents($base_dir.'/template.html');
    }
    
    //
    // Run "loop" through the post we've just created
    // FIRST: Single post view
    //

    // IMPORTANT: We can't create a "WP_Query" object here (but need to use "query_posts()") as the
    //   global function "is_singular()" (used by BlogText) only works on the global query object.
    query_posts('p='.$post_id);
    while (have_posts()) {
      the_post();
      global $post;

      try {
        $markup = new BlogTextMarkup();
        $output = $markup->convert_post_to_html($post, $post->post_content,
                                                AbstractTextMarkup::RENDER_KIND_REGULAR, 
                                                false);
        $output = self::mask_output($post_id, $output);

        if (!empty($template_code)) {
          $template_output = str_replace('###page_name###', $post->post_title, $template_code);
          $template_output = str_replace('###page_content###', $output, $template_output);
          file_put_contents($base_dir.'/output-single.html', $template_output);
        } else {
          file_put_contents($base_dir.'/output-single.html', $output);
        }
      } catch (Exception $e) {
        print MSCL_ErrorHandling::format_exception($e);
        // exit here as the exception may come from some static constructor that is only executed once
        exit;
      }

      break;
    }    

    //
    // Run "loop" through the post we've just created
    // SECOND: Multi post view and also RSS (which is always multi post view)
    //
    query_posts(array('post__in' => array($post_id, $dummy_post_id)));
    while (have_posts()) {
      the_post();
      global $post;
      
      if ($post->ID == $dummy_post_id) {
        // This is not the post we want.
        continue;
      }

      try {
        $markup = new BlogTextMarkup();
        $output = $markup->convert_post_to_html($post, $post->post_content,
                                                AbstractTextMarkup::RENDER_KIND_REGULAR, 
                                                false);
        $output = self::mask_output($post_id, $output);

        if (!empty($template_code)) {
          $template_output = str_replace('###page_name###', $post->post_title, $template_code);
          $template_output = str_replace('###page_content###', $output, $template_output);
          file_put_contents($base_dir.'/output-multi.html', $template_output);
        } else {
          file_put_contents($base_dir.'/output-multi.html', $output);
        }

        $output = $markup->convert_post_to_html($post, $post->post_content,
                                                AbstractTextMarkup::RENDER_KIND_RSS, 
                                                false);
        $output = self::mask_output($post_id, $output);

        file_put_contents($base_dir.'/output-rss.xml', $output);

      } catch (Exception $e) {
        print MSCL_ErrorHandling::format_exception($e);
        // exit here as the exception may come from some static constructor that is only executed once
        exit;
      }

      break;
    }    
  }
  
  /**
   * Removes/Masks any strings that may vary from server to server or from test run to test run. This includes
   * URLs to other posts or attachments as well as ids of posts or attachments.
   * 
   * @param int $post_id  the id of the test post whose output is being maskes
   * @param string $output  the actual output
   * @return string  the masked output 
   */
  private static function mask_output($post_id, $output) {
    # Make test result independent from the Wordpress URL
    $output = str_replace(site_url(), 'http://mydomain.com', $output);
   
    # Image tokens are domain dependent, too. So mask them.
    $output = preg_replace('#blogtext/api/thumbnail/do\.php\?id\=[0-9a-f]{40}_#i', 
                           'blogtext/api/thumbnail/do.php?id=XXX_', $output);
    
    # Mask ids to other posts, attachments, ...
    $output = preg_replace('#http://mydomain.com/\?(p|attachment_id)\=[0-9]+#',
                           'http://mydomain.com/?\1=XXX', $output);
    
    # Mask anchor/id names in multi post views
    # NOTE: Ids don't start with a hash sign (like in <img id="429_my_id">).
    $output = str_replace("${post_id}_", 'XXX_', $output);
    
    # Mask creation date
    $output = preg_replace('#^\s*<\!-- Generated "(.+)" item at .+ -->#iU',
                           '<!-- Generated "\1" item -->', $output);
    
    # Mask TOC
    $output = str_replace('_toctoggle_'.$post_id, '_toctoggle_XXX', $output);
    $output = str_replace('_toclist_'.$post_id, '_toclist_XXX', $output);
    $output = str_replace("javascript:toggle_toc($post_id);", 'javascript:toggle_toc(XXX);', $output);
    
    return $output;
  }
}
?>
