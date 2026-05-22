=== Content Visibility for Divi Builder ===
Contributors: jhorowitz
Donate link: https://www.aod-tech.com/donate/
Tags: divi, visibility, conditional, show, hide
Requires at least: 5.5
Tested up to: 7.0
Requires PHP: 7.0
Stable tag: 5.00
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Content Visibility for Divi Builder.

== Description ==

Content Visibility for Divi Builder allows Sections and Modules to be displayed/hidden based on the outcome of a PHP boolean expression.

This plugin is for both the standalone Divi theme (or child themes thereof) and the Divi Builder plugin, version 3 or higher!

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/content-visibility-for-divi-builder` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress
1. You're Done! You will notice that Section and Module settings dialogs will now have "Content Visibility" as a configurable setting.

== Frequently Asked Questions ==

= Will this work for any module, even custom ones? =

Yes!

In Divi 4 and below:
This plugin detects and modifies Modules and Sections by class inheritance.
As long as Elegant Themes continues to have a single root class for everything, this plugin should detect all of them, including third party ones!

In Divi 5:
This plugin detects and modifies Modules and Sections by instrumenting all Gutenberg block render callbacks.
As long as Elegant Themes continues to utilize Gutenberg blocks with render callbacks for everything, this plugin should detect all of them, including third party ones!

= What if I deactivate this plugin? Will all of my content reappear automatically? =

Yes. If you decide to deactivate or uninstall this plugin, the "Content Visibility" configuration option will disappear from the Divi Builder, and will not have any effect on the frontend output.

Of course, the "Content Visibility" settings that were defined for a particular Section or Module will continue to persist in the database, until that post/page is updated. 
This can be a good thing, however, as you may want to reinstall/reactivate in the future and not have to re-enter all of your "Content Visibility" expressions!

= How do I use it!? =

Once the plugin is installed and activated, a "Content Visibility" option will appear in each Section or Module's settings on either the Advanced tab under Visibility (for Divi 4.x or higher) or the General Settings / Content tab (for Divi 3.x or lower.)

You may enter any PHP boolean expression you would like, (e.g. is_user_logged_in()), and the Section or Module will only display if the expression evaluates to true.

NOTE: Complex expressions are usually best entered as a custom function call defined in a child theme or plugin!
So, for example, you could enter my_custom_function() in the Content Visibility option, and then define that function (returning true or false) in your child theme's functions.php.
If there are several common boolean expressions you use, this also has the added benefit of allowing you to change the behavior of your content by simply modifying the function body once instead of re-entering Content Visibility options all over the place.

= What is Expression Validation? =

Starting in version 5.00, the plugin includes expression validation that checks visibility expressions before they are evaluated. This prevents potentially dangerous PHP code (such as system commands, file operations, or network calls) from being executed via visibility expressions.

Expression validation works by:

1. Tokenizing the expression using PHP's built-in tokenizer.
2. Checking each token against an allowlist of safe token types (function calls, literals, operators, etc.).
3. Checking function names against a denylist of dangerous functions (e.g. exec, system, file_put_contents, eval, etc.).
4. Detecting attempts to use strings as callable functions (e.g. 'system'('ls')).

**New installs** have validation enabled automatically.

**Existing installs** upgrading to 5.00 will see a non-dismissible admin notice directing them to the Expression Validation tab under Tools > Content Visibility for Divi Builder API Reference. That tab provides a content scanner that checks all existing posts for expressions that would be blocked, plus a copy-paste filter snippet that pre-allowlists every custom callable found in current content. Paste the snippet into a site-specific plugin or your theme's `functions.php`, re-scan to verify zero remaining errors, then click "Enable Validation".

Validation can be disabled later (a "Disable Validation" form appears once it is enabled, requiring you to type `DISABLE` to confirm). Use this if validation causes unexpected behavior on your site while you investigate.

When validation is enabled and an expression is blocked, the content will be **shown** (not hidden) and an email notification is sent to the site admin with details about the blocked expression. Publishing posts whose content contains expressions that fail validation is blocked at the editor — Gutenberg / the Divi 5 Visual Builder surface a clear error, and the classic editor and the Divi 3 & 4 builders abort the save before any database write so the existing post is left exactly as it was (a published page stays published with its previous content; in-progress edits remain in the editor for you to fix and re-save).

= What expressions are allowed with validation enabled? =

Validation uses a curated allowlist of safe callables (WordPress conditional tags) plus an extension filter for adding your own. Out of the box the following work:

* `is_user_logged_in()`
* `current_user_can('editor')`
* `is_admin() && is_singular()`
* `is_page() || is_archive()`
* Comparison and logical operators: `==`, `!=`, `===`, `!==`, `<`, `>`, `&&`, `||`, `!`, `?:`, `??`
* Numeric / string literals, `true` / `false` / `null`

Custom callables (e.g. `my_custom_helper()` or `MyTheme::shouldBeVisible()`) are NOT allowlisted by default — admins explicitly add them via the `content_visibility_for_divi_builder_allowed_callables` filter (a ready-to-paste snippet is generated by the content scanner on the Expression Validation tab).

The following types of expressions are blocked:

* Unknown callables: function or static-method calls not on the allowlist — fix by adding to the allowlist filter or removing from content
* Bare identifiers: `wtf` (without parens) — must be a function call, static method, class constant access, or a `true`/`false`/`null` literal
* Dangerous functions: `system('ls')`, `update_option(...)`, `wp_mail(...)`, etc. — built-in denylist (filterable)
* Variable access: `$_GET['cmd']` — `$` and `T_VARIABLE` are not allowed
* String-as-callable: `'system'('ls')` — string literal followed by `(`
* Object instantiation: `new ReflectionFunction(...)` — `new` keyword blocked (write a static helper that does the `new` internally instead)
* Instance method chains: `Foo::bar()->baz()` — cannot be reliably analyzed; rewrite as a static helper
* Code execution keywords: `eval(...)`, `include(...)` — blocked token types
* Backtick execution: `` `ls` `` — backtick character blocked
* Assignment: `$x = 1` — both `$` and `=` are blocked characters

= Can I customize the validation rules? =

Yes! Three filters are available for developers to customize validation behavior. See the "Developer Filters" section below for details.

== Developer Filters ==

= Expression Validation Filters =

The following filters allow developers to customize expression validation behavior. Add these filters in your theme's functions.php or in a custom plugin.

**`content_visibility_for_divi_builder_blocked_functions`**

Filter the array of blocked function names. Functions in this list will cause an expression to fail validation. All comparisons are case-insensitive.

Example — allow `file_get_contents` (blocked by default):

`add_filter( 'content_visibility_for_divi_builder_blocked_functions', function( $functions ) {
    return array_diff( $functions, array( 'file_get_contents' ) );
} );`

Example — block an additional function:

`add_filter( 'content_visibility_for_divi_builder_blocked_functions', function( $functions ) {
    $functions[] = 'my_dangerous_function';
    return $functions;
} );`

**`content_visibility_for_divi_builder_allowed_tokens`**

Filter the array of allowed PHP token types (T_* constants). Tokens not in this list will cause an expression to fail validation. This is an advanced filter — consult the PHP tokenizer documentation before modifying.

Example — allow the T_VARIABLE token type (blocked by default, use with caution):

`add_filter( 'content_visibility_for_divi_builder_allowed_tokens', function( $tokens ) {
    $tokens[] = T_VARIABLE;
    return $tokens;
} );`

**`content_visibility_for_divi_builder_allowed_chars`**

Filter the array of allowed single-character tokens. Characters not in this list (such as `=`, `;`, `{`, `}`, `` ` ``, `@`, `&`, `|`, `~`, `^`) will cause an expression to fail validation.

