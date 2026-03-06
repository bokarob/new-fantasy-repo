-- regression-user-reset.sql
-- Resets ONLY user/competitor scoped data for repeatable smoke/regression runs.
-- Keeps reference data intact (leagues, gameweeks, teams, players, prices, results, etc.).
-- Assumptions:
--   - Base league_id = 10 exists with >= 1 team and >= 8 players.
--   - Admin profile_id=1 exists and is preserved.
--   - Legacy password hashing is md5(password + email).

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
START TRANSACTION;
SET FOREIGN_KEY_CHECKS=0;

-- --- Helper vars ---
SET @LEAGUE_ID := 10;
SET @GW := 1;

-- Favorite team (optional)
SET @FAV_TEAM := (SELECT team_id FROM team WHERE league_id=@LEAGUE_ID ORDER BY team_id LIMIT 1);

-- --- Clean user-scoped tables ---
-- (These are safe to wipe for dev/regression; reference tables are NOT touched.)
TRUNCATE TABLE transfers;
TRUNCATE TABLE roster;
TRUNCATE TABLE teamresult;
TRUNCATE TABLE teamranking;
TRUNCATE TABLE competitor;

TRUNCATE TABLE privateleaguemembers;
TRUNCATE TABLE privateleague;

TRUNCATE TABLE notification;

-- Optional tables (exist only after Phase D migrations); truncate if present.
SET @has_auth_rt := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='auth_refresh_tokens');
SET @sql_auth_rt := IF(@has_auth_rt > 0, 'TRUNCATE TABLE auth_refresh_tokens', 'SELECT 1');
PREPARE s1 FROM @sql_auth_rt; EXECUTE s1; DEALLOCATE PREPARE s1;

-- Keep profile_id=1, wipe all other profiles for deterministic tests
DELETE FROM profile WHERE profile_id <> 1;

-- Clean auth fields for admin
UPDATE profile
SET reg_token_hash=NULL, reset_token_hash=NULL, reset_token_expire=NULL
WHERE profile_id=1;

-- Ensure admin is "verified" if email_verified_at exists
SET @has_email_verified := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'profile'
    AND column_name = 'email_verified_at'
);
SET @sql_verify_admin := IF(
  @has_email_verified > 0,
  'UPDATE profile SET email_verified_at=UTC_TIMESTAMP() WHERE profile_id=1',
  'SELECT 1'
);
PREPARE s2 FROM @sql_verify_admin; EXECUTE s2; DEALLOCATE PREPARE s2;

-- Clear OTP state for admin if those columns exist
SET @has_otp_hash := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'profile'
    AND column_name = 'otp_hash'
);
SET @has_otp_expires := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'profile'
    AND column_name = 'otp_expires_at'
);
SET @has_otp_attempts := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'profile'
    AND column_name = 'otp_attempts'
);
SET @has_otp_last_sent := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'profile'
    AND column_name = 'otp_last_sent_at'
);
SET @has_otp_resend := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'profile'
    AND column_name = 'otp_resend_count'
);
SET @has_otp_purpose := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'profile'
    AND column_name = 'otp_purpose'
);
SET @sql_clear_admin_otp := CONCAT(
  'UPDATE profile SET ',
  IF(@has_otp_hash > 0, 'otp_hash=NULL, ', ''),
  IF(@has_otp_expires > 0, 'otp_expires_at=NULL, ', ''),
  IF(@has_otp_attempts > 0, 'otp_attempts=0, ', ''),
  IF(@has_otp_last_sent > 0, 'otp_last_sent_at=NULL, ', ''),
  IF(@has_otp_resend > 0, 'otp_resend_count=0, ', ''),
  IF(@has_otp_purpose > 0, 'otp_purpose=NULL, ', ''),
  'profile_id=profile_id WHERE profile_id=1'
);
PREPARE s2b FROM @sql_clear_admin_otp; EXECUTE s2b; DEALLOCATE PREPARE s2b;

