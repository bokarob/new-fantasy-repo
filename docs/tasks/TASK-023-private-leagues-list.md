# TASK-023 — GET /leagues/{league_id}/private-leagues (List + invites) (v1)

**Goal:** Implement the Private Leagues list screen payload: user’s private leagues in a base league + pending invites.

Category: **A** (ETag + 304).  
ETag scope: **User + League**.

## Source of truth
- `docs/spec/phase-c-api-contracts.md` → **2.1 GET /leagues/{league_id}/private-leagues**
- `docs/spec/api-schemas-updated.md` → **GET /leagues/{league_id}/private-leagues — Response**
- `docs/spec/api-errors-updated.md` → **Phase C — Private Leagues (Bundle A) → list**
- `docs/spec/caching-updated.md` + `docs/spec/endpoint-matrix-updated.md` → Category A
- `docs/spec/core-rules-updated.md` → Private Leagues rules (R9.3–R9.10)

## Endpoint
### GET /leagues/{league_id}/private-leagues
- Auth required (Bearer JWT)
- Headers: `Cache-Control: private, must-revalidate` + `ETag`
- Conditional: `If-None-Match` → `304` (no body)

## Response (schema)
Must match schema:
- `meta.league_id`, `meta.current_gw` (use GW resolver; if no GW -> 409 GW_NOT_AVAILABLE)
- `data.league_id`
- `data.invites[]` (`PrivateLeagueInviteSummary`)
- `data.leagues[]` (`PrivateLeagueSummary`)

Invite_id format: use deterministic string like `pl{privateleague_id}-c{invitee_competitor_id}` (opaque).

## Errors
- 401 AUTH_REQUIRED / AUTH_INVALID_TOKEN
- 403 LEAGUE_FORBIDDEN (only if enforced)
- 404 LEAGUE_NOT_FOUND
- 409 GW_NOT_AVAILABLE (if no gameweeks)
- 500 INTERNAL_ERROR

## Data mapping (DB)
Tables:
- `privateleague` (privateleague_id, leaguename, league_id, admin_profile_id)
- `privateleaguemembers` (privateleague_id, competitor_id, status/confirmed, timestamps)
- `competitor` (competitor_id, profile_id, league_id, teamname)
- `profile` (profile_id, alias)

Steps:
1) Validate league exists.
2) Resolve current_gw with shared helper.
3) Resolve the caller’s competitor in this league (if none, return leagues=[] invites=[]; do NOT error).
4) Invites: membership rows where competitor_id = caller competitor AND status is pending (or confirmed=0 legacy) and privateleague.league_id = league_id.
   - Map to `PrivateLeagueInviteSummary` (include target.kind="private_league_invite").
5) Leagues: membership rows where competitor_id = caller competitor AND status is member_confirmed (or confirmed=1 legacy).
   - Join privateleague + admin profile alias.
   - member_count = count confirmed members in that private league.

Your role:
- admin if privateleague.admin_profile_id == caller profile_id else member
Your status:
- member_confirmed or pending based on membership row.

## ETag marker
Include:
- max(privateleaguemembers updated/changed) for caller competitor in this league
- count of memberships in list + invites
- max privateleague_id touched
ETag: `W/"pl-list-u{profile_id}-l{league_id}-{sha1(marker)}"`

## Routing
- `.htaccess`: `^leagues/([0-9]+)/private-leagues$ -> leagues/private-leagues/index.php?league_id=$1 [QSA,L]`
- handler: `leagues/private-leagues/index.php`

## Smoke
`scripts/pl-list-smoke.ps1`:
1) login → token
2) GET list → 200 + ETag
3) If-None-Match → 304
4) invalid league → 404
5) no token → 401
