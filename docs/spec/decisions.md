# Decisions

## Password hashing (Phase D)
For backward compatibility, Phase D keeps the legacy password hashing scheme:
`profile.password = md5(plaintext_password + stored_email_from_db)`

No migration to widen `profile.password` and no bcrypt/argon2 in Phase D.
Future upgrade (optional) can be done via dual-hash columns and gradual migration on login.