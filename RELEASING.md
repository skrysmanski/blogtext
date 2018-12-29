Steps BEFORE releasing a new version
==================================

1. Check readme at: https://wordpress.org/plugins/about/validator/
   * Especially for incorrect line breaks
2. Check issue tracker for open issues
3. Check support forum for open issue


Steps WHEN releasing a new version
==================================

1. Make sure "Tested up to" and "Stable tag" in "readme.txt" have been properly updated
2. Make sure "Version" in "blogtext.php" has been updated properly

3. Run "build.sh"
4. Create SVN tag for version
5. Create Mercurial tag version

6. Create new version in BitBucket's issue tracker
7. Upload plugin as .zip to downloads section in BitBucket
8. Close all resolved issues
9. Close all resolved support tickets
