# TASK-016 — Implement GET /notifications (Inbox payload) (v1)

**Goal:** Implement the Notifications inbox list endpoint with pagination, filtering, unread counter, and Category A caching (ETag + 304).

---

## 0) Source of truth (must follow)

- `docs/spec/phase-c-api-contracts.md` → **1.1 GET /notifications**
- `docs/spec/api-schemas-updated.md` → **GET /notifications — Response** + `NotificationItem`
- `docs/spec/api-errors-updated.md` → **Phase C — Notifications (v1) → GET /notifications**
- `docs/spec/caching-updated.md` + `docs/spec/endpoint-matrix-updated.md` → Category A, ETag scope **User**
- `docs/spec/core-rules-updated.md` → notification types + target deep link contract

No schema/contract changes in this task.

---

## 1) Endpoint

### GET /notifications
- **Auth:** required (Bearer JWT)
  - missing token → `401 AUTH_REQUIRED`
  - invalid token → `401 AUTH_INVALID_TOKEN`
- **Caching:** Category A
  - `Cache-Control: private, must-revalidate`
  - `ETag: W/"..."`
  - Support `If-None-Match` → **304 Not Modified** (no body) when unchanged
- **ETag scope:** **User**

---

## 2) Query params (per contracts)

- `filter` (optional): `all` | `unread` (default `all`)
- `cursor` (optional): opaque paging cursor
- `limit` (optional): default 20, max 50

Validation:
- unknown filter → `400 BAD_REQUEST`
- limit not int or out of range → `400 BAD_REQUEST`

Cursor:
- treat as opaque string; v1 implementation may interpret it as numeric `notification_id` for “load older”.

---

## 3) Response shape (must match schema exactly)

`meta`:
- `server_time` ISO-8601 UTC
- `league_id = null`, `current_gw = null`
- `last_updated` ISO-8601 UTC
- `etag` (string) equals ETag header value

`data`:
- `unread_count` (global unread count)
- `items[]` newest-first
- `next_cursor` (string or null)

Each item (per schema/contracts):
- `notification_id`
- `type` (string)
- `title` (string)
- `body` (string, optional allowed)
- `created_at` (ISO-8601 UTC)
- `is_read` (bool)
- `target` (optional): `{ kind, league_id, params }`

---

## 4) Errors (standard error envelope)

Per api-errors:
- `401 AUTH_REQUIRED` / `401 AUTH_INVALID_TOKEN`
- `429 RATE_LIMITED` (if you have rate limit middleware)
- `500 INTERNAL_ERROR`

Plus request validation:
- `400 BAD_REQUEST` (bad filter/limit/cursor)

---

## 5) Data mapping (DB)

Assume Phase D migration added:
- `notification.created_at` (UTC)
- `notification.read_at` (nullable)
- `notification.target_kind`, `notification.target_league_id`, `notification.target_params` (JSON text)
Fallbacks allowed for compatibility:
- if `read_at` missing: use `mark_read` (0/1)
- if `created_at` missing: derive ordering by `notification_id` and set created_at to server_time

### 5.1 Resolve user
- `profile_id` from JWT

### 5.2 Unread count
Unread = (`read_at IS NULL`) OR (`mark_read=0` if legacy).
`SELECT COUNT(*) ... WHERE profile_id=? AND unread_condition`

### 5.3 Page query
Order newest-first:
- primary: `created_at DESC, notification_id DESC`
- fallback: `notification_id DESC`

Filter:
- `filter=unread` applies unread_condition
- `filter=all` no unread filter

Cursor handling (v1 recommended):
- interpret cursor as last seen `notification_id`
- fetch older items: `notification_id < cursor`

Limit:
- default 20, cap 50

### 5.4 Title/body
Preferred if columns exist:
- use `notification.title`, `notification.body`
Fallback:
- `title` from `notificationtype.name` join by `notification.notification_type`
- `body` from `notificationtext.text` join by `notification.notification_type` + user `lang_id`

If no match, set:
- title = `notification.type` (or "Notification")
- body omitted (or empty string) as schema allows.

### 5.5 Target
If `target_kind` present, include:
- `target.kind = target_kind`
- `target.league_id = target_league_id` (nullable allowed)
- `target.params = JSON.parse(target_params)` (if invalid JSON, use `{}`)

---

## 6) Category A ETag + last_updated

ETag must be stable for the same user and query params when nothing changed.

Recommended marker inputs:
- `unread_count`
- `max(notification_id)` for the user
- `max(coalesce(updated_at, created_at))` if available
- query params: filter + cursor + limit

Example marker string:
`"f:{filter}|c:{cursor}|l:{limit}|u:{unread_count}|max:{max_id}|ts:{max_ts}"`

ETag:
- `W/"notif-u{profile_id}-{sha1(marker)}"`

`meta.last_updated`:
- use `max_ts` if available
- else `meta.server_time`

Implement 304:
- read `HTTP_IF_NONE_MATCH`
- support comma-separated values
- match weak ETags exactly

---

## 7) Routing / file placement

Add root `.htaccess` rule:
- `^notifications$ -> notifications/index.php [QSA,L]`

Create handler:
- `notifications/index.php`

Reuse existing helpers:
- JWT verification
- envelope builder
- Category A header + 304 handler

---

## 8) Smoke tests

Create `scripts/notifications-smoke.ps1` (curl-based):

1) Login → token
2) GET `/notifications?filter=all&limit=5`
   - expect 200
   - headers include Cache-Control private,must-revalidate and ETag
   - capture ETag
3) Repeat with If-None-Match → expect 304
4) GET `/notifications?filter=unread&limit=5`
   - expect 200 and items are all is_read=false
5) Invalid filter → expect 400 BAD_REQUEST
6) No token → 401 AUTH_REQUIRED

---

## 9) Acceptance criteria

- Endpoint reachable: GET /notifications
- Response matches schema keys/types exactly
- Category A caching works (ETag + 304)
- filter/unread + cursor + limit behave
- Smoke script passes locally
