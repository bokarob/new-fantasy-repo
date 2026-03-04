# TASK-024 — POST /leagues/{league_id}/private-leagues (Create) (v1)

**Goal:** Create a private league and add creator as a confirmed member.

Category: **C** (no-store).  

## Source of truth
- `docs/spec/phase-c-api-contracts.md` → **2.2 Create**
- `docs/spec/api-schemas-updated.md` → **POST /leagues/{league_id}/private-leagues — Request/Response**
- `docs/spec/api-errors-updated.md` → create errors (VALIDATION_ERROR, NAME_ALREADY_USED optional, etc.)
- `docs/spec/core-rules-updated.md` → private league rules
- `docs/spec/caching-updated.md` + matrix → Category C; revalidate list on success

## Endpoint
### POST /leagues/{league_id}/private-leagues
- Auth required
- Headers: `Cache-Control: no-store`
- `meta.etag = null`

Body:
```json
{ "leaguename": "Friends League" }
```

## Success response
Per schema: `data.ok=true`, `data.privateleague_id`.

## Errors
- 400 BAD_REQUEST (missing/invalid payload)
- 401 AUTH_REQUIRED / AUTH_INVALID_TOKEN
- 403 LEAGUE_FORBIDDEN (if enforced)
- 404 LEAGUE_NOT_FOUND
- 409 PRIVATE_LEAGUE_LIMIT_REACHED (optional; can ignore if no limits)
- 409 NAME_ALREADY_USED (optional)
- 409 NO_COMPETITOR (recommended: creator must have competitor in league)
- 422 VALIDATION_ERROR (name invalid)
- 500 INTERNAL_ERROR

## Validation
- leaguename: trim, non-empty, length 3–30 (or per spec), allowed chars similar to alias/teamname rules.

## Persistence (transaction)
1) Validate league exists.
2) Resolve caller competitor in league; if none → 409 NO_COMPETITOR.
3) Insert into `privateleague` (league_id, leaguename, admin_profile_id=caller).
4) Insert into `privateleaguemembers` for caller competitor:
   - status = member_confirmed (or confirmed=1 legacy)
   - timestamps as available.
5) Commit.

## Routing
- `.htaccess`: POST hits same list route `.../private-leagues` with method dispatch OR a dedicated create handler.
Recommended: method dispatch in `leagues/private-leagues/index.php` (GET list + POST create).

## Smoke
`scripts/pl-create-smoke.ps1`:
1) login → token
2) POST create with unique name → 200 ok:true
3) GET list → contains new league
4) POST create same name (optional) → 409 NAME_ALREADY_USED or allow duplicates if spec says optional (document behavior)
5) no token → 401
