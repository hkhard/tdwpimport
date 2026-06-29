# ADR 0001: API interface — admin-ajax vs WordPress REST API

- **Status:** Proposed (pending ratification)
- **Date:** 2026-06-29
- **Bead:** tdwp-3mm
- **Related:** tdwp-9pi (Build REST API v1 for mobile controller), tdwp-2mn (Integrate Expo controller + mobile-app)

## Context

The plugin exposes ~111 `wp_ajax` actions (admin-ajax) and no REST routes. The phase PRDs specify a versioned REST surface (`/wp-json/poker-tournament/v1/*`) for the Expo controller and mobile app. We must reconcile the two: build REST per the PRDs now, or ratify admin-ajax as the supported interface and update the PRDs.

Relevant facts:
- The admin-ajax surface is feature-complete and now security-hardened (epic tdwp-2h7: nonce/capability/sanitization/escaping/`$wpdb->prepare` verified across the nopriv surface; pointless nopriv registrations removed; per-IP rate-limiting on public registration).
- There is **no live consumer** of a REST API today. The Expo controller/mobile app (tdwp-2mn) is separate and not yet integrated.
- A second, parallel API surface would double the maintenance and security-audit burden (every endpoint re-audited for auth, validation, escaping).
- WordPress REST is the right fit for an external typed client (token/application-password auth, JSON contracts, discoverability) — admin-ajax (nonce + cookie auth) is awkward for a non-browser client.

## Decision (recommended)

**Ratify admin-ajax as the supported interface for the WordPress plugin's own UI (web dashboard, shortcodes, display screens). Defer the REST API and build it under tdwp-9pi — scoped to exactly what the mobile controller needs — only when the Expo controller integration (tdwp-2mn) is actively worked, not before.**

Update the PRDs to reflect that REST is a controller-integration deliverable (tdwp-9pi), not a prerequisite for the plugin.

## Rationale

- **YAGNI / no consumer:** building REST now creates an unused, unmaintained, separately-attackable surface. Build it with its consumer so the contract is driven by real needs.
- **Security focus:** we just hardened admin-ajax; a parallel REST surface would reset that audit effort. Concentrating on one interface keeps the attack surface auditable.
- **Right tool when it matters:** when the mobile client is built, REST (with application-password / token auth) is clearly correct — and tdwp-9pi already tracks that work.

## Consequences

- Plugin UI continues on admin-ajax; no migration churn.
- Mobile/controller work (tdwp-2mn) is gated on tdwp-9pi delivering a REST v1 scoped to its needs.
- PRDs need a note that REST is delivered via tdwp-9pi, not assumed present.

## Alternatives considered

- **Build full REST now (per PRD):** rejected — significant effort, no consumer, doubles security surface.
- **Expose admin-ajax to the mobile client:** rejected — cookie/nonce auth is unsuitable for a non-browser typed client.

## Ratification

This ADR is **Proposed**. On approval, set Status to Accepted, close tdwp-3mm, and add the PRD note. To reject, record the alternative chosen and reopen scope on tdwp-9pi accordingly.
