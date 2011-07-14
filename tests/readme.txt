Readme for tests
================

Prepare Wordpress Testinstallation
----------------------------------

1. Download and installed the desired Wordpress version
2. Install and activate BlogText plugin
3. Delete all existing pages and then empty the trash (link on the pages overview page)
4. Import "Wordpress"


Manual WP-integration tests
---------------------------
These tests test stuff that uses some internals of WordPress and therefor is prone to be broken on version
changes.

 * Are the editor buttons in the editor and do they insert their code correctly?
 * Does the language lookup button work?
 * Does the settings page still look ok?
 * When clearing the page cache (using the BlogText settings page), do we get a notification that the page
   cache was cleared?
