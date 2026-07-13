# VeriFact 3.3: Nine Strong Recommendations

Date: July 14, 2026
Scope: VeriFact WordPress and VeriFact API

These recommendations build on the completed 3.2 authenticated integration, contract negotiation, bulk workflows, provenance verification, dead-letter recovery, audit exports, and cross-project tests.

## 1. Create a persistent claim registry and semantic duplicate detection

Give every normalized claim a stable claim ID, retain its verification history, and detect semantically equivalent claims across posts, sites, and API projects.

- **WordPress:** warn editors when a claim has already been checked, reuse approved evidence, and show whether the prior decision is still fresh.
- **API:** add claim-registry endpoints, embedding-based similarity search, freshness windows, canonical-claim merging, and tenant-safe deduplication.
- **Benefit:** lowers verification cost and prevents contradictory decisions about the same claim.

## 2. Add collaborative human-review cases

Turn uncertain or high-risk findings into assignable review cases with owners, due dates, comments, decisions, and escalation rules.

- **WordPress:** add an editorial review inbox, assignments, post-level discussion, approval history, and notification controls.
- **API:** expose case, assignment, comment, decision, and escalation endpoints with role-based permissions and immutable decision history.
- **Benefit:** makes VeriFact usable by newsrooms, agencies, compliance teams, and multi-author publishers.

## 3. Build a sandboxed evidence-provider extension system

Publish a provider interface that allows approved search, database, academic, government, and organization-specific evidence connectors.

- **WordPress:** let administrators select allowed providers per site, content type, jurisdiction, or publication workflow.
- **API:** add signed provider packages, capability manifests, health scoring, timeouts, quotas, sandboxing, and a certification test kit.
- **Benefit:** expands evidence coverage without coupling the core platform to every provider.

## 4. Add cryptographically signed verification receipts

Issue a portable, tamper-evident receipt for each completed verification, including the claim digest, evidence digest, policy version, model version, timestamp, and decision.

- **WordPress:** attach receipts to revisions and optionally publish a reader-facing verification record.
- **API:** sign receipts with rotating keys, publish verification keys, support receipt validation, and provide append-only transparency checkpoints.
- **Benefit:** allows third parties to prove that a result has not been altered after review.

## 5. Build explainable conflict and source-diversity maps

Go beyond a single confidence score by showing which evidence supports, disputes, or qualifies a claim and how independent the sources are.

- **WordPress:** provide an accessible evidence map, conflict summary, source-type balance, and plain-language rationale in the editor.
- **API:** return typed evidence relationships, source ownership clusters, corroboration depth, conflict severity, and confidence contributors.
- **Benefit:** makes decisions easier to understand and reduces false confidence from many sources repeating one origin.

## 6. Add jurisdiction-aware policy packs

Support reusable verification and publishing policies for medical, financial, political, scientific, legal, advertising, and regional compliance contexts.

- **WordPress:** allow sites and post types to select a policy pack, required evidence types, approval thresholds, and disclosure language.
- **API:** version policy packs, evaluate requests against them, report triggered rules, and preserve the exact policy version in audit records.
- **Benefit:** converts generic fact checking into enforceable, context-sensitive editorial governance.

## 7. Offer private self-hosted and edge verification modes

Provide a deployment profile for customers who cannot send unpublished content to a shared service.

- **WordPress:** support a local or private API profile with clear capability and health reporting.
- **API:** ship a hardened self-hosted edition with local inference adapters, private evidence indexes, offline queues, encrypted storage, and controlled update channels.
- **Benefit:** opens regulated, confidential, government, legal, and enterprise use cases.

## 8. Add entitlement, licensing, and organization administration

Create a consistent commercial control plane for plans, seats, projects, feature entitlements, provider costs, and overage approval.

- **WordPress:** show plan capabilities, usage, limits, renewal state, and organization-managed connection profiles without exposing billing secrets.
- **API:** add organization roles, entitlement checks, metering events, invoice-ready exports, budget alerts, and safe grace-period behavior.
- **Benefit:** makes the platform commercially operable without scattering licensing logic throughout the plugin and API.

## 9. Establish a certified integration ecosystem

Create conformance suites and reference integrations for browser extensions, command-line tools, static-site generators, additional CMS platforms, and developer-built applications.

- **WordPress:** serve as the reference CMS implementation and publish reusable integration patterns for editor state, background work, provenance, and publication gates.
- **API:** provide generated SDKs, a mock server, contract fixtures, webhook simulators, certification badges, compatibility matrices, and an integration directory.
- **Benefit:** turns VeriFact from two products into a dependable platform developers can extend.

## Recommended delivery order

1. Persistent claim registry.
2. Collaborative review cases.
3. Explainable conflict and source-diversity maps.
4. Jurisdiction-aware policy packs.
5. Signed verification receipts.
6. Provider extension system.
7. Self-hosted and edge modes.
8. Entitlement and organization administration.
9. Certified integration ecosystem.

The first three recommendations provide the highest immediate editorial value. Signed receipts, policy packs, and private deployments then create a strong enterprise trust layer. Licensing and ecosystem certification should follow once those core contracts are stable.
