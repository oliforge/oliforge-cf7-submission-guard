# OliForge CF7 Submission Guard v0.4.0 — smoke test

Use a Contact Form 7 form. Enable it under Submission Guard and map its fields first; newly discovered forms stay disabled until enabled.

## Core rules

1. A valid submission after the configured minimum time should pass.
2. A name longer than the configured maximum should be rejected.
3. A message shorter than the configured minimum should be rejected.
4. URL forms such as `https://example.com`, `www.example.com`, and `example.com` in protected fields should be rejected.
5. `@` and email-like text in protected fields should be rejected when enabled.
6. HTML tags should be rejected when enabled.
7. An email from a blocked domain or its subdomain should be rejected.
8. A native `select` value not offered by the field (including pipe-mapped and multiple values) should be rejected.
9. A form submitted before the minimum completion time should be rejected, including if the timing signature is missing or altered.
10. After the configured number of attempts from one IP within the window, further attempts should be rejected.
11. Duplicate message blocking should reject the same message from the same IP within its window when enabled, and only after a previous message was actually sent.
12. Required consent should reject an empty configured consent field when enabled.
13. Monitor mode should log rule matches without invalidating the CF7 submission, and the dashboard should report them as monitored.

## Per-form profiles

14. A newly created CF7 form appears in settings disabled and is not validated until enabled.
15. Two enabled forms with different length rules each apply their own rules.

## Country fields (requires OliForge CF7 Country Select)

16. Every `country_select` field on a form is validated; a value outside the field's allowed set is rejected (`invalid_country`).
17. With two country fields on one form, each is checked independently.
18. A field whose list cannot be resolved (deleted list, Pro inactive) is rejected and logged as `country_list_unresolved`.

## Logs

19. Logs must not contain the submitted name, email address, or message body.
20. The Form column shows the CF7 form title, not a hash.
21. "All events" is paginated at 30 entries per page.
22. "Clear all logs" removes blocked/monitored entries only; passed entries (Pro) are kept.
23. Saving settings shows the "Settings saved." notice, which dismisses after 5 seconds.

## Pro add-on (OliForge CF7 Submission Guard Pro; requires free 0.4.0+)

24. Without the free plugin, or with a free version older than 0.4.0, Pro shows an admin error notice and does not load its features.
25. Allowlist: an email from a domain outside the allowlist is rejected; one inside it passes.
26. Disposable domains: an address on the list is rejected; "Refresh now" updates the list, and the bundled list is used if the download fails.
27. MX validation: an address whose domain has no mail server is rejected; the result is cached for 24 hours per domain.
28. Passed journal is off by default: no "Passed" tab entries appear until Settings → Logging enables it.
29. With the journal enabled, a passing submission appears in the Passed tab with name, email, IP, form title and message body.
30. The Passed tab paginates at 30 per page, sorts by date and by sender, and its search matches any field.
31. The Passed tab's "Clear all" empties only that journal; entries also expire after the retention period.
32. Uninstalling Pro removes the Passed journal data.