-- --- Seed 4 profiles (id 1 preserved, add 2..4) ---
-- NOTE: password uses legacy md5(password+email)
INSERT INTO profile (profile_id, email, password, profilename, alias, picture_id, authorization, lang_id)
VALUES
(2, 'seed.user2@example.com', MD5(CONCAT('TestPass123!', 'seed.user2@example.com')), 'Seed User 2', 'seed2', 1, 1, 1),
(3, 'seed.user3@example.com', MD5(CONCAT('TestPass123!', 'seed.user3@example.com')), 'Seed User 3', 'seed3', 1, 1, 1),
(4, 'seed.user4@example.com', MD5(CONCAT('TestPass123!', 'seed.user4@example.com')), 'Seed User 4', 'seed4', 1, 1, 1);

-- Mark seeded users verified if email_verified_at exists
SET @sql_verify_seeds := IF(
  @has_email_verified > 0,
  'UPDATE profile SET email_verified_at=UTC_TIMESTAMP() WHERE profile_id IN (2,3,4)',
  'SELECT 1'
);
PREPARE s3 FROM @sql_verify_seeds; EXECUTE s3; DEALLOCATE PREPARE s3;

-- --- Choose 8 roster players from the league ---
-- Fallback: first 8 by player_id.
SET @P1 := (SELECT player_id FROM player WHERE league_id=@LEAGUE_ID ORDER BY player_id LIMIT 0,1);
SET @P2 := (SELECT player_id FROM player WHERE league_id=@LEAGUE_ID ORDER BY player_id LIMIT 1,1);
SET @P3 := (SELECT player_id FROM player WHERE league_id=@LEAGUE_ID ORDER BY player_id LIMIT 2,1);
SET @P4 := (SELECT player_id FROM player WHERE league_id=@LEAGUE_ID ORDER BY player_id LIMIT 3,1);
SET @P5 := (SELECT player_id FROM player WHERE league_id=@LEAGUE_ID ORDER BY player_id LIMIT 4,1);
SET @P6 := (SELECT player_id FROM player WHERE league_id=@LEAGUE_ID ORDER BY player_id LIMIT 5,1);
SET @P7 := (SELECT player_id FROM player WHERE league_id=@LEAGUE_ID ORDER BY player_id LIMIT 6,1);
SET @P8 := (SELECT player_id FROM player WHERE league_id=@LEAGUE_ID ORDER BY player_id LIMIT 7,1);

-- --- Seed 3 competitors (profiles 1..3) in league 10 ---
-- Use explicit competitor_ids to keep smoke scripts deterministic.
INSERT INTO competitor (competitor_id, profile_id, league_id, teamname, credits, favorite_team_id, favorite_team_changed)
VALUES
(1001, 1, @LEAGUE_ID, 'Admin Seed Team', 20.0, @FAV_TEAM, 0),
(1002, 2, @LEAGUE_ID, 'Seed Team 2',     20.0, @FAV_TEAM, 0),
(1003, 3, @LEAGUE_ID, 'Seed Team 3',     20.0, @FAV_TEAM, 0);

-- --- Seed rosters for current gw ---
INSERT INTO roster (competitor_id, gameweek, player1, player2, player3, player4, player5, player6, player7, player8, captain)
VALUES
(1001, @GW, @P1,@P2,@P3,@P4,@P5,@P6,@P7,@P8, @P1),
(1002, @GW, @P1,@P2,@P3,@P4,@P5,@P6,@P7,@P8, @P1),
(1003, @GW, @P1,@P2,@P3,@P4,@P5,@P6,@P7,@P8, @P1);

-- --- Seed fantasy results/rankings for gw 1 (or @GW) ---
INSERT INTO teamresult (competitor_id, gameweek, weeklypoints)
VALUES
(1001, @GW, 105.5),
(1002, @GW,  98.0),
(1003, @GW,  87.5);

INSERT INTO teamranking (competitor_id, gameweek, rank)
VALUES
(1001, @GW, 1),
(1002, @GW, 2),
(1003, @GW, 3);

-- --- Seed leaguetable (real-team standings snapshot) for league 10 gw @GW ---
-- Replace snapshot for that GW.
DELETE FROM leaguetable WHERE league_id=@LEAGUE_ID AND gameweek=@GW;

SET @i := 0;
INSERT INTO leaguetable (league_id, gameweek, team_id, win, draw, loss, team_points, match_points, set_points)
SELECT
  @LEAGUE_ID, @GW, t.team_id,
  0, 0, 0,
  (20 - (@i := @i + 1)) AS team_points,
  (60 - (@i * 2))       AS match_points,
  (120 - (@i * 3))      AS set_points
