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
