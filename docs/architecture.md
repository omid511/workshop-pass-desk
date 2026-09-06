# Architecture

```text
WooCommerce order hooks ----> pass issuance + capacity lock
                                      |
WordPress admin / shortcodes -> workshop + session service
                                      |
                         pass / waitlist / attendance tables
                                      |
                         audit events + owner-scoped export
```

order mechanics. A unique (order, item, unit-index) key makes completion-hook replay safe
and gives one pass per ordered unit; capacity is checked while the workshop row is locked;
pass secrets are stored as an HMAC verification hash with an encrypted attendee recovery value.
Capabilities, nonces, ownership, escaping, and prepared SQL are applied at the
WordPress boundary.

## Deliberate trade-offs

- Synchronous issuance and check-in avoid correctness depending on WP-Cron.
- Playground makes the public demo reproducible, but it is ephemeral and not a
  substitute for a persistent WordPress integration environment.
- The integration smoke path is documented instead of pretending that a PHP
  contract test is a full WooCommerce test site.
