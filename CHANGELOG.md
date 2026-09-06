# Changelog

## 0.2.0 - 2026-09-06

- Declared WooCommerce HPOS (`custom_order_tables`) compatibility and added
  `Requires Plugins`, `Tested up to`, and `WC tested up to` headers, so
  HPOS-only installs activate without an incompatibility warning.
- Reconciled the plugin header, `WPD_VERSION`, and release tag scheme on
  0.2.0; schema version is now 1.2.0.
- Hardened the Playground blueprint: WooCommerce installs before the plugin,
  the release-attached ZIP replaces the branch codeload archive, the demo
  signs in as admin so the workshops landing page capability check passes,
  and networking plus latest stable WordPress/WooCommerce are requested.
- Aligned the manual ZIP recipe with the CI folder-ZIP layout so both
  uploads expose a detectable plugin header.
- Issuance now honors line-item quantity (one pass per unit), resolves
  variation-level workshop mappings with parent fallback, and widens the
  idempotency key to `(order_id, order_item_id, item_index)` with an
  automatic 1.2.0 upgrade migration.
- Guests now see their passes on the order-received/thank-you page via an
  order-key-checked lookup instead of the signed-in customer query.
- Enqueued the bundled admin stylesheet on Pass Desk screens, load the
  plugin textdomain, clean up tables/options/capabilities on uninstall, and
  documented the ext-openssl requirement.
- Live deployment verified on a hosted WordPress/WooCommerce site with HPOS enabled: clean activation, purchase to pass issuance, check-in windows, refund/waitlist flow, and CSV export all pass.

## 0.1.0 - 2026-08-09

- Published the end-to-end workshop operations plugin.
- Added versioned schema upgrades, multi-session schedules, capacity-safe pass
  issuance, waitlist promotion, revocable credentials, check-in controls,
  owner-scoped CSV export, audit events, Playground, CI, and release packaging.
