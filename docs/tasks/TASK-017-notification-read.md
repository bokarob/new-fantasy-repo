# TASK-017 — Implement POST /notifications/{notification_id}/read (Mark read) (v1)

**Goal:** Mark a single notification as read (Category C). This must update unread_count and cause `/notifications` ETag to change.

---

## 0) Source of truth (must follow)

- `docs/spec/phase-c-api-contracts.md` → **1.2 POST /notifications/{notification_id}/read**
- `docs/spec/api-schemas-updated.md` → **POST /notifications/{notification_id}/read — Response**
- `docs/spec/api-errors-updated.md` → **Phase C — Notifications (v1) → POST /notifications/{notification_id}/read**
- `docs/spec/caching-updated.md` + `docs/spec/endpoint-matrix-updated.md` → Category C; on success revalidate `/notifications` (+ optional `/home`)

No schema/contract changes.

---

## 1) Endpoint

### POST /notifications/{notification_id}/read
- **Auth:** required (Bearer JWT)
- **Caching:** Category C
  - `Cache-Control: no-store`
  - `meta.etag = null`
- Body: optional; v1 recommended empty body.

---

## 2) Response shape (must match schema)

Success:
```json
{
  "meta": { "server_time":"...", "league_id":null, "current_gw":null, "last_updated":"...", "etag": null },
  "data": { "ok": true }
}
```

---

## 3) Errors (standard error envelope)

Per api-errors:
- `400 BAD_REQUEST` — invalid id format
- `401 AUTH_REQUIRED` / `401 AUTH_INVALID_TOKEN`
- `404 NOTIFICATION_NOT_FOUND`
- `403 NOTIFICATION_FORBIDDEN` — not owned by user
- `429 RATE_LIMITED` (optional)
- `500 INTERNAL_ERROR`

**Idempotency (recommended):**
- If already read, return `200 ok:true` (do not 409). This is allowed because 409 is marked optional.

---

## 4) Persistence

Preferred schema:
- set `read_at = UTC_TIMESTAMP()` for that row (only if currently unread)
- also update `updated_at` if present

Legacy fallback:
- set `mark_read = 1`

SQL pattern:
- select notification by id
- verify profile_id matches current user (else forbidden)
- update if unread

---

## 5) Routing / file placement

Add root `.htaccess` rule:
- `^notifications/([0-9]+)/read$ -> notifications/read/index.php?notification_id=$1 [QSA,L]`

Create handler:
- `notifications/read/index.php`

Reuse existing helpers:
- JWT verification
- envelope builder
- Category C headers

---

## 6) Smoke tests

Create `scripts/notification-read-smoke.ps1` (curl-based):

1) Login → token
2) GET `/notifications?filter=unread&limit=1`
   - if no items: print SKIP and exit 0
   - else capture notification_id and ETag from GET /notifications?filter=all (or use unread list’s ETag if returned)
3) POST `/notifications/{id}/read`:
   - expect 200 ok:true and Cache-Control no-store
4) GET `/notifications?filter=unread&limit=5`:
   - verify the id is not present (or items all is_read=false)
5) GET `/notifications?filter=all&limit=5` with previous ETag:
   - expect 200 with a NEW ETag (or 304 should not happen if list changed)
6) Invalid id (999999999) → 404 NOTIFICATION_NOT_FOUND
7) No token → 401 AUTH_REQUIRED

---

## 7) Acceptance criteria

- Endpoint reachable: POST /notifications/{id}/read
- Category C no-store + meta.etag null
- Updates read state and affects unread_count
- GET /notifications ETag changes after marking read
- Smoke script passes (or SKIP if no notifications exist)
