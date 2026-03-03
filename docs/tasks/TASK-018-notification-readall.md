# TASK-018 — Implement POST /notifications/read-all (Mark all read) (optional v1) (v1)

**Goal:** Mark all notifications as read (Category C). Returns how many were marked.

---

## 0) Source of truth (must follow)

- `docs/spec/phase-c-api-contracts.md` → **1.3 POST /notifications/read-all**
- `docs/spec/api-schemas-updated.md` → **POST /notifications/read-all — Response**
- `docs/spec/api-errors-updated.md` → **Phase C — Notifications (v1) → POST /notifications/read-all**
- `docs/spec/caching-updated.md` + `docs/spec/endpoint-matrix-updated.md` → Category C; on success revalidate `/notifications` (+ optional `/home`)

No schema/contract changes.

---

## 1) Endpoint

### POST /notifications/read-all
- **Auth:** required (Bearer JWT)
- **Caching:** Category C
  - `Cache-Control: no-store`
  - `meta.etag = null`
- Body: empty

---

## 2) Response shape (must match schema)

Success:
```json
{
  "meta": { "server_time":"...", "league_id":null, "current_gw":null, "last_updated":"...", "etag": null },
  "data": { "ok": true, "read_count": 12 }
}
```

`read_count` = number of notifications that transitioned from unread → read.

---

## 3) Errors (standard error envelope)

Per api-errors:
- `401 AUTH_REQUIRED` / `401 AUTH_INVALID_TOKEN`
- `429 RATE_LIMITED` (optional)
- `500 INTERNAL_ERROR`

---

## 4) Persistence

Preferred:
- update all unread rows for this profile:
  - set `read_at = UTC_TIMESTAMP()`
- legacy fallback:
  - set `mark_read = 1`

Return affected row count as `read_count`.

Idempotent:
- if there are no unread items, return `ok:true, read_count:0`.

---

## 5) Routing / file placement

Add root `.htaccess` rule:
- `^notifications/read-all$ -> notifications/read-all/index.php [QSA,L]`

Create handler:
- `notifications/read-all/index.php`

---

## 6) Smoke tests

Create `scripts/notification-readall-smoke.ps1` (curl-based):

1) Login → token
2) GET `/notifications?filter=unread&limit=50`
   - capture unread_count_before
3) POST `/notifications/read-all`:
   - expect 200 ok:true + Cache-Control no-store
   - `read_count` should be >= 0 and typically equals unread_count_before
4) GET `/notifications?filter=unread&limit=5`:
   - expect unread_count == 0 and items empty (or none unread)
5) No token → 401 AUTH_REQUIRED

If the inbox is already fully read, the test should still pass with `read_count:0`.

---

## 7) Acceptance criteria

- Endpoint reachable: POST /notifications/read-all
- Category C no-store + meta.etag null
- Marks all as read and returns read_count
- GET /notifications reflects unread_count=0 after
- Smoke script passes locally
