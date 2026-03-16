# Codex Task — Backend Bug Fix: `GET /leagues/{league_id}/team` ETag / 304 Revalidation

## Context

M2 mobile implementation is complete and tested.
The remaining issue is a **backend-side cache-contract inconsistency** discovered during M2 smoke testing.

Observed result from testing:
- `flutter analyze` passed
- `flutter test` passed
- live backend smoke checks against `http://localhost/new-fantasy-repo` passed for rules, market players, player detail, rankings handling, transfer quote, transfer confirm, and transfers list refresh
- happy path with `seed.user3@example.com` passed: transfer confirm returned `200`, `/team` changed after transfer, and `/transfers` refreshed with a new ETag and updated total
- guardrail path with `seed.user2@example.com` passed: quote stayed in-flow with `is_valid=false`, and confirm returned `409 TRANSFER_LIMIT_REACHED`
- **remaining issue:** `GET /leagues/{league_id}/team` returned `200` instead of `304` for unchanged `If-None-Match` revalidation in both seeded-user runs

This task is **not** a mobile integration task.
It is a **narrow backend bug-fix task** to bring the API back in line with the documented caching contract.

---

## Goal

Fix backend conditional GET handling so that:
- `GET /leagues/{league_id}/team` returns `304 Not Modified` when the request contains a matching `If-None-Match` for an unchanged resource
- the endpoint still returns `200 OK` with a new payload and a changed ETag when the underlying team resource has actually changed
- behavior remains consistent across seeded users and across post-transfer revalidation cases

---

## Primary source-of-truth files

Use these files as the authoritative reference while fixing the bug:

- `docs/spec/caching-updated.md`
- `docs/spec/endpoint-matrix-updated.md`
- `docs/spec/api-overview.md`
- `docs/spec/api-schemas-updated.md`
- `docs/spec/api-errors-updated.md`
- `docs/mobile-technical-architecture.md`
- `docs/mobile-integration-index.md`

Conflict rule:
- backend/spec files win over companion/planning docs
- do not change the mobile client to work around this bug unless a tiny compatibility improvement is absolutely necessary

---

## Bug statement

### Expected behavior
`GET /leagues/{league_id}/team` is a **Category A** payload endpoint.
It must support:
- ETag responses
- `If-None-Match` conditional requests
- `304 Not Modified` when the resource has not changed

### Actual behavior
For unchanged team revalidation, the endpoint currently returns:
- `200 OK`
- instead of `304 Not Modified`

This breaks the documented cache contract and causes unnecessary payload delivery on revalidation.

---

## Endpoint scope

### Endpoint to fix
- `GET /leagues/{league_id}/team`

### Do not broaden scope unnecessarily
Do **not** turn this into a general caching refactor unless clearly required.
Focus first on the team endpoint and its ETag / conditional-request path.

If you discover the root cause is in a shared cache helper used by other endpoints, keep the fix as small and safe as possible and document exactly what was changed.

---

## Contract requirements you must respect

### 1) Category A read behavior
`GET /leagues/{league_id}/team` is a Category A endpoint and must behave like other ETag-enabled reads:
- return an ETag header / meta etag as already defined by the project conventions
- compare request `If-None-Match` against the current server-side ETag for the resolved resource
- return `304` with no body when unchanged
- return `200` with body when changed

### 2) Team ETag scope
The endpoint matrix defines the team endpoint as user + league + GW scoped.
Its ETag basis includes team-related state such as:
- roster for the relevant gameweek
- competitor credits/teamname
- transfer usage for the relevant gameweek

Do not simplify the ETag in a way that would hide real state changes.

### 3) Post-write refresh behavior must still work
After successful writes such as:
- team creation
- captain change
- substitution
- transfer confirm

the team endpoint must produce a changed ETag and a fresh `200` response when state changed.

The bug fix must not cause false `304` responses after real mutations.

### 4) HTTP behavior
Do not return a fake `304` while also sending a normal success body.
Keep response semantics clean and standards-aligned.

---

## Suspected areas to inspect

Inspect the existing implementation path for:
- how the endpoint computes or retrieves the ETag
- whether `If-None-Match` is read correctly from request headers in the local Apache/PHP setup
- whether weak ETag formatting / quoting is compared correctly
- whether the endpoint checks equality before building/sending the response body
- whether the response helper always emits `200` and bypasses conditional handling
- whether any middleware/shared responder strips or ignores the 304 branch

Also verify whether the route uses the same cache helper pattern as other endpoints that already behave correctly.

---

## Reproduction baseline

Use the seeded-user cases already verified during M2:

### Happy-path seed
- `seed.user3@example.com`
- obtain `/leagues/{league_id}/team`
- capture returned ETag
- immediately re-request the same endpoint with `If-None-Match: <captured-etag>`
- expected: `304`
- actual today: `200`

Then verify after a real transfer confirm:
- transfer confirm succeeds
- next `/team` request returns `200` with changed ETag
- subsequent unchanged revalidation returns `304`

### Guardrail seed
- `seed.user2@example.com`
- obtain `/team`
- repeat unchanged `If-None-Match` request
- expected: `304`
- actual today: `200`

This second path is important to confirm the issue is not specific to the happy-path mutation flow.

---

## Implementation expectations

- keep the fix small and targeted
- preserve existing response envelope / header conventions
- do not change unrelated endpoint payload shapes
- do not weaken the ETag scope just to make `304` easier
- do not special-case the mobile client
- do not remove useful caching metadata already emitted by the endpoint

If a shared helper is fixed, make sure the change is backward-compatible for other cacheable endpoints.

---

## Testing expectations

You must test the implemented fix.
Do not stop at code changes only.

### Automated / scripted checks
Add or update tests where practical for:
- unchanged `If-None-Match` on `/leagues/{league_id}/team` -> `304`
- changed team state after transfer confirm -> fresh `200` + new ETag
- subsequent unchanged revalidation after the changed fetch -> `304`

If the repo already has smoke/regression scripts for API caching, extend them instead of inventing a parallel mechanism.

### Manual / curl verification
At minimum verify:
1. first `GET /leagues/{league_id}/team` returns `200` + ETag
2. second unchanged request with `If-None-Match` returns `304`
3. after real team mutation, next request returns `200` + changed ETag
4. another unchanged revalidation after that returns `304`
5. behavior works in both seeded-user scenarios above

Please include the exact commands or script path used for verification in the handoff.

---

## Deliverables

### 1) Working backend fix
Fix the endpoint so `/leagues/{league_id}/team` respects the documented conditional GET contract.

### 2) Tested result
Run the relevant tests / smoke checks and note what was executed.
If something could not be run, state that explicitly.

### 3) Handoff document
Create a handoff markdown document in the repo, for example:
- `docs/handoffs/backend-team-etag-304-fix.md`

The handoff must clearly describe:
- the root cause found
- what code path was changed
- whether the fix was endpoint-local or in a shared cache helper
- how ETag comparison now works
- what tests / smoke checks were run
- exact reproduction before vs after the fix
- any related follow-up items, if discovered

Be concrete and technical.
The handoff should let the next person understand the bug and the fix without re-investigating it.

---

## Done criteria

Treat this task as done only when all of the following are true:
- `/leagues/{league_id}/team` returns `304` for unchanged `If-None-Match` revalidation
- real team changes still produce fresh `200` responses with changed ETags
- behavior is verified in both seeded-user scenarios
- tests/smoke checks were executed
- handoff document is written

