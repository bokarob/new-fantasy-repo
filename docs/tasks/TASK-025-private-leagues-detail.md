# TASK-025 — GET /leagues/{league_id}/private-leagues/{privateleague_id} (Detail + standings) (v1)

**Goal:** Implement private league detail payload:
- header, membership, permissions
- current GW info
- standings (subset rankings among confirmed members)
- pending members (invited/pending)

Category: **A** (ETag + 304).  
ETag scope: **User + League + Current GW**.

## Source of truth
- `docs/spec/phase-c-api-contracts.md` → **2.3 Detail**
- `docs/spec/api-schemas-updated.md` → **GET .../{privateleague_id} — Response**
- `docs/spec/api-errors-updated.md` → detail errors (PRIVATE_LEAGUE_FORBIDDEN/NOT_FOUND, RANKING_NOT_AVAILABLE optional)
- `docs/spec/core-rules-updated.md` → R9.5 rankings rules + membership rules
- `docs/spec/caching-updated.md` + matrix → Category A

## Endpoint
### GET /leagues/{league_id}/private-leagues/{privateleague_id}
- Auth required
- Category A headers + ETag + 304

## Access rule
Allow access if the caller’s competitor has a membership row in this privateleague (confirmed OR pending) OR caller is admin.
Else `403 PRIVATE_LEAGUE_FORBIDDEN`.

## Response (schema)
Must include:
- `data.privateleague` (`PrivateLeagueHeader`)
- `data.membership` (`PrivateLeagueMembership`)
- `data.gameweek` (gw, is_open, deadline)
- `data.standings` (`PrivateLeagueStandings`)
- `data.pending_members[]` (`PrivateLeaguePendingMember`)
- `data.permissions` (`PrivateLeaguePermissions`)

## Errors
- 401 AUTH_REQUIRED / AUTH_INVALID_TOKEN
- 403 PRIVATE_LEAGUE_FORBIDDEN
- 404 PRIVATE_LEAGUE_NOT_FOUND
- 409 RANKING_NOT_AVAILABLE (optional; prefer when no ranking rows)
- 409 GW_NOT_AVAILABLE (if league has no gameweeks)
- 500 INTERNAL_ERROR

## Data mapping (DB)
1) Validate base league exists (optional; or derive from privateleague row).
2) Resolve current_gw via shared helper.
3) Load privateleague row by id + league_id; if none → 404.
4) Resolve caller competitor in league; if none:
   - if caller is admin_profile_id, allow; else 403.
5) Load membership row for caller competitor (if exists) and compute:
   - your_role: admin/member
   - your_status: member_confirmed/pending/declined (as stored)
6) Permissions:
   - admin: can_invite/remove/rename/delete true, can_leave per your policy (see leave task)
   - member confirmed: can_leave true, others false
   - pending: can_leave false (invites handled by accept/decline), others false
7) Pending members:
   - membership rows in privateleaguemembers with status pending (exclude caller if pending unless schema wants it)
   - join competitor + profile for alias + teamname
8) Standings:
   - confirmed member competitor_ids set
   - for current_gw:
     - if no teamranking rows for those competitors → 409 RANKING_NOT_AVAILABLE (preferred)
     - else compute rank/previous_rank/rank_change, weekly_points, total_points like TASK-010 but filtered to member set.
   - standings.you: include caller competitor_id + rank when caller is confirmed member; else null.

## ETag marker
Include:
- membership list changes (count + max id/ts)
- standings changes signal (sum weekly_points, sum total_points, max rank)
- current_gw
ETag: `W/"pl-{privateleague_id}-l{league_id}-gw{current_gw}-{sha1(marker)}"`

## Routing
- `.htaccess`: `^leagues/([0-9]+)/private-leagues/([0-9]+)$ -> leagues/private-leagues/detail/index.php?league_id=$1&privateleague_id=$2 [QSA,L]`
- handler: `leagues/private-leagues/detail/index.php`

## Smoke
`scripts/pl-detail-smoke.ps1`:
1) login → token
2) ensure at least one PL exists (create via TASK-024 if needed)
3) GET detail → 200 + ETag
4) If-None-Match → 304
5) invalid id → 404
6) no token → 401
