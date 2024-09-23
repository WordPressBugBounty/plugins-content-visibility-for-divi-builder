=== Content Visibility for Divi Builder ===
Contributors: jhorowitz
Donate link: https://www.aod-tech.com/donate/
Tags: divi, divi theme, divi builder, elegant themes, elegantthemes, page builder, pagebuilder, conditional-tags, conditional tags, conditional, tags, show, hide, show hide
Requires at least: 3.0
Tested up to: 5.9
Stable tag: 3.22
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Content Visibility for Divi Builder.

== Description ==

Content Visibility for Divi Builder allows Sections and Modules to be displayed/hidden based on the outcome of a PHP boolean expression.

This plugin is for both the standalone Divi theme (or child themes thereof) or the Divi Builder plugin, versions 2.6 or higher!

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/content-visibility-for-divi-builder` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress
1. You're Done! You will notice that Section and Module settings dialogs will now have "Content Visibility" as a configurable setting.

== Frequently Asked Questions ==

= Will this work for any module, even custom ones? =

Yes! It detects and modifies Modules and Sections by class inheritance. As long as Elegant Themes continues to have a single root class for everything, this plugin should detect all of them, including third party ones!

= What if I deactivate this plugin? Will all of my content reappear automatically? =

Yes. If you decide to deactivate or uninstall this plugin, the "Content Visibility" configuration option will disappear from the Divi Builder, and will not have any effect on the frontend output.

Of course, the "Content Visibility" settings that were defined for a particular Section or Module will continue to persist in the database, until that post/page is updated. 
This can be a good thing, however, as you may want to reinstall/reactivate in the future and not have to re-enter all of your "Content Visibility" expressions!

= How do I use it!? =

Once the plugin is installed and activated, a "Content Visibility" option will appear in each Section or Module's settings on either the Advanced tab under Visibility (for Divi 4.x) or the General Settings / Content tab (for Divi 3.x or lower.)

You may enter any PHP boolean expression you would like, (e.g. is_user_logged_in()), and the Section or Module will only display if the expression evaluates to true.

NOTE: Complex expressions are usually best entered as a custom function call defined in a child theme or plugin!
So, for example, you could enter my_custom_function() in the Content Visibility option, and then define that function (returning true or false) in your child theme's functions.php.
If there are several common boolean expressions you use, this also has the added benefit of allowing you to change the behavior of your content by simply modifying the function body once instead of re-entering Content Visibility options all over the place.

== Screenshots ==

1. The Content Visibility option in the Divi 4.x interface.

2. The Content Visibility option in the Divi 3.x Visual Builder interface.

3. The Content Visibility option in the 3.x and older Divi Builder backend interface.

== Changelog ==
= 3.22 =
* Fix compatibility with Stop Spammers plugin. Thanks to @kindred for providing access to a test environment exhibiting the issue!

= 3.21 =
* Fix compatibility with the Divi Library.

= 3.20 =
* Fix compatibility with the Divi Theme Builder.

= 3.19 =
* Restore support for DiviExtension modules in Divi 4.10.x.

= 3.18 =
* Fix the backend documentation page so that currently-available modules are shown.
* Fix the rating link image's styles.

= 3.17 =
* Restore support for lazy-loaded modules in Divi 4.10.x. Special thanks to @chaostica, @jgarces and @sthaney for contributing information which helped lead to the fix!

= 3.16 =
* Fix extraneous inclusion of builder.js, which in turn fixes various backend/classic editor logic. Thanks to Bernard Lemieux of bernardlemieux.ca!

= 3.15 =
* Fix the behavior of content_visibility_for_divi_builder_shortcode_* filters.

= 3.14 =
* Emergency hotfix for crash introduced in 3.13.

= 3.13 =
* Fix PHP v7.4.x notice spam with recent Divi versions.

= 3.12 =
* Fix interoperability issues with newer WordPress versions.
* Fix issues with Divi Builder 4.x releases.

= 3.11 =
* Remove hold harmless agreement.

= 3.10 =
* Remove usage tracking entirely.
* Fix request parameter sanitization.

= 3.09 =
* Fix optional usage tracking.
* Fix an interoperability issue with other Divi module extender plugins.

= 3.08 =
* Fix deprecation notice spam. Thanks to Ben Harper of 3dtek.xyz!

= 3.07 =
* Fix missing ET_BUILDER_DIR . 'layouts.php'.

= 3.06 =
* Fix version checker options.

= 3.05 =
* Update license terms.

= 3.04 =
* Fix the issue wherein builder-fixes.js forces builder.js to be loaded in the header instead of in the footer. Special thanks to @kihoshin for helping to locate this error!

= 3.03 =
* Better multisite support.
* Remove the need to clear local storage in modern browsers to see the "Content Visibility" settings on Sections / Modules.

= 3.02 =
* Fix distributable...

= 3.01 =
* Fix "Currently Available Module-Specific Actions and Filters" tab not displaying available actions and filters in the Module Extender API Reference.

= 3.00 =
* Add support for Visual Builder in Divi 3.x.

= 2.02 =
* Fix Builder UI to handle ']' characters in Content Visibility expressions.

= 2.0.0 =
* Add Module Extender for Divi Builder functionality; see API page after upgrading under Tools -> Module Extender API Reference.
* Add usage tracking. If you prefer not to submit your usage data, this can be disabled on the plugins page by clicking "Disable anonymous usage tracking".

= 1.0.4 =
* Add links to ratings and reviews to help spread the word.

= 1.0.3 =
* Call load_plugin_textdomain().

= 1.0.2 =
* Added i18n support.

= 1.0.1 =
* Fix handling of double quotes in Content Visibility expression. Thanks to Dave Bullock of memberium.com!

= 1.0.0 =
* Initial Release

== Upgrade Notice ==
= 3.19 =
* This release fixes a major issue with DiviExtension-loaded modules in Divi 4.10.x, wherein the module is always shown regardless of any visibility expression settings. Please upgrade this plugin before upgrading Divi to 4.10.x!

= 3.17 =
* This release fixes a major issue with lazy-loaded modules in Divi 4.10.x, wherein the module is always shown regardless of any visibility expression settings. Please upgrade this plugin before upgrading Divi to 4.10.x!

= 3.00 =
* This release finally adds support for Visual Builder in Divi 3.x!

= 2.02 =
* The workaround for using ']' characters in Content Visibility expressions is no longer required! A javascript fix for builder.js is applied automatically by the plugin, which will automatically escape ']' characters behind the scenes.

= 2.0.0 =
* Now even more developer friendly. Add your own custom attributes and functionality to any Divi Builder module!

= 1.0.4 =
* Please rate and review this plugin if you find it helpful (or even if you don't!). Links and a one-time notice have been added to help you do so easily from the admin area.

= 1.0.3 =
* load_plugin_textdomain() might be helpful for that i18n support!

= 1.0.2 =
* Now with i18n support!

= 1.0.1 =
* Prior versions did not handle double quotes in Content Visibility expressions correctly. Upgrade if you'd like your Content Visibility expressions to contain double quotes!

= 1.0.0 =
* Initial Release
