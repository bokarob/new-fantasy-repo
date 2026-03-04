# TASK-033 — POST /leagues/{league_id}/private-leagues/{privateleague_id}/rename (optional v1)

**Goal:** Admin renames private league. Category C.

## Source of truth
- `docs/spec/phase-c-api-contracts.md` → **2.6 Rename**
- `docs/spec/api-schemas-updated.md` → rename request/response
- `docs/spec/api-errors-updated.md` → NOT_ADMIN, VALIDATION_ERROR
- caching/matrix: Category C; revalidate list + detail

Endpoint:
POST `/leagues/{league_id}/private-leagues/{privateleague_id}/rename`
Body: `{ "leaguename": "New Name" }`

Validate name like create.

Routing:
- `.htaccess`: `^leagues/([0-9]+)/private-leagues/([0-9]+)/rename$ -> leagues/private-leagues/rename/index.php?league_id=$1&privateleague_id=$2 [QSA,L]`
