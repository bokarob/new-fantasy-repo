# TASK-027 — GET /leagues/{league_id}/private-leagues/{privateleague_id}/invite/search?q= (Invite search) (v1)

**Goal:** Autocomplete search to invite members. Admin-only. Category **B** (short TTL) with optional ETag/304.

## Source of truth
- `docs/spec/phase-c-api-contracts.md` → **2.8 Invite search**
- `docs/spec/api-schemas-updated.md` → invite/search response + `InviteSearchResult`
- `docs/spec/api-errors-updated.md` → NOT_ADMIN, QUERY_TOO_SHORT optional
- `docs/spec/core-rules-updated.md` + screens → exclude already member/invited
- Contracts appendix caching: Category B (short TTL), ETag optional

## Endpoint
### GET /leagues/{league_id}/private-leagues/{privateleague_id}/invite/search?q={q}
- Auth required
- Admin-only: else `403 NOT_ADMIN`
- Cache-Control: `private, max-age=30` (short TTL)
- ETag: optional; recommended to include and support 304 for consistency.

## Query validation
- q required
- if len(q) < 2: `422 QUERY_TOO_SHORT` (if you implement), else return empty list.

## Response
Must match schema:
- `data.q`
- `data.items[]` of `InviteSearchResult`:
  - competitor_id, profile_id, alias, teamname, already_member, already_invited

## Errors
- 401 auth errors
- 403 NOT_ADMIN
- 404 PRIVATE_LEAGUE_NOT_FOUND
- 422 QUERY_TOO_SHORT (optional)
- 500 internal

## Mapping
Search among competitors in the base league by:
- profile.alias LIKE %q% OR competitor.teamname LIKE %q%
Exclude:
- the admin’s own competitor
Compute flags:
- already_member: membership exists confirmed
- already_invited: membership exists pending
(You may still return them with flags true, but UI will disable invite.)

## Routing
- `.htaccess`: `^leagues/([0-9]+)/private-leagues/([0-9]+)/invite/search$ -> leagues/private-leagues/invite/search/index.php?league_id=$1&privateleague_id=$2 [QSA,L]`
- handler: `leagues/private-leagues/invite/search/index.php`

## Smoke
`scripts/pl-invite-search-smoke.ps1`:
1) login token (as admin)
2) create PL, then call search with q from existing users
3) expect 200 and Cache-Control contains max-age=30
4) invalid q -> 422 or items=[]
5) no token -> 401
