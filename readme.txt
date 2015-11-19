=== Plugin Name ===
Plugin Name: Mambo / Joomla to Wordpress Importer
Plugin URI: http://www.github.com/webuddhainc/wp-mambo-joomla-importer
Contributors: misterpah, webuddha
Tags: mambo, joomla, import, migrate
Requires at least: 3.0.0
Tested up to: 3.0.1
Stable tag: 2.0

Import Joomla 1.0 and Mambo 4.5 articles and insert them into wordpress page, regardless of the location of the server.

== Description ==

Before wordpress become what it is today, many of us use mambo and joomla. Then after mambo developer stop the development, we we forced to migrate to joomla and other wordpress.
But some of us, we make so many changes to the site, its worth thousands of article; which are a burden to migrate them.

This plugin will do 2 things. By order :

* Retrieve data from the mambo / joomla database by using pure php. this data will be represented into strings which can be saved as a text-document.
* Convert the data retrieved from mambo / joomla into wordpress pages

== Installation ==

This section describes how to install the plugin and get it working.

Setup :
1. extract this zip file
1. upload showArticle-mambo.php (or shoArticle-joomla.phg) into the root of your mambo/joomla folder. Make sure that configuration.php is available on the same folder.
1. upload mamboImporter.php into your wordpress plugin folder ( wordpress/wp-content/plugin/<here>)
1. activate the plugin (Mambo Importer)

usage :
1. Run the showArticle-mambo.php (example : www.example.com/showArticle-mambo.php )
1. You will get a page with many letters. COPY ALL OF THEM WITHOUT MISSING ANYTHING. (CTRL+A & CTRL+C)
1. Go to your wordpress , Scroll to the bottom and find mambo importer menu. click it
1. Paste what you copy from showArticle-mambo.php into the yellow box. click Import Data
1. Disable the plugin, and enjoy!

== Changelog ==

= 1.0 =
* support for joomla
* support for mambo

= 2.0 =
* media support
* tag support
* category support
