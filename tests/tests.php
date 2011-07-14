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
    // you must first include the image.php file
    // for the function wp_generate_attachment_metadata() to work
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    
    if (empty($only_and_keep)) {
      $page_names = self::get_test_pages();
    } else {
      $page_names = array($only_and_keep);
    }
    
    $uploads = wp_upload_dir();
    $uploads_dir = $uploads['basedir'].'/blogtexttests';
    @mkdir($uploads_dir);
    
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
          
          $src_filename = $media_dir.'/'.$media_name;
          $desc = @file_get_contents($src_filename.'.txt');
          
          $wp_filetype = wp_check_filetype(basename($media_name), null);
          $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', $media_name),
            'post_content' => '',
            'post_excerpt' => $desc,
            'post_status' => 'inherit'
          );
          
          $dest_filename = $uploads_dir.'/'.$media_name;
          # Copy the file so that it isn't deleted when we delete the attachment.
          copy($src_filename, $dest_filename);
          $attach_id = wp_insert_attachment($attachment, $dest_filename, $post_id);
          $attach_data = wp_generate_attachment_metadata($attach_id, $dest_filename);
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
          $alt_text = @file_get_contents($src_filename.'.alt.txt');
          if (!empty($alt_text)) {
            update_post_meta($attach_id, '_wp_attachment_image_alt', $alt_text);
          }
          
          $added_attachment_ids[] = $attach_id;
        }
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
          $output = self::clean_output($post_id, $output);
          
          file_put_contents($base_dir.'/output.html', $output);

          $output = $markup->convert_post_to_html($post, $contents, AbstractTextMarkup::RENDER_KIND_RSS, 
                                                  false);
          $output = self::clean_output($post_id, $output);
          
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
        foreach ($added_attachment_ids as $attach_id) {
          wp_delete_attachment($attach_id, true);
        }
      } else {
        BlogTextPostSettings::set_use_blogtext($post_id, true);
      }
    }
  }
  
  private static function clean_output($post_id, $output) {
    # Make test result independent from the Wordpress URL
    $output = str_replace(site_url(), 'http://mydomain.com', $output);
   
    # Image tokens are domain dependent, too. So mask them.
    $output = preg_replace('#blogtext/api/thumbnail/do\.php\?id\=[0-9a-f]{40}_#i', 
                           'blogtext/api/thumbnail/do.php?id=XXX_', $output);
    
    # Mask ids to other posts, attachments, ...
    $output = preg_replace('#http://mydomain.com/\?(p|attachment_id)\=[0-9]+#',
                           'http://mydomain.com/?\1=XXX', $output);
    
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
