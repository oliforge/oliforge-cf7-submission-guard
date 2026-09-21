=== OliForge CF7 Submission Guard ===
Contributors: oliforge
Tags: contact form 7, validation, spam, security, rate limit
Requires at least: 6.2
Requires PHP: 7.4
Tested up to: 7.1
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Configurable server-side submission rules and lightweight logs for Contact Form 7.

== Description ==

OliForge CF7 Submission Guard validates Contact Form 7 submissions before mail is sent. It can enforce field length rules, block URLs and email-like content, validate country values when OliForge CF7 Country Select is active, block email domains, rate-limit by IP, require a minimum completion time, detect duplicate messages, validate a configured consent field, and keep lightweight security logs without storing submitted message contents.

== Installation ==

1. Install and activate Contact Form 7.
2. Upload and activate OliForge CF7 Submission Guard.
3. Open the "Submission Guard" menu in wp-admin, enable protection for the required CF7 forms, map their fields, and configure the shared rules.

== Changelog ==

= 0.3.0 =
* Admin settings UX: widened the "Field" column in the Logs table, gave the "Fields checked by content rules" block a proper section heading, and fixed the "Settings saved." notice never appearing after save (auto-dismisses after 5 seconds now).
* Every `country_select` field on a form is now validated automatically — replaced the manual per-form "Country fields" checkbox toggle with auto-detection, and fixed `country_select` fields slipping into the generic "Fields checked by content rules" list (only plain `select` was excluded before).
* Added extension points for add-ons (e.g. OliForge CF7 Submission Guard Pro): `oliforge_cf7sg_domains_panel` action to render extra UI in the Domains tab, `oliforge_cf7sg_sanitize_settings` filter to persist extra settings keys, and `oliforge_cf7sg_domain_check` filter to add extra email-domain checks beyond the core blocklist.

= 0.2.5 =
* Moved textdomain loading from plugins_loaded to init: since WordPress 6.7, loading a plugin's textdomain before the init hook can trigger a "_load_textdomain_just_in_time was called incorrectly" notice. The plugin still ships and loads its own bundled translations (it isn't distributed through WordPress.org language packs), so load_plugin_textdomain() itself stays — only its timing changed.

= 0.2.4 =
* Fixed: unchecking every field in a form's "Country fields" list and saving had no effect — the previous selection was silently kept instead of being cleared, because unchecked checkboxes submit nothing and the save handler couldn't tell that apart from the panel not being shown at all.
* Country validation now uses OliForge CF7 Country Select's structured `get_country_context()` API (3.2.7+) when available: an unresolvable list: (unknown/deleted slug, or Pro inactive) is now logged as its own `country_list_unresolved` rule, distinct from `invalid_country` for a genuinely bad submission — the visitor sees the same message either way, but the two are easy to tell apart in the Logs and dashboard rule breakdown.
* Refreshed translation strings for the 0.2.3 country-field changes ("Country fields" checkbox group) across all 5 bundled locales, and fixed their Project-Id-Version header, which msgmerge doesn't update on its own.

= 0.2.3 =
* A country_select field is now always validated through OliForge CF7 Country Select's API — a leftover value in the (now-hidden) legacy allowed_countries setting could previously override a Pro list: option instead of the field's own configuration.
* A protected form can now have more than one country field (e.g. billing vs. shipping): every country_select field is protected by default, and each is checked independently. The single "Country field" dropdown per form is replaced with a "Country fields" checkbox group.
* country_select fields fail closed when Country Select itself can't resolve a list: option (unknown/deleted list, or Pro inactive): previously an unresolvable list silently fell back to accepting no value as invalid only if a legacy allowlist happened to also be empty; now it's always rejected.

= 0.2.2 =
* Country validation now uses OliForge CF7 Country Select's public `get_allowed_country_codes()` API (3.2.5+) when available, instead of a no-op: it checks the submitted value against exactly the set that field's admin allowlist, include/exclude options, and Pro named lists allow, and logs a genuine `invalid_country` block when it doesn't match.

= 0.2.1 =
* Validate native CF7 select values, including pipe-mapped and multiple values.
* Exclude select and dedicated email, country, and consent fields from generic content rules.
* Use an atomic fixed-window database rate limiter.
* Separate the plugin version from the database schema version.
* Refresh translation metadata and release packaging.

= 0.2.0 =
* Discover saved Contact Form 7 forms and their fields automatically.
* Add an independent protection profile for every form, including field mapping and length rules.
* Apply validation, timing tokens, rate limits, duplicate detection, and logs only to enabled forms.
* Keep newly discovered forms disabled until an administrator explicitly enables them.

= 0.1.3 =
* Record Monitor only rule matches as monitored instead of blocked and report them separately on the dashboard.

= 0.1.2 =
* Store duplicate-submission fingerprints only after Contact Form 7 successfully sends the message.

= 0.1.1 =
* Show country settings and run country validation only when OliForge CF7 Country Select is active.

= 0.1.0 =
* Initial release.
