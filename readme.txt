=== BlogText ===
Contributors: manski
Tags: formatting, markup, post
Requires at least: 3.0.0
Tested up to: 3.1.4
Stable tag: 0.9.1

BlogText is a plugin for WordPress that adds a simple wiki-like syntax to WordPress and enriches it with a
good Wordpress editor integration.

== Description ==
BlogText (http://blogtext.mayastudios.com) is a plugin for WordPress that allows you to use a simple wiki-like
syntax (based on the Creole wiki syntax) to write your posts and pages. This syntax is easy-to-learn and
fast-to-type. The goal behind BlogText is that you don’t need to write HTML anymore to achieve the desired
text layout.

The following list lists some of the markups supported by BlogText. For a more complete list, see BlogText's
syntax description page at http://blogtext.mayastudios.com/syntax/ (which is written entirely in BlogText
syntax and demonstrates BlogText's capabilities).

Supported markup:

* Basic text formatting such as bold, italics, underlining, and strike-through
* Lists
* Tables
* Internal and external links
* Headings
* Table of contents
* Preformatted text and code blocks with syntax highlighting

BlogText also integrates into Wordpress' HTML editor by providing its own buttons (to create BlogText syntax),
media browser integration, and help links. This make writing posts with BlogText even easier.

For more information, see BlogText's feature list at: http://blogtext.mayastudios.com/features/

== Installation ==
Installing BlogText is pretty much straight forward.

You need **Wordpress 3.0 or higher** to install BlogText. You also need **PHP 5.0 or higher** installed on
your webserver.

1. Simply download the BlogText .zip file.
1. Extract it.
1. Upload the folder "blogtext" (containing the file `blogtext.php` among others) into your blog's plugin directory (usually `wp-content/plugins`).
1. Activate it from the "Plugins" panel in your blog's admin interface
1. Start writing your posts

== Changelog ==

= 0.9.1 =
* Absolute links (like `[[/feed|my feed]]`) can now be used.
* Fixed image captions for external images; they no longer have a width of 0px.
* Added "big" for image size which is just an alias for "large".
* Certain JPEG images (encoded with Progressive DCT) can now be used
* Rewrote output cache

= 0.9.0d =
* This version was just released to fix the buggy readme parser in the Wordpress Plugin Directory. It's
  identical to 0.9.0c.

= 0.9.0c =
* First official release.
