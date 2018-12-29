<?php
use MSCL\FileInfo\FileInfoException;

require_once(dirname(__FILE__).'/../commons.php');
require_once(dirname(__FILE__).'/api.php');

// set memory limit to be able to have enough space to resize larger images
ini_set('memory_limit', '50M');

try {
  // NOTE: We can't use the ThumbnailAPI here as this requires Wordpress being loaded.
  $thumb = new MSCL_Thumbnail($_GET['id'], null, null, null);
  $thumb->display_thumbnail();
} catch (FileInfoException $e) {
  MSCL_Thumbnail::display_error_msg_image($e->getMessage());
} catch (Exception $e) {
  print MSCL_ErrorHandling::format_exception($e, true);
  // exit here as the exception may come from some static constructor that is only executed once
  exit;
}
?>
