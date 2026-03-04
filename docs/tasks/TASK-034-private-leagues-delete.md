# TASK-034 — POST /leagues/{league_id}/private-leagues/{privateleague_id}/delete (optional v1)

**Goal:** Admin deletes private league. Category C.

## Source of truth
- `docs/spec/phase-c-api-contracts.md` → **2.7 Delete**
- `docs/spec/api-schemas-updated.md` → delete response
- `docs/spec/api-errors-updated.md` → NOT_ADMIN
- caching/matrix: Category C; revalidate list

Endpoint:
POST `/leagues/{league_id}/private-leagues/{privateleague_id}/delete`

Behavior:
- Admin-only
- Delete privateleague row and all membership rows (transaction).

Routing:
- `.htaccess`: `^leagues/([0-9]+)/private-leagues/([0-9]+)/delete$ -> leagues/private-leagues/delete/index.php?league_id=$1&privateleague_id=$2 [QSA,L]`
