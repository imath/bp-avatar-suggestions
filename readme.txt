=== BP Avatar Suggestions ===
Contributors: imath
Donate link: http://imathi.eu/donations/
Tags: BuddyPress, avatar
Requires at least: 4.2
Tested up to: 4.5
Stable tag: 1.3.2
License: GPLv2

Avatar suggestions for the members and the groups of your BuddyPress powered community.

== Description ==

BP Avatar Suggestions is a BuddyPress plugin to allow administrators to add a list of suggested avatars in order to let the members or the groups of their community to select one of the avatars from their profile page in case of users or their manage page in case of groups.
This plugin was inspired by Dan (Network Admin of the French BuddyPress community).


http://vimeo.com/120889562


== Installation ==

You can download and install BP Avatar Suggestions using the built in WordPress plugin installer. If you download BP Avatar Suggestions manually, make sure it is uploaded to "/wp-content/plugins/bp-avatar-suggestions/".

Activate BP Avatar Suggestions in the "Plugins" admin panel using the "Network Activate" (or "Activate" if you are not running a network) link.

== Frequently Asked Questions ==

= If you have any question =

Please add an issue <a href="https://github.com/imath/bp-avatar-suggestions/issues">here</a> or a request <a href="https://github.com/imath/bp-avatar-suggestions/pulls">here</a>

== Screenshots ==

1. Settings to allow/ disallow users/groups to choose a suggested avatar.
2. The avatar suggestions Administration screen.
3. Example for a user.
4. Example for a group.

== Changelog ==

= 1.3.2 =
* Use `load_plugin_textdomain` so that plugin can be translated within the WordPress.org repository
* Adapts the BuddyPress settings tab layout to changes introduced in BuddyPress 2.5.
* requires BuddyPress 2.5
* Tested Up to WordPress 4.5

= 1.3.1 =
* Fixes a bug when the uploaded image is exactly 150x150px. Props @shpitzyl

= 1.3.0 =
* requires WordPress 4.2.
* requires BuddyPress 2.3.
* Uses the new BuddyPress Avatar UI by adding a new tav for suggestions.
* Adds its own image size to match avatar full dimensions (these can > 150x150)
* Uses the BuddyPress attachments API to improve the upload process and user feedbacks

= 1.2.1 =
* fixes a bug when the suggestion is set, props: lenasterg, pixie2012, lionel.
* Make sure javascript is only loaded in the user's change profile photo screen, props: lenasterg.
* improves the user feedback.

= 1.2.0 =
* requires WordPress 4.1.
* requires BuddyPress 2.2.
* Uses a draft post to attach the avatar to.
* Improves the interface so that it is now possible to bulk upload and delete avatar suggestions.
* Improves the Avatar suggestions "selector", so that it can be used even if BuddyPress user uploads are disabled.
* Adds support for Groups avatar suggestions.

= 1.1.0 =
* Requires BuddyPress 2.0

= 1.0 =
* Plugin birth..

== Upgrade Notice ==

= 1.3.2 =
* Requires BuddyPress 2.5
* Please make sure to backup your database before upgrading the plugin.

= 1.3.1 =
* Requires BuddyPress 2.3
* Please make sure to backup your database before upgrading the plugin.

= 1.3.0 =
* Requires BuddyPress 2.3
* Please make sure to backup your database before upgrading the plugin.

= 1.2.1 =
* Requires BuddyPress 2.2
* Please make sure to backup your database before upgrading the plugin.

= 1.2.0 =
* Requires BuddyPress 2.2
* Please make sure to backup your database before upgrading the plugin.

= 1.1.0 =
* Requires BuddyPress 2.0

= 1.0 =
no upgrades, just a first version..
