<?php
// See: http://orderedlist.com/blog/articles/live-search-with-quicksilver-style-for-jquery/
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Live Search with Quicksilver Style</title>
	<link rel="stylesheet" href="codeblock-lang.css" type="text/css" media="screen" />
	<script type="text/javascript" src="jquery.js"></script>
	<script type="text/javascript" src="quicksilver.js"></script>
	<script type="text/javascript" src="codeblock-lang.js"></script>

	<script type="text/javascript" charset="utf-8">
		$(document).ready(function() {
			$('#q').liveUpdate('languages').focus();
		});
	</script>
</head>
<body>

<div id="wrapper">
	<h1>Search Supported Programming Languages</h1>
	
	<form method="get" autocomplete="off">
		<div>
			<input type="text" value="" name="q" id="q" autocomplete="off"/>
		</div> 
	</form>

	<ul id="languages">
<?php
require_once(dirname(__FILE__).'/../../thirdparty/geshi/geshi.php');
$geshi = new GeSHi();
$supported_languages = $geshi->get_supported_languages();
foreach ($supported_languages as $idx => $lang_name) {
  // TODO by sk: $geshi-get_language_fullname() may crash the server. So we don't use it
  //   until the problem is analyzed and/or fixed. See: https://bugs.php.net/bug.php?id=55090
  //echo '<li>'.$geshi->get_language_fullname($lang_name).'</li>'."\n";
  
  echo '<li>'.$lang_name.'</li>';
}
?>
	</ul>

</div>

</body>
</html>