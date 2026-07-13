# VeriFact WordPress 3.3.0

A production-oriented editorial verification client for the VeriFact API.

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
