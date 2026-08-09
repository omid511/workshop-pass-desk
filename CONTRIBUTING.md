# Contributing

This is a portfolio-quality WordPress/WooCommerce plugin demo. Keep changes
focused on the workshop operations workflow and preserve capability checks,
nonces, owner scoping, escaping, prepared SQL, replay safety, and versioned
schema upgrades.

## Before opening a pull request

```bash
find . -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/test_contract.php
rm -rf dist
mkdir -p dist/workshop-pass-desk
cp workshop-pass-desk.php README.md LICENSE dist/workshop-pass-desk/
cp -R includes assets dist/workshop-pass-desk/
(cd dist && zip -qr workshop-pass-desk.zip workshop-pass-desk)
```

Use a short imperative commit subject and document schema or WooCommerce
compatibility implications. Never commit payment credentials, production
attendee data, or copied branding/assets.
