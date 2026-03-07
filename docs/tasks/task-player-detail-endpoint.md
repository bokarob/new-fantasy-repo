# TASK — Implement missing Player Detail endpoint

## Goal
Implement the backend endpoint:

`GET /leagues/{league_id}/players/{player_id}`

This endpoint is already part of the current specs and mobile architecture, but is not yet implemented in the PHP backend.

## Why this task exists
The endpoint is already treated as available in the current source-of-truth docs:
- `phase-c-api-contracts.md` defines `GET /leagues/{league_id}/players/{player_id}` as the Player Detail modal payload.
- `endpoint-matrix-updated.md` lists the same endpoint for Player Detail and for cross-screen taps from Matches / Stats.
- `phase-c-screens.md` defines Player Detail as a modal entered from Team, Matches / Stats, Market, and notifications deep links.


Because this is intended target-state behavior and is important for M2/M3 mobile work, the preferred fix is to implement the endpoint rather than weakening the architecture/specs.

## Contract summary
Implement:
- **Method:** `GET`
- **Path:** `/leagues/{league_id}/players/{player_id}`

Expected purpose:
- return the Player Detail modal payload
- support use from Team, Matches / Stats, Market, and notification deep links

Expected caching behavior from specs:
- Category A
- ETag scope: **User + League + Current GW + Player**
- conditional requests: `If-None-Match` → `304`

Expected response shape:
- standard success envelope: `meta` + `data`
- standard error envelope on failures

Expected payload fields from current spec:
- `league_id`
- `gw`
- `player`
- `ownership`
- `price`
- `base_stats`
- `actions`

## Required references in repo
Use these files as the source of truth while implementing:
- `docs/spec/phase-c-api-contracts.md` — Player Detail endpoint contract
- `docs/spec/endpoint-matrix-updated.md` — endpoint usage / cache notes
- `docs/spec/phase-c-screens.md` — Player Detail screen purpose and entry points
- `docs/spec/api-overview.md` — envelope conventions
- `docs/spec/caching-updated.md` — Category A / ETag behavior
- `docs/spec/api-errors-updated.md` — error envelope and relevant error codes
- `docs/spec/core-rules-updated.md` — any rule implications for actions / GW state / ownership flags

Also inspect existing implemented league endpoints for routing / auth / envelope patterns so the new endpoint matches the current backend style.

## Implementation expectations
Please:
1. Locate the existing routing/handler pattern under `leagues/` and add support for `GET /leagues/{league_id}/players/{player_id}`.
2. Reuse existing auth / league access validation patterns already used by similar league-scoped GET endpoints.
3. Return the documented envelope format.
4. Implement ETag / conditional request behavior consistently with the Category A pattern already used in the backend.
5. Return appropriate errors for at least:
   - auth required / invalid token
   - league not found / forbidden
   - player not found (or not valid in this league context)
   - internal error
6. Keep the implementation aligned with current backend conventions rather than inventing a parallel style.
7. If any small spec ambiguity is discovered during implementation, keep behavior as close as possible to the existing contract and note the ambiguity in the handoff summary.

## Acceptance criteria
The task is complete when:
- the endpoint exists and is reachable via the backend router
- `GET /leagues/{league_id}/players/{player_id}` returns the expected success envelope
- auth / forbidden / not-found cases use the normal error envelope
- ETag revalidation works according to current backend conventions for Category A endpoints
- the implementation style matches the existing codebase
- any needed smoke-test example or verification steps are included in the handoff

## Requested output
Please provide:
1. A short summary of what was implemented
2. The files changed
3. Any assumptions or ambiguities noticed
4. Create smoke-test and execute them

---


