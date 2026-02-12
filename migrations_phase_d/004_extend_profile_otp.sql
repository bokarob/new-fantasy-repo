-- Phase D migration 004: extend profile for email verification + OTP
-- If any column already exists, remove it from the statement and re-run.

ALTER TABLE `profile`
  ADD COLUMN `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN `otp_hash` VARBINARY(32) NULL DEFAULT NULL,
  ADD COLUMN `otp_expires_at` TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN `otp_attempts` SMALLINT NOT NULL DEFAULT 0,
  ADD COLUMN `otp_last_sent_at` TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN `otp_resend_count` SMALLINT NOT NULL DEFAULT 0,
  ADD COLUMN `otp_purpose` VARCHAR(20) NULL DEFAULT NULL;
