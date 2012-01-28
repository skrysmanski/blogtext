Readme for tests
================

See also: http://codex.wordpress.org/Debugging_in_WordPress

Recommended WP-Plugins:

 * Debug Bar
 * Wordpress Logger (version 0.3 issues a warning since Wordpress 3.3)

Debugging:

 * var_dump() : dumps the whole structure of the variable to the output


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


Create Wordpress copies for each Wordpress version
--------------------------------------------------
This section describes how to maintain one Wordpress installation per Wordpress version. It assumes, you've
already created a working Wordpress installation. To update to the new version, do:

 1. Copy the Wordpress installation folder of the latest version you have (e.g. copy from "wordpress-3.2" to
    "wordpress-3.3".
 2. Copy the database (e.g. from "wordpress-32" to "wordpress-33"). When using phpMyAdmin, go to the current
    database, choose "Operations" and then "Copy database to".
 3. Update database entries to the new path. You need to update in "wp_options":
    * "siteurl"
    * "home"
 4. Update "wp-conf.php" to the new database name.
 5. Update Wordpress to the new version.

NOTE: If you renamed the wordpress folder (for some reason) while your webserver was running, you may need to
  restart it before it "knows" the new name.

See also: http://codex.wordpress.org/Moving_WordPress


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
