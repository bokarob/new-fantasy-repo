# Backend Follow-Up Task — Post-M4 Mobile Integration Gaps

## Goal

The mobile app milestones M1–M4 are implemented. This backend task collects the backend-side follow-ups that are still limiting full mobile verification or full end-to-end target behavior.

This task is **separate from the mobile repo stabilization work**.
Use it to address backend contract gaps or environment/test-data gaps surfaced during mobile milestone verification.

---

## Source context

Use the following handoffs/issues as the starting point:

- `mobile-m2-handoff.md` (or the M2 summary/bug note already recorded)
- `mobile-m3-handoff.md`
- `mobile-m4-handoff.md`

Relevant known follow-ups already observed during mobile verification:

1. `GET /leagues/{league_id}/private-leagues/{privateleague_id}` can return `409 RANKING_NOT_AVAILABLE` for valid private leagues before standings are populated
2. `GET /me` returned uppercase language code (`"HU"`) in local smoke verification
3. `DELETE /leagues/{league_id}/team` is not currently implemented in the connected backend
4. `DELETE /me` is not currently implemented in the connected backend
5. Some mobile smoke scenarios were skipped because local seed data did not include:
   - unread notifications for single-item mark-read verification
   - a usable match for match-detail verification

---

## Scope

### 1)  Improve private-league detail behavior before standings are available

#### Problem
The mobile M4 implementation is complete, but private-league detail can still receive:
- `409 RANKING_NOT_AVAILABLE`
for a valid private league before standings are populated.

This is handled safely by the mobile app, but it blocks a fully populated happy-path detail experience in local verification.

#### Desired direction
Prefer returning a usable private-league detail payload whenever the league exists and the user is allowed to view it, even if standings/ranking content is not yet available.

Possible acceptable approaches:
- return `200` with base private-league metadata plus an empty/pending standings block
- return `200` with an explicit status field indicating standings are not ready yet
- only keep `409` if the contract truly intends this as a state conflict and the response semantics are well justified

#### What to do
- review the current contract/behavior of private-league detail
- decide the intended behavior for existing leagues before standings exist
- implement the safer/more mobile-friendly behavior if appropriate
- keep permissions/visibility checks intact

#### Deliverable
A clarified backend behavior and smoke evidence showing either:
- valid `200` detail payload for not-yet-ranked private leagues, or
- a clearly justified contract decision with updated behavior/docs if `409` remains intended

---

### 2) Normalize `lang` output on `GET /me`

#### Problem
During M4 smoke verification, `GET /me` returned uppercase language code (`"HU"`).
The mobile client normalizes outgoing updates to lowercase, but backend output is not fully normalized.

#### Desired behavior
Return language codes in a consistent normalized format, preferably lowercase ISO-style values such as:
- `hu`
- `en`
- `de`

#### What to do
- review persistence/output mapping for `lang`
- normalize response output consistently
- verify that write + read-back behavior stays stable

#### Deliverable
Consistent `GET /me` output for language codes, with smoke evidence.

---

### 3) Implement league-scoped team deletion endpoint

#### Problem
The mobile architecture and M4 planning allow a team deletion flow, but the currently connected backend returns `404` for:
- `DELETE /leagues/{league_id}/team`

Because of this, the mobile delete-team flow remains deferred.

#### Desired behavior
Implement:
- `DELETE /leagues/{league_id}/team`

Expected semantics:
- delete the authenticated user’s competitor/team for that league
- reject when the league/team context is invalid or not deletable
- after success, dependent reads should reflect the removal

Expected downstream effects for verification:
- `/me/teams` updates
- `/home` / `/home?league_id` updates as applicable
- `/leagues/{league_id}/fantasy` updates as applicable
- cached `/leagues/{league_id}/team` should no longer remain valid on the client side after revalidation

#### What to do
- implement the endpoint
- enforce authorization and league-scoped ownership correctly
- enforce rule/state guards as intended
- verify post-delete read behavior

#### Deliverable
Implemented endpoint plus smoke coverage and a short handoff note.

---

### 4) Implement profile deletion endpoint

#### Problem
The mobile architecture and M4 planning also allow profile deletion, but the connected backend returns `404` for:
- `DELETE /me`

Because of this, the mobile profile-deletion flow remains deferred.

#### Desired behavior
Implement:
- `DELETE /me`

Expected semantics:
- irreversible deletion of the authenticated profile and associated data
- remove related user-owned or user-associated data according to the project rules/policy
- mobile app should then be able to clear session/cache and exit authenticated shell

Important from the mobile perspective:
- a successful backend response should allow the client to treat the session as invalid and clear all authenticated local state

#### What to do
- implement the endpoint
- make sure deletion is explicit and safe
- ensure auth/session behavior after deletion is coherent
- verify that subsequent authenticated calls no longer behave like the deleted user

#### Deliverable
Implemented endpoint plus smoke coverage and a short handoff note.

---

### 5) Improve local seed/test data for remaining skipped mobile smokes

#### Problem
Some mobile M3 verification paths were skipped because the current local seeded data did not provide:
- unread notifications for single-item mark-read verification
- a match available for match-detail verification in the selected league/GW

#### What to do
Adjust local seed/reset/test-data support so verification can reliably exercise:
- notification single-item read flow
- match-detail flow

Prefer deterministic seed data over ad hoc manual DB changes.

#### Deliverable
A repeatable local setup where those smoke checks can run as PASS rather than SKIP.

---

## Testing requirement

For each implemented backend fix, add or update the strongest practical verification available, for example:

- endpoint-specific smoke scripts
- regression scripts
- focused manual probes with recorded request/response notes

At minimum, document:
- exact endpoint tested
- auth/user context used
- expected result
- actual result after fix

---

## Required backend handoff

Create a concise backend handoff document, for example:

- `docs/handoffs/backend-post-m4-followups-handoff.md`

Include:
1. summary of fixes implemented
2. endpoints/areas changed
3. test evidence
4. any contract decisions clarified
5. anything still deferred

---

## Definition of done

This task is done when:

- the known backend issues limiting mobile verification are fixed or explicitly clarified
- the missing delete endpoints are either implemented or explicitly deferred with a documented reason
- mobile verification gaps caused only by missing test data are removed where practical
- a backend handoff document records exactly what changed
