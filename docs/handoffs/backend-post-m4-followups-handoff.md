# Backend Post-M4 Follow-Ups Handoff

## Summary

Implemented the backend follow-ups from `docs/tasks/backend-followups-post-m4.md`:

1. `GET /leagues/{league_id}/private-leagues/{privateleague_id}` now returns `200` with an empty `standings` block when the league exists but rankings are not populated for the current GW.
2. `GET /me` now normalizes `data.me.lang` to lowercase on read.
3. `DELETE /leagues/{league_id}/team` is implemented.
4. `DELETE /me` is implemented.
5. Local reset/seed support now guarantees deterministic follow-up coverage for:
   - unread notification read flow
   - private-league detail with standings unavailable
   - fallback match availability when the base dataset has no match row for league `10` / GW `1`

## Endpoints / Areas Changed

### `GET /me`

File:
- `me/index.php`

Change:
- `data.me.lang` now uses lowercase normalization before JSON serialization.

Verified:
- `GET /me` returned `lang: "hu"` for `seed.user2@example.com`
- PATCH with uppercase input `lang="EN"` read back as `lang: "en"`

### `DELETE /me`

File:
- `me/index.php`

Behavior:
- Category C (`Cache-Control: no-store`)
- idempotent `200 { ok: true }`
- deletes:
  - profile row
  - competitors across leagues
  - competitor-scoped rows in `roster`, `transfers`, `teamresult`, `teamranking`, `votes`
  - private-league memberships for the user competitors
  - private leagues administered by the profile, plus their memberships
  - `notification`, `auth_refresh_tokens`, `extrapictures`, `trollanswers`, `trollpoints`

Post-delete checks:
- same access token gets `401 AUTH_INVALID_TOKEN` from `/me` and `/home`
- refresh token gets `401 AUTH_INVALID_TOKEN`
- repeated `DELETE /me` stays idempotent with `200 { ok: true }`

### `DELETE /leagues/{league_id}/team`

File:
- `leagues/team/index.php`

Behavior:
- Category C (`Cache-Control: no-store`)
- deletes the authenticated user’s competitor for that league
- also removes league-scoped associated data:
  - `roster`, `transfers`, `teamresult`, `teamranking`, `votes`
  - private-league memberships for that competitor
  - private leagues administered by that profile in the same league, plus their memberships, so league-scoped admin state is not orphaned
- guards:
  - `404 LEAGUE_NOT_FOUND`
  - `404 TEAM_NOT_FOUND`
  - `409 GW_NOT_AVAILABLE`
  - `409 STATE_CONFLICT` when the league is not open / deletable

Post-delete checks:
- `/me/teams` no longer lists the team
- `/leagues/{league_id}/team` returns `409 NO_COMPETITOR`
- `/home?league_id={id}` no longer includes `league_context.your_team`
- `/leagues/{league_id}/fantasy` no longer treats the user as a competitor; in the local seed it currently returns `409 RANKING_NOT_AVAILABLE`, which is acceptable for this dataset

### `GET /leagues/{league_id}/private-leagues/{privateleague_id}`

File:
- `leagues/private-leagues/detail/index.php`

Contract decision:
- removed the branch that returned `409 RANKING_NOT_AVAILABLE` when confirmed members existed but no standings rows were available
- current behavior is `200` with the normal detail payload and:
  - `standings.items = []`
  - `standings.you = null`

Rationale:
- better mobile behavior without changing the existing payload shape
- preserves permissions, membership, gameweek, and pending-member data even when ranking computation has not happened yet

### Auth Refresh Coherence

File:
- `auth/index.php`

Change:
- `/auth/token/refresh` now checks that the target profile still exists before rotating tokens

Why:
- prevents refresh token rotation after `DELETE /me`

## Seed / Smoke Support

Files:
- `database/seed/regression-user-reset.sql`
- `scripts/reset-db.ps1`
- `scripts/run-regression.ps1`

Seed additions:
- added `seed.user5@example.com / TestPass123!` with competitor `1005` in league `10`, intentionally without ranking rows
- added private league `2002` administered by profile `2`, with confirmed member `1005`, to verify the private-league detail fallback path
- added a fallback `matches` insert for league `10`, GW `1`, only when the base dataset does not already include one

Smoke / regression updates:
- added `scripts/me-delete-smoke.ps1`
- added `scripts/team-delete-smoke.ps1`
- added `scripts/pl-detail-unranked-smoke.ps1`
- updated:
  - `scripts/me-smoke.ps1`
  - `scripts/me-patch-smoke.ps1`
  - `scripts/match-detail-smoke.ps1`
  - `scripts/pl-detail-smoke.ps1`
  - `scripts/run-regression.ps1`

## Verification

### Focused Commands

Executed:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/reset-db.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/me-smoke.ps1 -Email seed.user2@example.com
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/me-patch-smoke.ps1 -Email seed.user3@example.com
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/team-smoke.ps1 -Email seed.user2@example.com
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/pl-detail-smoke.ps1 -Email seed.user2@example.com
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/pl-detail-unranked-smoke.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/team-delete-smoke.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/me-delete-smoke.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/match-detail-smoke.ps1 -Email seed.user2@example.com
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/notification-read-smoke.ps1 -Email seed.user2@example.com
```

Focused results:
- `/me` smoke: PASS
- `/me` PATCH + uppercase-lang normalization: PASS
- `/leagues/{league_id}/team` GET/304 regression: PASS
- standard private-league detail smoke: PASS
- unranked private-league detail smoke (`league_id=10`, `privateleague_id=2002`, `seed.user2@example.com`): PASS with `200` and empty standings
- team delete smoke: PASS
- profile delete smoke: PASS
- notification single-item read smoke: PASS
- match-detail smoke: PASS

### Full Regression

Executed:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/run-all.ps1 -ContinueOnFail
```

Result:
- full smoke suite PASS
- `40/40` listed smoke scripts passed

## Before / After Notes

### Private-league detail fallback

Before:
- valid detail requests could return `409 RANKING_NOT_AVAILABLE`

After:
- same scenario returns `200`
- verified example:
  - `league_id=10`
  - `privateleague_id=2002`
  - `your_role=admin`
  - `standings.items=[]`
  - `standings.you=null`

### `/me` language normalization

Before:
- local verification observed uppercase language output such as `HU`

After:
- `GET /me` returns lowercase values
- uppercase write input reads back normalized

### Delete endpoints

Before:
- `DELETE /leagues/{league_id}/team` -> `404`
- `DELETE /me` -> `404`

After:
- both endpoints implemented and covered by smoke scripts

## Deferred / Notes

- The local seed’s current GW is `19`, while ranking rows are only present for earlier GW snapshots, so league-wide fantasy payloads may still legitimately return `409 RANKING_NOT_AVAILABLE` for this dataset. That is separate from the private-league detail fallback change made here.
- I did not change the mobile payload schema beyond allowing private-league detail to succeed with empty standings instead of returning `409`.
