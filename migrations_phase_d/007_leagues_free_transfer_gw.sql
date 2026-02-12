-- Phase D migration 007: League config fields used by rules/config payloads
-- Minimal: free transfer GW marker (league-scoped special rule)

ALTER TABLE `leagues`
  ADD COLUMN `free_transfer_gw` SMALLINT NULL DEFAULT NULL;
