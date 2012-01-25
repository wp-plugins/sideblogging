=== Sideblogging ===
Contributors: cedbv
Donate link: http://www.boverie.eu/
Tags: asides,facebook,twitter
Requires at least: 3.0
Tested up to: 3.3
Stable tag: 0.8.1

Display asides in a widget. They can automatically be published to Twitter, Facebook, and any Status.net installation (like identi.ca).

== Description ==

Manage and write aside posts (using the new custom post types feature).    
They are displayed in a sidebar widget and don't interfere with other posts.   
A dashboard widget is provided to allow fast aside blogging.

Require **Wordpress 3** and **PHP 5**.

Aside content must be write in post title.
If you write something in post content (like a video embed), a link to this content will be displayed after the aside.

Asides can be automatically posted on Twitter and/or Facebook and/or Identica (Status.Net).
A Twitter app is preconfigured.           
For Facebook, you need to create your own application. Video tutorial included in contextual help on settings page.

When asides with additional content are published to Twitter a shortlink to the full content is added.          

Supported shortlink providers :

* Native (blogurl?p=post_ID)
* is.gd
* bit.ly (api key needed)
* jm.p (api key needed)
* goo.gl
* tinyurl.com
* su.pr
* cli.gs
* twurl.nl
* fon.gs

== Installation ==

1. Upload `sideblogging` directory to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Configure the plugin's settings.

== Frequently Asked Questions ==

= What are the requirements ? =
* PHP 5
* Wordpress 3.0
* a widget compatible theme

= I have 404 error on aside's permalink =
Try to regenerate permalink in *Settings/Permalinks*.

= Publication to Facebook doesn't work =
Click on change Facebook account on settings page and try to Connect Facebook again.

== Screenshots ==

1. Settings
2. Dashboard widget
3. Menu
4. Public widget

== Changelog ==

= 0.8.1 =
* Archives work with more themes

= 0.8 =
* Compatibility with Wordpress 3.3
* New option : asides in main rss feed
* Reduce overall memory footprint by around 50%

= 0.7 =
* Compatibility with Wordpress 3.2
* Dashboard Widget : Compatibility with jQuery 1.6
* Better media detection for rich Facebook post
* No more refreshing rewrite rules on every administration page
* Widget : A little cleaning + Fix broken rss link with custom slug
* Update OAuth library

= 0.6 =
* Rich Facebook post (Images thumbnail, Youtube embed, etc.)
* Asides can be clickable
* Images in widget are now optional (and customizable in the settings)
* Custom permalinks prefix
* Little bug fix in widget

= 0.5.1 =
* Fix a bug in Widget

= 0.5 =
* StatusNet integration (include identi.ca)
* RSS Feed
* Archives page
* A more customizable widget (RSS, Archives links)
* Wordpress 3.1 compatibility
* Use of goo.gl native API
* Fix a fatal error when another plugin uses OAuth

= 0.3.1 =
* Fix a regression in public widget
* New shortlinks providers : j.mp

= 0.3 =
* Converts text links into clickable links.
* New permalinks.
* Twitter features no longer need Curl.

= 0.2.1 =
* Minor change

= 0.2 =
* New option : comments in asides.
* Bugfix : Errors when WordPress address was not the same that blog address.

= 0.1.1 =
* Fix a problem that occurred in unexpected situations
* Plugin tested with PHP 5.2
* Fix a security issue in dashboard widget
* More check about compatibility

= 0.1 =
* First public version.

== Upgrade Notice ==

= 0.7 =
Compatibility fixes for recent changes in Wordpress and jQuery, much better performance in administration pages and a few bug fixes.

= 0.6 =
Rich Facebook post (Images thumbnail, Youtube embed, etc).
Custom permalinks prefix (I hope that will work everywhere...).
A lot of new settings are not yet documented...sorry.

= 0.5.1 =
New Status.Net integration (identi.ca), customizable widget and Wordpress 3.1 compatibility.
New features like RSS feed and archives page may not work everywhere...

= 0.5 =
New Status.Net integration (identi.ca), customizable widget and Wordpress 3.1 compatibility.
New features like RSS feed and archives page may not work everywhere...

= 0.3.1 =
Fix a regression and adds jm.p support.

= 0.3 =
Links in asides are now clickable. Curl is not needed anymore.

= 0.2 =
Comments in asides : If you want you can manually allow comments on previous asides (after activate the option).
On new aside that will be automatically.

= 0.1.1 =
Fix a random error and a security issue.

= 0.1 =
Is it possible to upgrade to the first version ?