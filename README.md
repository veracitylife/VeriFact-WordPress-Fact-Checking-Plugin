# VeriFact WordPress 3.5.0

A production-oriented editorial verification client for the VeriFact API.

Installation, onboarding, queue, upgrade, and rollback procedure: [`Installation.md`](Installation.md).

## Version 3.0 capabilities

- Durable database-backed jobs with locking, retries, stale-lock recovery, Action Scheduler integration, WP-Cron fallback, and a system-cron-compatible WP-CLI runner.
- Gutenberg sidebar, Classic Editor meta box, block themes, custom post types, and page-builder-compatible post-body review hooks.
- Revision-aware claim hashing that reuses unchanged results and checks only changed claims.
- Configurable publication gates, confidence thresholds, post-type policies, and audited human overrides.
- Reusable evidence library, citation shortcode, and human-approved Schema.org `ClaimReview` output.
- Keyboard and screen-reader status, logical RTL borders, forced-colors support, reduced motion, and translatable UI strings.
- Queue performance metrics, Site Health checks, and a redacted support bundle that excludes content, credentials, users, and network identifiers.
- Server-injected connection profiles, API-host allowlists, key-rotation status, and multisite-safe capabilities.
- PHP 8.1-8.4, WordPress 6.2/6.8/master, Plugin Check, large-post, JavaScript, packaging, and integration CI definitions.

## Configuration

Map `VERIFACT_API_BASE_URL` and `VERIFACT_API_KEY` from OpenClaw Vault or an approved secret store. For enterprise installations, also use `VERIFACT_CONNECTION_PROFILES`, `VERIFACT_ACTIVE_PROFILE`, `VERIFACT_ALLOWED_API_HOSTS`, `VERIFACT_API_KEY_ID`, and `VERIFACT_API_KEY_ROTATED_AT`. Never store raw credentials in WordPress options or this repository.

If real system cron is available, run `wp verifact queue run` every minute. Action Scheduler is used automatically when available; WP-Cron remains a fallback.

## Commands

- `composer test`
- `npm run check`
- `npm run wp:start` then `npm run test:integration`
- `npm run test:large-post`
- `powershell -ExecutionPolicy Bypass -File tools/build-plugin.ps1`

The built package is `dist/verifact.zip`.


## Version 3.4 subscription experience

- Stripe-hosted signup and customer billing portal, with no card data handled by WordPress.
- Automatic website activation after successful checkout, plus one-time activation for existing licenses.
- Ed25519 site identity derived from existing WordPress salts; the raw license and permanent API credentials are never saved in WordPress.
- Fifteen-minute in-memory API bearer tokens, plan status, website seats, monthly usage, and datasource visibility.
- Clear subscription, activation, connection, billing, and deactivation recovery messages.

The official API base URL must be injected through `VERIFACT_API_BASE_URL` or configured under VeriFact > Settings before signup. Do not guess or hard-code a deployment URL until the production API route is confirmed.


Version 3.5 renders verified claims as normal prose with compact inline source links and accessible factuality tooltips.
