# Decisions

## Password hashing (Phase D)
For backward compatibility, Phase D keeps the legacy password hashing scheme:
`profile.password = md5(plaintext_password + stored_email_from_db)`

No migration to widen `profile.password` and no bcrypt/argon2 in Phase D.
Future upgrade (optional) can be done via dual-hash columns and gradual migration on login.

OTP timestamps are stored/compared in UTC (writes use UTC_TIMESTAMP() + DATE_ADD, comparisons are UTC-consistent)

JWT secret resolution order: env JWT_SECRET first, then local config fallback; prod should use env.

Team creation uses POST /leagues/{league_id}/team (method-dispatch with GET). Builder is separate GET /team/builder.