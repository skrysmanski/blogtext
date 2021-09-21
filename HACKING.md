# Hacking BlogText

This file contains some hints for developing BlogText.

## General Development

To test out changes to BlogText, you can easily spin up a development server. To do this, you need two things:

* PowerShell
* Docker

Then just call:

    ./Start-TestEnv.ps1

This will spin up a Wordpress Docker container (with the most recent Wordpress and PHP version).

The development server is then available at:

    http://localhost:8080

You can customize both the Wordpress and the PHP version by specifying the appropriate parameters; e.g.:

    ./Start-TestEnv.ps1 -WordpressVersion 5.0 -PhpVersion 7.1 -MySqlVersion 5.6

**Note:** This only supports versions that are available in the [Wordpress Docker image](https://hub.docker.com/_/wordpress).

To stop the development server, call:

    ./Stop-TestEnv.ps1

## Run BlogText tests

1. Run the tests (under **Tools/BlogTextTests**).
1. Check whether any files have changed (via `git status`)

## Manual WP-integration tests

These tests test stuff that uses some internals of WordPress and therefor is prone to be broken on version
changes.

* Is previewing a post working?
* Are the editor buttons in the editor and do they insert their code correctly?
* Does the language lookup button work?
* Does the settings page still look ok?
* When clearing the page cache (using the BlogText settings page), do we get a notification that the page cache was cleared?

## Debugging

See also: <http://codex.wordpress.org/Debugging_in_WordPress>

Recommended WP-Plugins:

* Debug Bar

Debugging:

* `var_dump()` : dumps the whole structure of the variable to the output
* `MSCL_ErrorHandling::print_stacktrace()` : prints a brief stacktrace
* `log_stacktrace()` : log the current stacktrace
* `log_error()`, `log_warn()`, `log_info()`, `console()` : logs to the JavaScript browser console
