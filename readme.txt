=== OliForge CF7 Submission Guard ===
Contributors: oliforge
Tags: contact form 7, validation, spam, security, rate limit
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 0.2.0
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
