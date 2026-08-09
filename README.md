# Workshop Pass Desk

Workshop Pass Desk is a WordPress/WooCommerce operations product for running paid workshops end to end: organizers configure a workshop and multi-session schedule, sell capacity-limited passes, manage waitlists, issue revocable attendee credentials, check people in safely, and export attendance evidence. It does not lock content, create subscription roles, or implement generic membership behavior.

## End-to-end flow

1. Activate WooCommerce and this plugin. Activation creates versioned workshop, session, pass, attendance, waitlist, and audit-event tables and grants administrators `manage_workshop_passes`.
2. Open **Pass Desk → Workshop Pass Desk**, create an active workshop with a site-timezone window and capacity, then add one or more sessions. Session windows govern check-in when a schedule exists.
3. Create a simple WooCommerce product. In its product data, set **Workshop ID** to the workshop ID.
4. Complete a purchase with a test gateway. A pass is issued once per order item. Completion and payment hooks are safe to replay because `(order_id, order_item_id)` is unique; capacity is locked transactionally.
5. If the workshop is full, a visitor can use `[wpd_waitlist workshop_id="123"]`. When a non-used pass is cancelled or refunded, the oldest pending entry is invited and receives an email.
6. The customer sees the credential and schedule on the order-received page or `[wpd_my_passes]`. An organizer can use **Pass Desk → Check in**, or `[wpd_check_in]` on an organizer-only page.
7. Organizers can export a workshop’s pass/attendance CSV and review the immutable event history through the service layer for operational reporting.

## Security and state rules

- Codes use `random_bytes`, are normalized before use, and are stored as an HMAC hash. An encrypted recovery value is stored so the signed-in attendee can see their code without storing plaintext; the hash is the verification source of truth.
- Check-in reports `valid`, `already_checked_in`, `cancelled`, `out_of_window`, `invalid`, or `rate_limited`. The update requires `checked_in_at IS NULL`; the unique attendance key and affected-row check make concurrent check-ins idempotent.
- Admin mutations require `manage_workshop_passes` and WordPress nonces. Workshop reads and updates are scoped to the current owner. SQL uses `$wpdb->prepare`; rendered values are escaped.
- Full refunds and cancellations revoke passes that have not checked in. A checked-in pass remains an attendance record and is not silently erased.
- Capacity is enforced inside a transaction while the workshop row is locked. WP-Cron is not used for check-in validity, so missed cron cannot grant access.
- Session creation, waitlist joins/invitations, issuance, check-in, cancellation, and workshop changes are recorded in the event table. Repeated plugin activation runs `dbDelta` and the version upgrade hook so schema additions are not silently skipped.
- CSV exports are owner-scoped and contain operational identifiers/timestamps, not pass secrets. The customer-facing waitlist form validates email and uses a per-workshop nonce.

## Versions and deployment

The maintained target is PHP 8.3+, WordPress 6.9+, and the current WooCommerce release. CI runs PHP linting, deterministic plugin-level contract tests, and packages a ZIP artifact. A full WordPress/WooCommerce integration smoke test is documented below because this repository does not vendor a test site.

The included [Playground Blueprint](blueprint/blueprint.json) installs WordPress and WooCommerce and loads the direct public GitHub codeload archive (whose extracted folder is `workshop-pass-desk-main`). For a local ZIP demo:

```sh
zip -r workshop-pass-desk.zip workshop-pass-desk.php includes assets README.md LICENSE
```

Upload the ZIP in Playground or WordPress admin. In Playground, use a test gateway only; Playground is ephemeral unless exported. For a persistent host, use HTTPS, PHP 8.3, MySQL 8/MariaDB 10.6+, at least 256 MB memory, and a host that permits custom plugins and WP-Cron. Do not use production payment credentials.

## Integration smoke path

On a disposable WordPress install with WooCommerce and HPOS enabled: activate the plugin; create an active workshop with two sessions and a mapped product; purchase with a test gateway; replay the order completion hook; assert one pass; fill capacity and join the waitlist; try the same code from two browser tabs; check in during and outside a session; refund an unused order; confirm the cancelled result and waitlist email; export CSV; and verify the event trail. Check just before, during, and after the workshop/session windows in the site timezone. WP-Cron limitations do not affect synchronous issuance or check-in; reminder delivery remains an explicit host integration boundary.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