FROM (SELECT team_id FROM team WHERE league_id=@LEAGUE_ID ORDER BY team_id LIMIT 12) t;

-- --- Seed private league: admin=profile 1, member=profile2, pending invite=profile3 ---
INSERT INTO privateleague (privateleague_id, leaguename, league_id, admin)
VALUES (2001, 'Regression Private League', @LEAGUE_ID, 1);

SET @has_plm_status := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'privateleaguemembers'
    AND column_name = 'status'
);
SET @has_plm_request_kind := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'privateleaguemembers'
    AND column_name = 'request_kind'
);
SET @has_plm_requested_by := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'privateleaguemembers'
    AND column_name = 'requested_by_profile_id'
);
SET @has_plm_decided_by := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'privateleaguemembers'
    AND column_name = 'decided_by_profile_id'
);
SET @has_plm_responded_at := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'privateleaguemembers'
    AND column_name = 'responded_at'
);

SET @plm_cols := 'privateleague_id, competitor_id, confirmed';
SET @plm_admin_vals := '2001, 1001, 1';
SET @plm_member_vals := '2001, 1002, 1';
SET @plm_pending_vals := '2001, 1003, 0';

SET @plm_cols := CONCAT(@plm_cols, IF(@has_plm_status > 0, ', status', ''));
SET @plm_admin_vals := CONCAT(@plm_admin_vals, IF(@has_plm_status > 0, ", 'member_confirmed'", ''));
SET @plm_member_vals := CONCAT(@plm_member_vals, IF(@has_plm_status > 0, ", 'member_confirmed'", ''));
SET @plm_pending_vals := CONCAT(@plm_pending_vals, IF(@has_plm_status > 0, ", 'pending'", ''));

SET @plm_cols := CONCAT(@plm_cols, IF(@has_plm_request_kind > 0, ', request_kind', ''));
SET @plm_admin_vals := CONCAT(@plm_admin_vals, IF(@has_plm_request_kind > 0, ', NULL', ''));
SET @plm_member_vals := CONCAT(@plm_member_vals, IF(@has_plm_request_kind > 0, ', NULL', ''));
SET @plm_pending_vals := CONCAT(@plm_pending_vals, IF(@has_plm_request_kind > 0, ", 'invite'", ''));

SET @plm_cols := CONCAT(@plm_cols, IF(@has_plm_requested_by > 0, ', requested_by_profile_id', ''));
SET @plm_admin_vals := CONCAT(@plm_admin_vals, IF(@has_plm_requested_by > 0, ', 1', ''));
SET @plm_member_vals := CONCAT(@plm_member_vals, IF(@has_plm_requested_by > 0, ', 1', ''));
SET @plm_pending_vals := CONCAT(@plm_pending_vals, IF(@has_plm_requested_by > 0, ', 1', ''));

SET @plm_cols := CONCAT(@plm_cols, IF(@has_plm_decided_by > 0, ', decided_by_profile_id', ''));
SET @plm_admin_vals := CONCAT(@plm_admin_vals, IF(@has_plm_decided_by > 0, ', 1', ''));
SET @plm_member_vals := CONCAT(@plm_member_vals, IF(@has_plm_decided_by > 0, ', 1', ''));
SET @plm_pending_vals := CONCAT(@plm_pending_vals, IF(@has_plm_decided_by > 0, ', NULL', ''));

SET @plm_cols := CONCAT(@plm_cols, IF(@has_plm_responded_at > 0, ', responded_at', ''));
SET @plm_admin_vals := CONCAT(@plm_admin_vals, IF(@has_plm_responded_at > 0, ', UTC_TIMESTAMP()', ''));
SET @plm_member_vals := CONCAT(@plm_member_vals, IF(@has_plm_responded_at > 0, ', UTC_TIMESTAMP()', ''));
SET @plm_pending_vals := CONCAT(@plm_pending_vals, IF(@has_plm_responded_at > 0, ', NULL', ''));

