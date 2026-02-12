-- Phase D migration 006: Private league membership lifecycle (invites/applications)
-- Adds status + timestamps to represent pending/declined/left/removed cleanly,
-- while keeping the legacy "confirmed" boolean for compatibility.

ALTER TABLE `privateleaguemembers`
  ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'member_confirmed',
  ADD COLUMN `request_kind` VARCHAR(12) NULL DEFAULT NULL,  -- invite | application
  ADD COLUMN `requested_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `responded_at` TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN `requested_by_profile_id` INT NULL DEFAULT NULL,
  ADD COLUMN `decided_by_profile_id` INT NULL DEFAULT NULL;

-- Backfill status from legacy confirmed flag (safe to rerun)
UPDATE `privateleaguemembers`
SET `status` = CASE
  WHEN `confirmed` = 1 THEN 'member_confirmed'
  ELSE 'pending'
END
WHERE `confirmed` IN (0,1);

-- Helpful indexes
CREATE INDEX `idx_plm_privateleague_status`
  ON `privateleaguemembers` (`privateleague_id`, `status`);

CREATE INDEX `idx_plm_competitor_status`
  ON `privateleaguemembers` (`competitor_id`, `status`);
