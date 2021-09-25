# Releasing a new BlogText version

For general help on Wordpress' plugin directory, see <https://developer.wordpress.org/plugins/wordpress-org/>.

## Required Software

* Docker Desktop
* Visual Studio Code

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
1. Run `./Create-Release.ps1 <VERSION>` in the VSCode dev container
1. Run `./Publish-ReleaseToWordPress.ps1 <VERSION>` in the VSCode dev container (this will upload the new version to WordPress' plugin directory)
1. Create Git tag version
1. Upload `dist-zip/blogtext-<VERSION>.zip` to downloads section in GitHub
1. Close all resolved support tickets
1. Update changelog at: <https://blogtext.mayastudios.com/changelog/>
