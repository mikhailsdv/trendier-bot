-- Tables the bot expects. Names are set in .env (DB_TABLE, DB_ALICE_TABLE).

CREATE TABLE IF NOT EXISTS `trendier_bot` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `word1` VARCHAR(255) NOT NULL,
    `word2` VARCHAR(255) NOT NULL,
    `command` TEXT NOT NULL,
    `answer` TEXT NOT NULL,
    `chat_id` BIGINT NULL,
    `session_id` VARCHAR(64) NULL,
    PRIMARY KEY (`id`),
    KEY `chat_id` (`chat_id`, `id`),
    KEY `session_id` (`session_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `trendier_bot_alice` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `url` VARCHAR(255) NOT NULL,
    `is_given` TINYINT(1) NOT NULL DEFAULT 0,
    `user_id` BIGINT NULL,
    PRIMARY KEY (`id`),
    KEY `is_given` (`is_given`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- For an existing database: the rate limit looks up the last request of a chat / session
-- on every message, these indexes keep it instant as the log grows (skip the ones you already have).
-- ALTER TABLE `trendier_bot` ADD INDEX `chat_id` (`chat_id`, `id`), ADD INDEX `session_id` (`session_id`, `id`);
-- ALTER TABLE `trendier_bot_alice` ADD INDEX `is_given` (`is_given`, `user_id`);