Example — allow the `&` character for bitwise operations:

`add_filter( 'content_visibility_for_divi_builder_allowed_chars', function( $chars ) {
    $chars[] = '&';
    return $chars;
} );`

**`content_visibility_for_divi_builder_allowed_callables`**

Filter the array of callables (function names and `Class::method` static-method entries) the validator considers known-safe. Anything not on the allowlist (and not on the `blocked_functions` denylist) is treated as an "Unknown callable" error. Names are normalized (leading `\` stripped, `namespace\` keyword prefix stripped, lowercased) before comparison, so `\My\Namespace\Class::method`, `My\Namespace\Class::method`, and `namespace\My\Namespace\Class::method` all match the same allowlist entry. The default list ships with WordPress conditional tags (`is_user_logged_in`, `current_user_can`, `is_admin`, etc.). The Expression Validation tab's content scanner generates a ready-to-paste snippet for this filter pre-populated with every custom callable currently in your content.

Example — allowlist a theme helper and a static service method:

`add_filter( 'content_visibility_for_divi_builder_allowed_callables', function( $callables ) {
    $callables[] = 'mytheme_should_be_visible';
    $callables[] = 'MyTheme\Visibility\Service::checkUser';
    return $callables;
} );`

== Screenshots ==

1. The Content Visibility option in the Divi 5.x interface.

2. The Content Visibility option in the Divi 4.x interface.

3. The Content Visibility option in the Divi 3.x Visual Builder interface.

4. The Content Visibility option in the Divi 3.x backend interface.

== Changelog ==
= 5.00 =
* Visibility expressions are now evaluated against an allowlist of known-safe callables (default: WordPress conditional tags). Function calls and static method calls not on the allowlist are rejected with a "Contact the site administrator" error. `new`, instance method chains (`Foo::bar()->baz()`), variable functions, string-as-callable, and bare identifiers are hard errors and cannot be allowlisted — rewrite as a static helper. New installs have validation enabled by default; existing installs ship with validation off and a persistent admin notice prompting the migration workflow.
* The Expression Validation tab now generates a copy-paste `add_filter('content_visibility_for_divi_builder_allowed_callables', ...)` snippet pre-populated with every custom callable found in current content (with first-sighting context: post id, module name, admin label, file:line via Reflection when available). Non-allowlistable patterns (instance methods, etc.) are listed separately as "must be rewritten."
* Posts whose content contains expressions failing validation cannot be published while the toggle is on. The REST flow (Gutenberg / Divi 5 VB / API clients) returns `WP_Error` with a structured error message; the classic editor and the Divi 3 & 4 builders abort the save before any database write so an already-published page stays published with its previous content, and the in-progress edits remain in the editor for you to fix and re-save.
* After every save, validation findings are stored as private post meta and surfaced as a yellow (validation off) or red (validation on) admin notice on the post's edit screen.
* New REST endpoints under `cvdb/v1/`: `security/scan`, `security/validate-expression` (server-truth validator for the inline UI), `notices/rating/dismiss` (replaces an AJAX action).
* Persistent red admin notice on every admin page when PHP `eval()` is unavailable on the host (e.g. Suhosin's `executor.disable_eval`, custom hardening). Detected via runtime smoke probe.
* New filter: `content_visibility_for_divi_builder_allowed_callables`. Extension point for admins to allowlist custom callables. Accepts function names and `Class::method` static-method entries; namespace-qualified names are normalized (leading `\`, `namespace\` prefix stripped, lowercased).
* New "Expression Validation Filters" tab** on the API Reference page documenting all four expression-validation filters (`blocked_functions`, `allowed_tokens`, `allowed_chars`, `allowed_callables`) with paste-ready examples.

= 4.02 =
* Add Plugin URI.

= 4.01 =
* Fix undefined variable in cvdb-et-builder-element.class.php. Thanks to @kindred for the quick bug report!

= 4.00 =
* Refactor the code for performance and maintainability.
* Add Divi 5 public alpha support!
* Drop Divi 2.x support.

= 3.23 =
* Catch errors in visibility expression evaluation; this allows the rest of the page to load while only hiding the module or section that triggered the error.
* Email site admin when errors in visibility expression evalution occur with helpful debugging information (i.e. the error that occured, the URL on which it occured, and the full shortcode contents of the relevant module or section as an attachment).

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
= 5.00 =
* Major security release: strict expression validation. Visibility expressions are now checked against an allowlist of safe callables before evaluation, blocking any unknown functions or static methods (including `new` and instance method chains, which cannot be statically analyzed). Existing installs upgrade with validation **disabled** to avoid breaking existing content; a persistent admin notice directs you to the Expression Validation tab under Tools > API Reference, where the content scanner generates a copy-paste `add_filter('content_visibility_for_divi_builder_allowed_callables', ...)` snippet pre-populated with every custom callable currently in your content. Paste, re-scan, then enable validation. Validation can be disabled afterwards via a `DISABLE`-confirmation form if needed. Publish blocking is enforced at the editor (Gutenberg / Divi 5 VB error; classic editor and Divi 3 & 4 builders abort the save before any database write so an already-published page stays published unchanged). Includes inline live validation in the Divi 5 Visual Builder and a new REST API.

= 4.00 =
* This release adds support for Divi 5 public alpha! Yay! Remember, Elegant Themes has said that the public alpha is not intended for use on production sites. So, if they update something in the Divi 5 public alpha in a breaking way and this plugin (used with Divi 5) also breaks, it shouldn't be a problem since you aren't using Divi 5 public alpha in production, right? :)
* This release also drops support for Divi 2.x, which you probably aren't using anyway...

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
