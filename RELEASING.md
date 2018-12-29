# Releasing a new BlogText version

## Steps **before** releasing a new version

1. Check readme at: <https://wordpress.org/plugins/about/validator/>
   * Especially for incorrect line breaks
1. Check issue tracker for open issues
1. Check support forum for open issue

## Steps **when** releasing a new version

1. Make sure "Tested up to" and "Stable tag" in "readme.txt" have been properly updated
1. Make sure "Version" in "blogtext.php" has been updated properly

1. Run "build.sh"
1. Create SVN tag for version
1. Create Mercurial tag version

1. Create new version in BitBucket's issue tracker
1. Upload plugin as .zip to downloads section in BitBucket
1. Close all resolved issues
1. Close all resolved support tickets
