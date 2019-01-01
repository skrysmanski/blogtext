# Releasing a new BlogText version

For general help on Wordpress' plugin directory, see <https://developer.wordpress.org/plugins/wordpress-org/>.

## Steps **before** releasing a new version

1. Both for the **oldest** supported PHP version (`./Start-TestEnv.ps1 -PhpVersion 5.6`) and the **newest** supported PHP version (`./Start-TestEnv.ps1`) - as defined by the [Wordpress Docker image](https://hub.docker.com/_/wordpress):
   1. Run automated tests
   1. Run manual tests (see `HACKING.md`)
1. Check readme at: <https://wordpress.org/plugins/developers/readme-validator/>
1. Check issue tracker for open issues
1. Check [support forum](https://wordpress.org/support/plugin/blogtext/) for open issue

## Steps **when** releasing a new version

1. Make sure `Tested up to` and `Stable tag` in `src/readme.txt` have been updated.
1. Make sure `Version` in `src/blogtext.php` has been updated.
1. Run `./Create-Release.ps1 <VERSION>`
1. Check-in the SVN repository in the `dist/` directory.
1. Create SVN tag for version
1. Create Git tag version
1. Upload `dist-zip/blogtext-<VERSION>.zip` to downloads section in GitHub
1. Close all resolved issues
1. Close all resolved support tickets
1. Update changelog at: <https://blogtext.mayastudios.com/changelog/>
