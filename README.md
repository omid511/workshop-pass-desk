# Workshop Pass Desk

[![CI](https://github.com/omid511/workshop-pass-desk/actions/workflows/ci.yml/badge.svg)](https://github.com/omid511/workshop-pass-desk/actions/workflows/ci.yml)
[![Issues](https://img.shields.io/github/issues/omid511/workshop-pass-desk)](https://github.com/omid511/workshop-pass-desk/issues)

Workshop Pass Desk is a WordPress/WooCommerce operations product for running paid workshops end to end: organizers configure a workshop and multi-session schedule, sell capacity-limited passes, manage waitlists, issue revocable attendee credentials, check people in safely, and export attendance evidence. It does not lock content, create subscription roles, or implement generic membership behavior.

## End-to-end flow

1. Activate WooCommerce and this plugin. Activation creates versioned workshop, session, pass, attendance, waitlist, and audit-event tables and grants administrators `manage_workshop_passes`.
2. Open **Pass Desk → Workshop Pass Desk**, create an active workshop with a site-timezone window and capacity, then add one or more sessions. Session windows govern check-in when a schedule exists.
3. Create a simple WooCommerce product. In its product data, set **Workshop ID** to the workshop ID. For variable products, each variation can carry its own **Workshop ID** and falls back to the parent product mapping.
4. Complete a purchase with a test gateway. One pass is issued per ordered unit, so a quantity of 2 yields two passes. Completion and payment hooks are safe to replay because `(order_id, order_item_id, item_index)` is unique; capacity is locked transactionally.
5. If the workshop is full, a visitor can use `[wpd_waitlist workshop_id="123"]`. When a non-used pass is cancelled or refunded, the oldest pending entry is invited and receives an email.
6. The customer sees the credential and schedule on the order-received page (including guest checkout via the order key) or `[wpd_my_passes]` when signed in. An organizer can use **Pass Desk → Check in**, or `[wpd_check_in]` on an organizer-only page.

## Security and state rules

- Codes use `random_bytes` and are stored as an HMAC hash under a dedicated per-site secret (rotatable; old passes keep verifying). An encrypted recovery value is stored so the signed-in attendee can see their code without storing plaintext; the hash is the verification source of truth. Issuance fails closed when cryptography is unavailable.
- Check-in reports `valid`, `already_checked_in`, `cancelled`, `out_of_window`, `invalid`, or `rate_limited`. The guarded update and the attendance insert run in one transaction; the unique attendance key and affected-row check make concurrent check-ins idempotent.
- Admin mutations require `manage_workshop_passes` and WordPress nonces, including variation-level workshop mapping. Workshop reads, updates, and deletes are scoped to the current owner; capacity cannot drop below issued passes and workshops with passes cannot be deleted. SQL uses `$wpdb->prepare`; rendered values are escaped.
- Full refunds and cancellations revoke passes that have not checked in; partial refunds revoke exactly the refunded units. Subscription renewals never mint passes. A checked-in pass remains an attendance record and is not silently erased.
- Capacity is enforced inside a transaction while the workshop row is locked. WP-Cron is not used for check-in validity, so missed cron cannot grant access.
- Session creation, waitlist joins/invitations, issuance, check-in, cancellation, secret rotation, mail failures, and workshop changes are recorded in the event table. Schema upgrades run locked on admin screens only, never on public page views.
- CSV exports are owner-scoped, escape spreadsheet formula triggers, and contain operational identifiers/timestamps, not pass secrets. The customer-facing waitlist form validates email, uses a per-workshop nonce, and is rate limited; invites send first-come, first-served only on delivered mail.
- Single-site only; network activation shows a warning. Uninstall keeps data by default unless erase is opted in.

## Versions and deployment

The maintained target is PHP 8.3+ with ext-openssl, WordPress 6.6+, and WooCommerce 9.0+. The plugin declares WooCommerce HPOS (`custom_order_tables`) compatibility and requires WooCommerce via the `Requires Plugins` header, so WordPress installs it as a dependency. CI runs PHP linting, deterministic plugin-level contract tests, and packages a ZIP artifact. A full WordPress/WooCommerce integration smoke test is documented below because this repository does not vendor a test site.

Pull requests and pushes also run dependency review. PHP is not supported by
GitHub CodeQL, so the repository uses a credential-free Semgrep Community
Edition scan with PHP-aware rules on pull requests, pushes to `main`, and a
weekly schedule. Published `v*` GitHub releases re-run the package checks and
attach the plugin ZIP plus a SHA-256 checksum; verify it with
`sha256sum -c workshop-pass-desk.zip.sha256`.

The included [Playground Blueprint](blueprint/blueprint.json) installs the latest WordPress and WooCommerce releases first, then loads the release-attached plugin ZIP (whose extracted folder is `workshop-pass-desk`), and lands an admin on the workshops page. For a local ZIP demo, mirror the CI folder layout so the upload has a detectable plugin header:

```sh
rm -rf dist && mkdir -p dist/workshop-pass-desk
cp workshop-pass-desk.php uninstall.php README.md readme.txt LICENSE dist/workshop-pass-desk/
cp -R includes assets languages dist/workshop-pass-desk/
(cd dist && zip -qr workshop-pass-desk.zip workshop-pass-desk)
```

Upload the ZIP in Playground or WordPress admin. In Playground, use a test gateway only; Playground is ephemeral unless exported. For a persistent host, use HTTPS, PHP 8.3 with ext-openssl, MySQL 8/MariaDB 10.6+, at least 256 MB memory, a host that permits custom plugins, and a working mail path (SMTP) for waitlist invites. WP-Cron stays on for WooCommerce itself; the plugin's issuance and check-in do not depend on it. Do not use production payment credentials.

## Integration smoke path

On a disposable WordPress install with WooCommerce and HPOS enabled: activate the plugin; create an active workshop with two sessions and a mapped product; purchase with a test gateway; replay the order completion hook; assert one pass per unit purchased; fill capacity and join the waitlist; try the same code from two browser tabs; check in during and outside a session; refund an unused order; confirm the cancelled result and waitlist email; export CSV; and verify the event trail. Check just before, during, and after the workshop/session windows in the site timezone. WP-Cron limitations do not affect synchronous issuance or check-in; reminder delivery remains an explicit host integration boundary.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
