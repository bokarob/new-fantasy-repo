-- Phase D migration 005: Notifications storage upgrades (Phase C)
-- Goals:
--  - support string-based notification types (invite_received, deadline_near, etc.)
--  - add created_at for stable ordering
--  - add typed deep-link target fields (kind/league_id/params)
--  - keep existing "mark_read" as the is_read flag for backward compatibility

-- 1) Widen notification_type keys (legacy tables had VARCHAR(2))
ALTER TABLE `notification`
  MODIFY COLUMN `notification_type` VARCHAR(32) NOT NULL;

ALTER TABLE `notificationtype`
  MODIFY COLUMN `notification_type` VARCHAR(32) NOT NULL;

ALTER TABLE `notificationtext`
  MODIFY COLUMN `notification_type` VARCHAR(32) NOT NULL;

-- 2) Add new columns to notification
ALTER TABLE `notification`
  ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `read_at` TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN `target_kind` VARCHAR(32) NULL DEFAULT NULL,
  ADD COLUMN `target_league_id` INT NULL DEFAULT NULL,
  ADD COLUMN `target_params` JSON NULL DEFAULT NULL;

-- 3) Helpful indexes for list/pagination/filter
CREATE INDEX `idx_notification_user_created`
  ON `notification` (`profile_id`, `created_at`, `notification_id`);

CREATE INDEX `idx_notification_user_read_created`
  ON `notification` (`profile_id`, `mark_read`, `created_at`, `notification_id`);
