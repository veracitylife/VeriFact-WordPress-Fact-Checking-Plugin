# VeriFact WordPress Audit

## Imported baseline

- Baseline tag: `audit-import-20260713T180539Z`
- Baseline commit: `0bd264f06252092c984fa23aa7c76e29b5e8726c`
- Working branch: `feature/verifact-rebuild`
- Imported header version: 2.0.8
- Historical nested distribution: 2.0.5, archived because it contained a PHP parse error

## Resolved for 2.1.0

- Implemented all previously missing administrative callbacks.
- Moved API credentials out of WordPress options and into server-injected configuration.
- Added REST authorization, admin capability checks, consistent nonces, bounded inputs, safe upstream requests, caching, and hourly limits.
- Reduced client metadata collection; it is disabled by default and IP values are hashed when enabled.
- Added activation-only schema migration, complete uninstall cleanup, tests, CI, and repeatable ZIP packaging.

## Still required before production release

- Run WordPress Plugin Check and WordPress Coding Standards in a real WordPress test environment.
- Perform administrator, editor, subscriber, anonymous, multisite, and uninstall integration tests.
- Verify the final API base URL, Vault mapping, proxy configuration, and TLS certificate on the selected host.
- Review licensing, public branding, privacy wording, and WordPress.org metadata before distribution.