-- Phase D migration 001: create leaguetable (derived standings snapshot per GW)
-- MariaDB / MySQL

CREATE TABLE IF NOT EXISTS `leaguetable` (
  `league_id`     INT NOT NULL,
  `gameweek`      SMALLINT NOT NULL,
  `team_id`       INT NOT NULL,

  `win`           INT NOT NULL DEFAULT 0,
  `draw`          INT NOT NULL DEFAULT 0,
  `loss`          INT NOT NULL DEFAULT 0,

  `team_points`   INT NOT NULL DEFAULT 0,
  `match_points`  INT NOT NULL DEFAULT 0,
  `set_points`    INT NOT NULL DEFAULT 0,

  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                 ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`league_id`, `gameweek`, `team_id`),
  INDEX `idx_leaguetable_league_gw` (`league_id`, `gameweek`),
  INDEX `idx_leaguetable_sort` (`league_id`, `gameweek`, `team_points`, `match_points`, `set_points`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
