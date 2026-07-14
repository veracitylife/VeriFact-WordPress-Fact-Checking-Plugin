=== VeriFact Checker ===
Contributors: veracityintegrity
Tags: fact checking, editorial, evidence, verification
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 3.4.0
License: MIT
License URI: https://opensource.org/license/mit

Evidence-backed fact checking for WordPress through a private VeriFact API.

== Description ==

VeriFact Checker provides durable queued document review, revision-aware claim checking, Gutenberg and Classic Editor tools, publication gates, evidence citations, approved ClaimReview markup, privacy controls, and enterprise-safe API connections. API credentials remain on the server.

== Installation ==

1. Upload and activate the plugin.
2. Configure the official VERIFACT_API_BASE_URL, then subscribe or activate a license under VeriFact > Subscription & License. Managed installations may continue using a Vault-injected VERIFACT_API_KEY.
3. Configure access, retention, publication gates, confidence, post types, and allowed API hosts under VeriFact > Settings.
4. Use the editor panel, Classic Editor meta box, [verifact], or [verifact_citations].
5. For reliable processing, schedule `wp verifact queue run` every minute or install Action Scheduler.

== Changelog ==

= 3.4.0 =
* Added hosted subscription checkout, secure one-time license activation, site-bound short-lived API tokens, plan usage, datasource visibility, and customer billing management.

= 3.2.0 =
* Added authenticated API capability, identity, scope, and contract handshakes.
* Added transient retries, circuit breaking, safe error mapping, and provenance verification.
* Added bulk queue workflows, dead-letter recovery, audit integration, and cross-project contract tests.

= 3.1.0 =
* Added network-wide multisite installation and cleanup.
* Bound editorial overrides and ClaimReview approval to the exact content revision.
* Added freshness-controlled claim reuse and page-builder content extraction.

= 3.0.0 =
* Added durable jobs, revision-aware review, editor compatibility, publication gates, evidence library, accessibility, diagnostics, enterprise controls, and expanded compatibility testing.
