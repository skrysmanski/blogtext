Readme for tests
================

Prepare Wordpress Testinstallation
----------------------------------

1. Download and installed the desired Wordpress version
2. Set "define('WP_DEBUG', true);" in "wp-config.php".
3. Install and activate BlogText plugin from the BitBucket repository
   $ cd InstallDir/wp-content/plugins
   $ hg clone https://bitbucket.org/mayastudios/blogtext
4. Make sure that you use the default permalink structure (.../?p=123)
5. Run the tests (under Tools/BlogTextTests).
6. Check whether the output files have changed.
   $ hg diff ...


Manual WP-integration tests
---------------------------
These tests test stuff that uses some internals of WordPress and therefor is prone to be broken on version
changes.

 * Are the editor buttons in the editor and do they insert their code correctly?
 * Is previewing a post working?
 * Does the language lookup button work?
 * Does the settings page still look ok?
 * When clearing the page cache (using the BlogText settings page), do we get a notification that the page
   cache was cleared?
