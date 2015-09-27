Steps BEFORE releasing a new version
==================================

1. Check readme at: https://wordpress.org/plugins/about/validator/
   * Especially for incorrect line breaks
2. Check issue tracker for open issues
3. Check support forum for open issue


Steps WHEN releasing a new version
==================================

1. Make sure "Tested up to" and "Stable tag" in "readme.txt" have been properly updated
2. Make sure "Version" in "font-emoticon.php" has been updated properly
3. Make sure "VERSION" constant "FontEmoticonsPlugin.php" has been updated properly

4. Run "build.sh"
5. Checking assets and trunk in Wordpress SVN repository
6. Create SVN tag for version
7. Create Mercurial tag version

8. Create new version in BitBucket's issue tracker
9. Upload plugin as .zip to downloads section in BitBucket
10. Close all resolved issues
11. Close all resolved support tickets
