# TASK-026 — POST /leagues/{league_id}/private-leagues/{privateleague_id}/leave (v1)

**Goal:** Leave a private league (member confirmed). Category C action.

## Source of truth
- `docs/spec/phase-c-api-contracts.md` → **2.4 Leave**
- `docs/spec/api-schemas-updated.md` → leave response
- `docs/spec/api-errors-updated.md` → leave errors (ADMIN_CANNOT_LEAVE optional)
- caching/matrix → Category C; revalidate list on success

## Endpoint
### POST /leagues/{league_id}/private-leagues/{privateleague_id}/leave
- Auth required
- `Cache-Control: no-store`, `meta.etag=null`

## Errors
- 401 auth errors
- 403 PRIVATE_LEAGUE_FORBIDDEN (not a member)
- 404 PRIVATE_LEAGUE_NOT_FOUND
- 409 ADMIN_CANNOT_LEAVE (optional; enforce if admin and league would become ownerless)
- 500 internal

## Behavior
- If caller is admin and there are other confirmed members, either:
  - return 409 ADMIN_CANNOT_LEAVE (recommended), OR
  - transfer admin automatically (NOT recommended for v1).
- If caller is the only member, allow leave by deleting the league or leaving membership; prefer delete league (optional).
Keep behavior consistent and document in code comments.

## Persistence
- delete membership row for caller competitor OR set status=left (if you keep history).
- If you enforce ADMIN_CANNOT_LEAVE, do check before delete.

## Routing
- `.htaccess`: `^leagues/([0-9]+)/private-leagues/([0-9]+)/leave$ -> leagues/private-leagues/leave/index.php?league_id=$1&privateleague_id=$2 [QSA,L]`
- handler: `leagues/private-leagues/leave/index.php`

## Smoke
`scripts/pl-leave-smoke.ps1`:
1) login token
2) create a private league (TASK-024) with a unique name
3) leave it → 200 ok:true
4) list → does not include league
5) no token → 401
