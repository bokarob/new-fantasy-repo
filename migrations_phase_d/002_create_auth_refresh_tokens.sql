-- Phase D migration 002: create auth_refresh_tokens (server-side refresh token storage)

CREATE TABLE IF NOT EXISTS `auth_refresh_tokens` (
  `refresh_token_id` BIGINT NOT NULL AUTO_INCREMENT,
  `profile_id`       INT NOT NULL,

  -- SHA-256 hash of the refresh token (32 bytes). Store hash, never raw token.
  `token_hash`       VARBINARY(32) NOT NULL,

  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at`       TIMESTAMP NOT NULL,

  `revoked_at`       TIMESTAMP NULL DEFAULT NULL,
  `last_used_at`     TIMESTAMP NULL DEFAULT NULL,

  -- Optional but useful for session management UX / debugging
  `device_name`      VARCHAR(80) NULL DEFAULT NULL,
  `ip_hash`          VARBINARY(32) NULL DEFAULT NULL,

  PRIMARY KEY (`refresh_token_id`),
  UNIQUE KEY `uq_auth_refresh_token_hash` (`token_hash`),
  INDEX `idx_auth_refresh_profile` (`profile_id`, `revoked_at`, `expires_at`),
  INDEX `idx_auth_refresh_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
