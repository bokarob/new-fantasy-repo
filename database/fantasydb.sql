-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 24, 2026 at 10:19 PM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u941400841_fantasydb`
--

-- --------------------------------------------------------

--
-- Table structure for table `authorizations`
--

CREATE TABLE `authorizations` (
  `authorization` int(1) NOT NULL,
  `name` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `competitor`
--

CREATE TABLE `competitor` (
  `competitor_id` int(11) NOT NULL,
  `profile_id` int(11) NOT NULL,
  `league_id` int(2) NOT NULL,
  `teamname` varchar(50) NOT NULL,
  `credits` decimal(3,1) NOT NULL,
  `favorite_team_id` int(11) DEFAULT NULL,
  `favorite_team_changed` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `extrapictures`
--

CREATE TABLE `extrapictures` (
  `profile_id` int(11) NOT NULL,
  `picture_id` int(3) NOT NULL,
  `gameweek` int(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gameweeks`
--

CREATE TABLE `gameweeks` (
  `league_id` int(2) NOT NULL,
  `gameweek` int(3) NOT NULL,
  `datefrom` date NOT NULL,
  `deadline` date NOT NULL,
  `gamedate` date NOT NULL,
  `dateto` date NOT NULL,
  `open` tinyint(1) NOT NULL,
  `updated` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `languages`
--

CREATE TABLE `languages` (
  `lang_id` int(1) NOT NULL,
  `short` varchar(2) NOT NULL,
  `language` varchar(15) NOT NULL,
  `emoji` varchar(15) NOT NULL,
  `flag` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leagues`
--

CREATE TABLE `leagues` (
  `league_id` int(2) NOT NULL,
  `league name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `matches`
--

CREATE TABLE `matches` (
  `match_id` int(11) NOT NULL,
  `league_id` int(2) NOT NULL,
  `gameweek` int(3) NOT NULL,
  `hometeam` int(11) NOT NULL,
  `awayteam` int(11) NOT NULL,
  `link` int(100) NOT NULL,
  `homepoint` decimal(2,1) NOT NULL DEFAULT 0.0,
  `awaypoint` decimal(2,1) NOT NULL DEFAULT 0.0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `news`
--

CREATE TABLE `news` (
  `news_id` int(11) NOT NULL,
  `lang_id` int(1) NOT NULL,
  `newstitle` varchar(255) NOT NULL,
  `short_description` text NOT NULL,
  `full_content` text NOT NULL,
  `live` tinyint(1) NOT NULL,
  `published_on` date NOT NULL,
  `image` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `newsletterlog`
--

CREATE TABLE `newsletterlog` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `template_name` varchar(255) NOT NULL,
  `lang` varchar(10) NOT NULL,
  `sent_at` datetime NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'sent'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `newsletter_templates`
--

CREATE TABLE `newsletter_templates` (
  `template_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `lang` enum('en','de','hu') NOT NULL,
  `subject` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification`
--

CREATE TABLE `notification` (
  `notification_id` int(11) NOT NULL,
  `notification_type` varchar(2) NOT NULL,
  `profile_id` int(11) NOT NULL,
  `gameweek` int(3) NOT NULL,
  `picture_id` int(3) NOT NULL,
  `mark_read` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notificationtext`
--

CREATE TABLE `notificationtext` (
  `notification_type` varchar(2) NOT NULL,
  `lang_id` int(1) NOT NULL,
  `text` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notificationtype`
--

CREATE TABLE `notificationtype` (
  `notification_type` varchar(2) NOT NULL,
  `name` varchar(50) NOT NULL,
  `navigation` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pictures`
--

CREATE TABLE `pictures` (
  `picture_id` int(3) NOT NULL,
  `link` varchar(50) NOT NULL,
  `basic` tinyint(1) NOT NULL DEFAULT 0,
  `secret` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `picturetexts`
--

CREATE TABLE `picturetexts` (
  `picture_id` int(3) NOT NULL,
  `lang_id` int(1) NOT NULL,
  `description` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `player`
--

CREATE TABLE `player` (
  `player_id` int(11) NOT NULL,
  `league_id` int(2) NOT NULL,
  `playername` varchar(100) NOT NULL,
  `name_short` varchar(50) NOT NULL,
  `team_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `playerresult`
--

CREATE TABLE `playerresult` (
  `player_id` int(11) NOT NULL,
  `gameweek` int(3) NOT NULL,
  `match_id` int(11) NOT NULL,
  `homegame` tinyint(1) NOT NULL,
  `played` tinyint(1) NOT NULL,
  `starter` tinyint(1) NOT NULL,
  `substituted` tinyint(1) NOT NULL,
  `row` int(1) NOT NULL,
  `pins` int(3) NOT NULL,
  `setpoints` decimal(3,1) NOT NULL,
  `matchpoints` decimal(3,1) NOT NULL,
  `points` decimal(4,1) NOT NULL,
  `opponent_id` int(11) NOT NULL,
  `opponent_result` int(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `playertrade`
--

CREATE TABLE `playertrade` (
  `player_id` int(11) NOT NULL,
  `gameweek` int(11) NOT NULL,
  `price` decimal(4,1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `privateleague`
--

CREATE TABLE `privateleague` (
  `privateleague_id` int(11) NOT NULL,
  `leaguename` varchar(50) NOT NULL,
  `league_id` int(2) NOT NULL,
  `admin` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `privateleaguemembers`
--

CREATE TABLE `privateleaguemembers` (
  `privateleague_id` int(11) NOT NULL,
  `competitor_id` int(11) NOT NULL,
  `confirmed` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `profile`
--

CREATE TABLE `profile` (
  `profile_id` int(11) NOT NULL,
  `email` varchar(50) NOT NULL,
  `password` varchar(50) NOT NULL,
  `profilename` varchar(50) NOT NULL,
  `alias` varchar(21) NOT NULL,
  `picture_id` int(3) NOT NULL DEFAULT 1,
  `authorization` int(1) NOT NULL DEFAULT 1,
  `lang_id` int(1) NOT NULL,
  `reg_token_hash` varchar(64) DEFAULT NULL,
  `reset_token_hash` varchar(64) DEFAULT NULL,
  `reset_token_expire` datetime DEFAULT NULL,
  `newsletter_subscribe` tinyint(1) DEFAULT NULL,
  `newsletter_subs_timestamp` datetime DEFAULT NULL,
  `newsletter_unsubscribe_hash` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roster`
--

CREATE TABLE `roster` (
  `competitor_id` int(11) NOT NULL,
  `gameweek` int(11) NOT NULL,
  `player1` int(11) NOT NULL,
  `player2` int(11) NOT NULL,
  `player3` int(11) NOT NULL,
  `player4` int(11) NOT NULL,
  `player5` int(11) NOT NULL,
  `player6` int(11) NOT NULL,
  `player7` int(11) NOT NULL,
  `player8` int(11) NOT NULL,
  `captain` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `team`
--

CREATE TABLE `team` (
  `team_id` int(11) NOT NULL,
  `league_id` int(2) NOT NULL,
  `name` varchar(50) NOT NULL,
  `short` varchar(15) NOT NULL,
  `logo` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teamranking`
--

CREATE TABLE `teamranking` (
  `competitor_id` int(11) NOT NULL,
  `gameweek` int(3) NOT NULL,
  `rank` int(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teamresult`
--

CREATE TABLE `teamresult` (
  `competitor_id` int(11) NOT NULL,
  `gameweek` int(3) NOT NULL,
  `weeklypoints` decimal(5,1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transfers`
--

CREATE TABLE `transfers` (
  `transfer_id` int(11) NOT NULL,
  `competitor_id` int(11) NOT NULL,
  `gameweek` int(3) NOT NULL,
  `playerout` int(11) NOT NULL,
  `playerin` int(11) NOT NULL,
  `normal` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trollanswers`
--

CREATE TABLE `trollanswers` (
  `question_id` int(11) NOT NULL,
  `profile_id` int(11) NOT NULL,
  `bet` tinyint(1) NOT NULL,
  `textbet` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trollbet`
--

CREATE TABLE `trollbet` (
  `question_id` int(11) NOT NULL,
  `gameweek` int(11) NOT NULL,
  `question` varchar(255) NOT NULL,
  `type` int(1) NOT NULL,
  `result` tinyint(1) NOT NULL,
  `optional` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trollpoints`
--

CREATE TABLE `trollpoints` (
  `profile_id` int(11) NOT NULL,
  `gameweek` int(11) NOT NULL,
  `points` int(5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `votes`
--

CREATE TABLE `votes` (
  `survey_id` int(11) NOT NULL,
  `competitor_id` int(11) NOT NULL,
  `option_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `votingoptions`
--

CREATE TABLE `votingoptions` (
  `survey_id` int(11) NOT NULL,
  `option_id` int(11) NOT NULL,
  `answerHU` varchar(255) NOT NULL,
  `answerEN` varchar(255) NOT NULL,
  `answerDE` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `votingtopics`
--

CREATE TABLE `votingtopics` (
  `survey_id` int(11) NOT NULL,
  `league_id` int(2) NOT NULL,
  `topicHU` varchar(255) NOT NULL,
  `topicEN` varchar(255) NOT NULL,
  `topicDE` varchar(255) NOT NULL,
  `questionHU` text NOT NULL,
  `questionEN` text NOT NULL,
  `questionDE` text NOT NULL,
  `open` tinyint(1) NOT NULL DEFAULT 0,
  `end_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `leaguetable`
--

CREATE TABLE leaguetable (
  league_id     INT NOT NULL,
  gameweek      SMALLINT NOT NULL,
  team_id       INT NOT NULL,

  win           INT NOT NULL DEFAULT 0,
  draw          INT NOT NULL DEFAULT 0,
  loss          INT NOT NULL DEFAULT 0,

  team_points   INT NOT NULL DEFAULT 0,
  match_points  INT NOT NULL DEFAULT 0,
  set_points    INT NOT NULL DEFAULT 0,

  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
               ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (league_id, gameweek, team_id),

  -- Useful for “table view per GW”
  INDEX idx_leaguetable_league_gw (league_id, gameweek),

  -- Useful for ordering / partial optimizations (sorting still happens, but helps scans)
  INDEX idx_leaguetable_sort (league_id, gameweek, team_points, match_points, set_points)
);

--
-- Table structure for table `auth_refresh_tokens`
--

CREATE TABLE auth_refresh_tokens (
  refresh_token_id BIGINT NOT NULL AUTO_INCREMENT,
  profile_id       INT NOT NULL,

  -- SHA-256 hash of the refresh token (32 bytes). Store hash, never raw token.
  token_hash       VARBINARY(32) NOT NULL,

  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at       TIMESTAMP NOT NULL,

  revoked_at       TIMESTAMP NULL DEFAULT NULL,
  last_used_at     TIMESTAMP NULL DEFAULT NULL,

  -- Optional but useful for session management UX / debugging
  device_name      VARCHAR(80) NULL DEFAULT NULL,

  PRIMARY KEY (refresh_token_id),

  -- Ensure a token hash can’t exist twice
  UNIQUE KEY uq_auth_refresh_token_hash (token_hash),

  -- Fast lookup for “list active sessions” or cleanup jobs
  INDEX idx_auth_refresh_profile (profile_id, revoked_at, expires_at),
  INDEX idx_auth_refresh_expires (expires_at)

 
);


--
-- Indexes for dumped tables
--

--
-- Indexes for table `authorizations`
--
ALTER TABLE `authorizations`
  ADD PRIMARY KEY (`authorization`);

--
-- Indexes for table `competitor`
--
ALTER TABLE `competitor`
  ADD PRIMARY KEY (`competitor_id`),
  ADD KEY `fk_competitor_profile` (`profile_id`);

--
-- Indexes for table `extrapictures`
--
ALTER TABLE `extrapictures`
  ADD PRIMARY KEY (`profile_id`,`picture_id`);

--
-- Indexes for table `gameweeks`
--
ALTER TABLE `gameweeks`
  ADD PRIMARY KEY (`league_id`,`gameweek`);

--
-- Indexes for table `languages`
--
ALTER TABLE `languages`
  ADD PRIMARY KEY (`lang_id`);

--
-- Indexes for table `leagues`
--
ALTER TABLE `leagues`
  ADD PRIMARY KEY (`league_id`);

--
-- Indexes for table `matches`
--
ALTER TABLE `matches`
  ADD PRIMARY KEY (`match_id`);

--
-- Indexes for table `news`
--
ALTER TABLE `news`
  ADD PRIMARY KEY (`news_id`,`lang_id`);

--
-- Indexes for table `newsletterlog`
--
ALTER TABLE `newsletterlog`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `newsletter_templates`
--
ALTER TABLE `newsletter_templates`
  ADD PRIMARY KEY (`template_id`);

--
-- Indexes for table `notification`
--
ALTER TABLE `notification`
  ADD PRIMARY KEY (`notification_id`);

--
-- Indexes for table `notificationtext`
--
ALTER TABLE `notificationtext`
  ADD PRIMARY KEY (`notification_type`,`lang_id`);

--
-- Indexes for table `notificationtype`
--
ALTER TABLE `notificationtype`
  ADD PRIMARY KEY (`notification_type`);

--
-- Indexes for table `pictures`
--
ALTER TABLE `pictures`
  ADD PRIMARY KEY (`picture_id`),
  ADD UNIQUE KEY `link` (`link`);

--
-- Indexes for table `picturetexts`
--
ALTER TABLE `picturetexts`
  ADD PRIMARY KEY (`picture_id`,`lang_id`);

--
-- Indexes for table `player`
--
ALTER TABLE `player`
  ADD PRIMARY KEY (`player_id`);

--
-- Indexes for table `playerresult`
--
ALTER TABLE `playerresult`
  ADD PRIMARY KEY (`player_id`,`gameweek`);

--
-- Indexes for table `playertrade`
--
ALTER TABLE `playertrade`
  ADD PRIMARY KEY (`player_id`,`gameweek`);

--
-- Indexes for table `privateleague`
--
ALTER TABLE `privateleague`
  ADD PRIMARY KEY (`privateleague_id`);

--
-- Indexes for table `privateleaguemembers`
--
ALTER TABLE `privateleaguemembers`
  ADD PRIMARY KEY (`privateleague_id`,`competitor_id`);

--
-- Indexes for table `profile`
--
ALTER TABLE `profile`
  ADD PRIMARY KEY (`profile_id`),
  ADD UNIQUE KEY `reset_token_hash` (`reset_token_hash`),
  ADD KEY `reg_token_hash` (`reg_token_hash`);

--
-- Indexes for table `roster`
--
ALTER TABLE `roster`
  ADD PRIMARY KEY (`competitor_id`,`gameweek`);

--
-- Indexes for table `team`
--
ALTER TABLE `team`
  ADD PRIMARY KEY (`team_id`);

--
-- Indexes for table `teamranking`
--
ALTER TABLE `teamranking`
  ADD PRIMARY KEY (`competitor_id`,`gameweek`);

--
-- Indexes for table `teamresult`
--
ALTER TABLE `teamresult`
  ADD PRIMARY KEY (`competitor_id`,`gameweek`);

--
-- Indexes for table `transfers`
--
ALTER TABLE `transfers`
  ADD PRIMARY KEY (`transfer_id`);

--
-- Indexes for table `trollanswers`
--
ALTER TABLE `trollanswers`
  ADD PRIMARY KEY (`question_id`,`profile_id`);

--
-- Indexes for table `trollbet`
--
ALTER TABLE `trollbet`
  ADD PRIMARY KEY (`question_id`);

--
-- Indexes for table `trollpoints`
--
ALTER TABLE `trollpoints`
  ADD PRIMARY KEY (`profile_id`,`gameweek`);

--
-- Indexes for table `votes`
--
ALTER TABLE `votes`
  ADD PRIMARY KEY (`survey_id`,`competitor_id`);

--
-- Indexes for table `votingoptions`
--
ALTER TABLE `votingoptions`
  ADD PRIMARY KEY (`survey_id`,`option_id`);

--
-- Indexes for table `votingtopics`
--
ALTER TABLE `votingtopics`
  ADD PRIMARY KEY (`survey_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `competitor`
--
ALTER TABLE `competitor`
  MODIFY `competitor_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `newsletterlog`
--
ALTER TABLE `newsletterlog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `newsletter_templates`
--
ALTER TABLE `newsletter_templates`
  MODIFY `template_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification`
--
ALTER TABLE `notification`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `privateleague`
--
ALTER TABLE `privateleague`
  MODIFY `privateleague_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `profile`
--
ALTER TABLE `profile`
  MODIFY `profile_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transfers`
--
ALTER TABLE `transfers`
  MODIFY `transfer_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trollbet`
--
ALTER TABLE `trollbet`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `votingtopics`
--
ALTER TABLE `votingtopics`
  MODIFY `survey_id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

--
-- updated_at columns
--

ALTER TABLE competitor
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE notification
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE privateleague
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE privateleaguemembers
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE roster
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE transfers
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE teamranking
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE teamresult
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE playertrade
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE playerresult
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE matches
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE news
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE gameweeks
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;


ALTER TABLE profile
  ADD COLUMN email_verified_at TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN otp_hash VARBINARY(32) NULL DEFAULT NULL,
  ADD COLUMN otp_expires_at TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN otp_attempts SMALLINT NOT NULL DEFAULT 0,
  ADD COLUMN otp_last_sent_at TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN otp_resend_count SMALLINT NOT NULL DEFAULT 0,
  ADD COLUMN otp_purpose VARCHAR(20) NULL DEFAULT NULL;


/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
