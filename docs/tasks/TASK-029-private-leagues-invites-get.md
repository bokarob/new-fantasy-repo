# TASK-029 — GET /leagues/{league_id}/private-leagues/invites (Invites inbox) (optional v1)

**Goal:** List pending private league invites for current user in a base league.
Category A (ETag + 304). Scope: User + League.

## Source of truth
- `docs/spec/phase-c-api-contracts.md` → **2.10.1 GET invites**
- `docs/spec/api-schemas-updated.md` → invites response
- `docs/spec/api-errors-updated.md` → manage invites errors
- caching appendix: Category A

## Endpoint
### GET /leagues/{league_id}/private-leagues/invites
- Auth required
- Category A: private,must-revalidate + ETag + 304

## Mapping
- Resolve caller competitor_id in league.
- Pending invites = membership rows where competitor_id=caller and status pending.
- invite_id deterministic string `pl{privateleague_id}-c{competitor_id}`.

## Errors
- 401 auth errors
- 403 LEAGUE_FORBIDDEN (if enforced)
- 404 LEAGUE_NOT_FOUND
- 500 internal

## Routing
- `.htaccess`: `^leagues/([0-9]+)/private-leagues/invites$ -> leagues/private-leagues/invites/index.php?league_id=$1 [QSA,L]`
- handler: `leagues/private-leagues/invites/index.php`

## Smoke
`scripts/pl-invites-get-smoke.ps1`:
1) login token
2) GET invites → 200 + ETag
3) If-None-Match → 304
