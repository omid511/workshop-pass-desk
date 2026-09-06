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

## GitHub Actions policy

Workflow actions use maintained major tags rather than floating branch names:
for example, checkout `v6`, dependency review `v5`, and artifact upload `v4`.
This keeps routine upstream fixes flowing while making major-version changes
visible in reviewed Dependabot updates. Keep workflow permissions at the
smallest scope needed by each job.

GitHub CodeQL does not analyze PHP. The repository therefore uses the
credential-free Semgrep Community Edition workflow for PHP security scanning;
keep it free of secrets and do not replace it with a nominal CodeQL workflow.

## Releases

Publish a GitHub release from a `v*` tag. The release workflow re-runs PHP
linting and contract tests, packages the plugin ZIP, and uploads a SHA-256
checksum. Keep the release artifact reproducible from the tagged source.
