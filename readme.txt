=== Workshop Pass Desk ===
Contributors: omid511
Tags: woocommerce, workshops, ticketing, check-in, waitlist
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 8.3
Stable tag: 0.3.0
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Run paid workshops end to end: sell capacity-limited passes through
WooCommerce, issue revocable attendee credentials, check people in safely,
run waitlists, and export attendance evidence.

== Description ==

Workshop Pass Desk is a WooCommerce extension for organizers who run paid
workshops, classes, and multi-session trainings. A customer buys a pass with
a normal WooCommerce checkout; the plugin issues one revocable credential
per ordered unit, shows it on the order-received page, and lets organizers
check attendees in from a mobile-friendly desk.

* Capacity-limited workshops with multi-session schedules
* One pass per ordered unit, honoring quantities and product variations
* Revocable HMAC-hashed codes (no plaintext secrets stored)
* Double-scan-safe check-in with session windows
* Waitlist with automatic invites when passes are cancelled or refunded
* Immutable audit events and owner-scoped CSV attendance export
* Compatible with high-performance order storage (HPOS)

Single-site only; network activation is not supported.

== Installation ==

1. Install and activate WooCommerce 9.0 or later.
2. Upload `workshop-pass-desk.zip` via Plugins → Add New → Upload Plugin.
3. Activate Workshop Pass Desk.
4. Open Pass Desk → Workshop Pass Desk, create a workshop with sessions.
5. Create a WooCommerce product and set its Workshop ID to the workshop.
6. Use a test payment gateway first; confirm passes issue on purchase.

== Frequently Asked Questions ==

= Do check-in and issuance need WP-Cron? =

No. Issuance and check-in run synchronously. WP-Cron stays on for
WooCommerce itself, but missed cron runs cannot grant access.

= What happens on refunds? =

Full refunds and cancellations revoke passes that have not checked in.
Partial refunds revoke exactly the refunded units. Checked-in passes remain
as attendance records.

= What if my mail provider is down? =

Waitlist entries stay pending and are retried on the next cancellation; a
`mail_failed` event records the miss. No seat is silently marked filled.

= Does uninstall delete my data? =

Only if you opt in. Workshop data is kept by default; set the erase option
to remove everything on uninstall.

== Changelog ==

= 0.3.0 =
* Partial refunds revoke exactly the refunded units; subscription renewals
  no longer mint passes.
* Dedicated per-site pass secret with rotation; passes survive salt changes.
* Checkout fails closed with an admin notice when cryptography is missing.
* Variation mapping requires a nonce; unowned mappings surface an error.
* Waitlist invites send only on delivered mail; joins are rate limited.
* Atomic check-in; CSV formula-injection guard; workshop edit/delete UI.
* Capacity cannot drop below issued passes; single-site guard; locked,
  admin-only schema upgrades; erase-on-uninstall opt-in.

= 0.2.0 =
* Declared HPOS compatibility; reconciled versions; hardened Playground
  blueprint; aligned ZIP packaging; quantity/variation/guest issuance.

= 0.1.0 =
* Published the end-to-end workshop operations plugin.
