# Releasing a new BlogText version

For general help on Wordpress' plugin directory, see <https://developer.wordpress.org/plugins/wordpress-org/>.

## Steps **before** releasing a new version

1. Check readme at: <https://wordpress.org/plugins/developers/readme-validator/>
   * Especially for incorrect line breaks
1. Check issue tracker for open issues
1. Check [support forum](https://wordpress.org/support/plugin/blogtext/) for open issue

## Steps **when** releasing a new version

1. Make sure `Tested up to` and `Stable tag` in `src/readme.txt` have been updated.
1. Make sure `Version` in `src/blogtext.php` has been updated.

1. Run `./Create-Release.ps1`
1. Create SVN tag for version
1. Create Git tag version

1. Upload plugin as .zip to downloads section in GitHub
1. Close all resolved issues
1. Close all resolved support tickets
