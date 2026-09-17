=== OliForge CF7 Submission Guard ===
Contributors: oliforge
Tags: contact form 7, validation, spam, security, rate limit
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 0.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Configurable server-side submission rules and lightweight logs for Contact Form 7.

== Description ==

OliForge CF7 Submission Guard validates Contact Form 7 submissions before mail is sent. It can enforce field length rules, block URLs and email-like content, validate country values when OliForge CF7 Country Select is active, block email domains, rate-limit by IP, require a minimum completion time, detect duplicate messages, validate a configured consent field, and keep lightweight security logs without storing submitted message contents.

== Installation ==

1. Install and activate Contact Form 7.
2. Upload and activate OliForge CF7 Submission Guard.
3. Open the "Submission Guard" menu in wp-admin and configure field names/rules under Settings.

== Changelog ==

= 0.1.2 =
* Replaced the fixed Name/Message length settings with a repeatable "Field length rules" list on the Core fields tab, so a minimum and/or maximum character length can be enforced on any CF7 field, not just name and message.

= 0.1.1 =
* Show country settings and run country validation only when OliForge CF7 Country Select is active.

= 0.1.0 =
* Initial release.