SET @sql_plm_admin := CONCAT('INSERT INTO privateleaguemembers (', @plm_cols, ') VALUES (', @plm_admin_vals, ')');
SET @sql_plm_member := CONCAT('INSERT INTO privateleaguemembers (', @plm_cols, ') VALUES (', @plm_member_vals, ')');
SET @sql_plm_pending := CONCAT('INSERT INTO privateleaguemembers (', @plm_cols, ') VALUES (', @plm_pending_vals, ')');
PREPARE s4 FROM @sql_plm_admin; EXECUTE s4; DEALLOCATE PREPARE s4;
PREPARE s5 FROM @sql_plm_member; EXECUTE s5; DEALLOCATE PREPARE s5;
PREPARE s6 FROM @sql_plm_pending; EXECUTE s6; DEALLOCATE PREPARE s6;

-- --- Seed unread notifications ---
SET @has_notification_read_at := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'notification'
    AND column_name = 'read_at'
);
SET @has_notification_target_kind := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'notification'
    AND column_name = 'target_kind'
);
SET @has_notification_target_league := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'notification'
    AND column_name = 'target_league_id'
);
SET @has_notification_target_params := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'notification'
    AND column_name = 'target_params'
);

SET @notif_cols := 'notification_id, notification_type, profile_id, gameweek, picture_id, mark_read';
SET @notif_row1 := "3001, 'D1', 2, 1, 2001, 0";
SET @notif_row2 := "3002, 'D1', 2, 1, 2001, 0";
SET @notif_row3 := "3003, 'D2', 3, 1, 2001, 0";

SET @notif_cols := CONCAT(@notif_cols, IF(@has_notification_read_at > 0, ', read_at', ''));
SET @notif_row1 := CONCAT(@notif_row1, IF(@has_notification_read_at > 0, ', NULL', ''));
SET @notif_row2 := CONCAT(@notif_row2, IF(@has_notification_read_at > 0, ', NULL', ''));
SET @notif_row3 := CONCAT(@notif_row3, IF(@has_notification_read_at > 0, ', NULL', ''));

SET @notif_cols := CONCAT(@notif_cols, IF(@has_notification_target_kind > 0, ', target_kind', ''));
SET @notif_row1 := CONCAT(@notif_row1, IF(@has_notification_target_kind > 0, ", 'private_league'", ''));
SET @notif_row2 := CONCAT(@notif_row2, IF(@has_notification_target_kind > 0, ", 'private_league'", ''));
SET @notif_row3 := CONCAT(@notif_row3, IF(@has_notification_target_kind > 0, ", 'private_league_invite'", ''));

SET @notif_cols := CONCAT(@notif_cols, IF(@has_notification_target_league > 0, ', target_league_id', ''));
SET @notif_row1 := CONCAT(@notif_row1, IF(@has_notification_target_league > 0, ', 10', ''));
SET @notif_row2 := CONCAT(@notif_row2, IF(@has_notification_target_league > 0, ', 10', ''));
SET @notif_row3 := CONCAT(@notif_row3, IF(@has_notification_target_league > 0, ', 10', ''));

SET @notif_cols := CONCAT(@notif_cols, IF(@has_notification_target_params > 0, ', target_params', ''));
SET @notif_row1 := CONCAT(@notif_row1, IF(@has_notification_target_params > 0, ", '{\"privateleague_id\":2001}'", ''));
SET @notif_row2 := CONCAT(@notif_row2, IF(@has_notification_target_params > 0, ", '{\"privateleague_id\":2001}'", ''));
SET @notif_row3 := CONCAT(@notif_row3, IF(@has_notification_target_params > 0, ", '{\"privateleague_id\":2001}'", ''));

SET @sql_notifications := CONCAT(
  'INSERT INTO notification (', @notif_cols, ') VALUES ',
  '(', @notif_row1, '), ',
  '(', @notif_row2, '), ',
  '(', @notif_row3, ')'
);
PREPARE s7 FROM @sql_notifications; EXECUTE s7; DEALLOCATE PREPARE s7;

SET FOREIGN_KEY_CHECKS=1;
COMMIT;

-- Login credentials for seeded users:
--  seed.user2@example.com / TestPass123!
--  seed.user3@example.com / TestPass123!
--  seed.user4@example.com / TestPass123! (no competitor)
