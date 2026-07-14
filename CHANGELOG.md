# Changelog

## 3.5.0

- Added normal-prose inline source citations with hover and keyboard-focus factuality notes.
- Added automatic rendering from API annotations and portable `{{verifact|URL|NOTE}}` markers.
- Added direct-source new-tab links with safe rel attributes and accessible labels.
- Removed the requirement for Fact/Commentary paragraph formatting or a mandatory end-of-post report.

## 3.4.0

- Added customer-friendly Stripe-hosted subscription signup and billing management.
- Added one-time license activation without saving the raw license in WordPress.
- Added Ed25519 site identity and short-lived in-memory API tokens.
- Added plan, website-seat, monthly-usage, and datasource entitlement views.
- Added safe website deactivation and subscription recovery messages.

## 3.3.0

- Added the shared claim registry and semantic duplicate search.
- Added collaborative human-review cases, assignments, comments, and decisions.
- Added sandboxed evidence-provider manifests and policy-pack administration.
- Added signed verification receipts and transparency-log access.
- Added evidence conflict/source-diversity maps and private deployment profiles.
- Added organization entitlements and integration conformance certification.
- Added the Platform admin screen and secret-safe WordPress proxy routes.
- Added a reproducible npm lockfile for GitHub Actions.
# Changelog

## 3.2.0 - 2026-07-14

- Added authenticated API handshake and client contract negotiation.
- Added bounded retries, circuit breaking, structured upstream errors, and response provenance verification.
- Added bulk post queues, dead-letter requeue controls, redacted connection diagnostics, and API contract tests.

## 3.1.0 - 2026-07-14

- Completed network-wide database/capability lifecycle for multisite.
- Added explicit page-builder source extraction hooks.
- Added freshness-bounded claim reuse.
- Invalidated publication overrides and ClaimReview approval whenever content changes.

## 3.0.0 - 2026-07-14

- Replaced WP-Cron-only review processing with a durable database queue, locks, retries, stale recovery, Action Scheduler, and WP-CLI execution.
- Added Classic Editor, custom-post-type, block-theme, page-builder, and revision-aware claim support.
- Added editorial publication gates, audited overrides, evidence library, citations, and approved ClaimReview output.
- Added accessibility/RTL improvements, redacted diagnostics, connection profiles, host restrictions, and key-rotation reporting.
- Expanded CI across PHP and WordPress versions with large-document and package checks.

## 2.2.0 - 2026-07-14

- Added Gutenberg full-post review sidebar and source insertion.
- Added asynchronous private review jobs, post reports, and changed-since-review state.
- Added custom capabilities, multisite activation, retention, deletion, privacy export/erasure, and complete uninstall cleanup.
- Added API/database/scheduler Site Health tests and support diagnostics.
- Added WordPress 6.8 integration environment, expanded CI, and module-aware release packaging.

## 2.1.0 - 2026-07-14

- Hardened the REST proxy, settings, admin pages, authentication, caching, rate limiting, history, and packaging.
