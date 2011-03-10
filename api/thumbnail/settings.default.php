<?php
//
// Settings for the thumbnail API.
// NOTE: Since Wordpress may not be loaded, these settings can't be implemented using "get_option()". For
//   customizing these settings, create a "settings.custom.php" file in this directory and define the
//   constants you like to modify.
//


// The name of the cache directory inside the upload dir (for local and for remote file). May be the same
// directory.
if (!defined('LOCAL_IMG_CACHE_DIR')) {
  define('LOCAL_IMG_CACHE_DIR', 'thumb_cache/local');
}
if (!defined('REMOTE_IMG_CACHE_DIR')) {
  define('REMOTE_IMG_CACHE_DIR', 'thumb_cache/remote');
}

// Specifies the amount of seconds after which a remote image should be checked again for changes. Before this
// timeout expires, the remote image is considered unchanged. There are several values here:
// * value > 0: works as specified above
// * value = 0: the remote image is checked every time
// * value < 0: update checks are triggered manually, for example when publishing/updating a post
if (!defined('REMOTE_IMAGE_TIMEOUT')) {
  define('REMOTE_IMAGE_TIMEOUT', -1);
}

// Specifies the JPEG quality (0 - 100) to be used. The higher the value the better the quality.
if (!defined('JPEG_QUALITY')) {
  define('JPEG_QUALITY', 80);
}
?>
