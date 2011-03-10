<?php
/* 
 * Outputs the fileicons.css file. Note that this is just a maintenance script. Use "fileicon.css" instead.
 */
require(dirname(__FILE__).'/../util.php');
foreach (MarkupUtil::get_fileicon_available_extensions() as $suffix) {
  echo "a.file-$suffix { background-image: url(fileicons/$suffix.png) !important; }\n";
}
?>
