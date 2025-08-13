-- Create Assurify SQLite Database Schema
-- Converted from Rails schema for user acceptance testing

-- Users table (core table)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `username` VARCHAR(50) UNIQUE,
  `email` VARCHAR(100) UNIQUE,
  `password_digest` VARCHAR(75),
  `created_at` DATETIME,
  `is_admin` BOOLEAN NOT NULL DEFAULT 0,
  `password_reset_token` VARCHAR(75) UNIQUE,
  `session_token` VARCHAR(75) NOT NULL DEFAULT '' UNIQUE,
  `about` TEXT,
  `invited_by_user_id` INTEGER,
  `is_moderator` BOOLEAN NOT NULL DEFAULT 0,
  `pushover_mentions` BOOLEAN NOT NULL DEFAULT 0,
  `rss_token` VARCHAR(75) UNIQUE,
  `mailing_list_token` VARCHAR(75) UNIQUE,
  `mailing_list_mode` INTEGER NOT NULL DEFAULT 0,
  `karma` INTEGER NOT NULL DEFAULT 0,
  `banned_at` DATETIME,
  `banned_by_user_id` INTEGER,
  `banned_reason` VARCHAR(256),
  `deleted_at` DATETIME,
  `disabled_invite_at` DATETIME,
  `disabled_invite_by_user_id` INTEGER,
  `disabled_invite_reason` VARCHAR(200),
  `settings` TEXT,
  `show_email` BOOLEAN NOT NULL DEFAULT 0,
  `last_read_newest_story` DATETIME,
  `last_read_newest_comment` DATETIME,
  `token` VARCHAR(255) NOT NULL UNIQUE,
  FOREIGN KEY (`invited_by_user_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`banned_by_user_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`disabled_invite_by_user_id`) REFERENCES `users`(`id`)
);

-- Categories table
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `category` VARCHAR(25) NOT NULL UNIQUE,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `token` VARCHAR(255) NOT NULL UNIQUE
);

-- Tags table
CREATE TABLE IF NOT EXISTS `tags` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `tag` VARCHAR(25) NOT NULL UNIQUE,
  `description` VARCHAR(100),
  `privileged` BOOLEAN NOT NULL DEFAULT 0,
  `is_media` BOOLEAN NOT NULL DEFAULT 0,
  `active` BOOLEAN NOT NULL DEFAULT 1,
  `hotness_mod` REAL NOT NULL DEFAULT 0,
  `permit_by_new_users` BOOLEAN NOT NULL DEFAULT 1,
  `category_id` INTEGER NOT NULL,
  `token` VARCHAR(255) NOT NULL UNIQUE,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`)
);

-- Domains table
CREATE TABLE IF NOT EXISTS `domains` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `domain` VARCHAR(255) NOT NULL UNIQUE,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `banned_at` DATETIME,
  `banned_by_user_id` INTEGER,
  `banned_reason` VARCHAR(200),
  `selector` VARCHAR(255),
  `replacement` VARCHAR(255),
  `stories_count` INTEGER NOT NULL DEFAULT 0,
  `token` VARCHAR(255) NOT NULL UNIQUE,
  FOREIGN KEY (`banned_by_user_id`) REFERENCES `users`(`id`)
);

-- Stories table  
CREATE TABLE IF NOT EXISTS `stories` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `created_at` DATETIME,
  `user_id` INTEGER NOT NULL,
  `url` VARCHAR(250) DEFAULT '',
  `normalized_url` VARCHAR(255),
  `title` VARCHAR(150) NOT NULL DEFAULT '',
  `description` TEXT,
  `short_id` VARCHAR(6) NOT NULL DEFAULT '' UNIQUE,
  `is_deleted` BOOLEAN NOT NULL DEFAULT 0,
  `score` INTEGER NOT NULL DEFAULT 1,
  `flags` INTEGER NOT NULL DEFAULT 0,
  `is_moderated` BOOLEAN NOT NULL DEFAULT 0,
  `hotness` REAL NOT NULL DEFAULT 0.0,
  `markeddown_description` TEXT,
  `comments_count` INTEGER NOT NULL DEFAULT 0,
  `merged_story_id` INTEGER,
  `unavailable_at` DATETIME,
  `twitter_id` VARCHAR(20),
  `user_is_author` BOOLEAN NOT NULL DEFAULT 0,
  `user_is_following` BOOLEAN NOT NULL DEFAULT 0,
  `domain_id` INTEGER,
  `mastodon_id` VARCHAR(25),
  `origin_id` INTEGER,
  `last_comment_at` DATETIME,
  `stories_count` INTEGER NOT NULL DEFAULT 0,
  `updated_at` DATETIME NOT NULL,
  `last_edited_at` DATETIME NOT NULL,
  `token` VARCHAR(255) NOT NULL UNIQUE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`domain_id`) REFERENCES `domains`(`id`),
  FOREIGN KEY (`merged_story_id`) REFERENCES `stories`(`id`)
);

-- Comments table
CREATE TABLE IF NOT EXISTS `comments` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME,
  `short_id` VARCHAR(10) NOT NULL DEFAULT '' UNIQUE,
  `story_id` INTEGER NOT NULL,
  `confidence_order` BLOB,
  `user_id` INTEGER NOT NULL,
  `parent_comment_id` INTEGER,
  `thread_id` INTEGER,
  `comment` TEXT NOT NULL,
  `score` INTEGER NOT NULL DEFAULT 1,
  `flags` INTEGER NOT NULL DEFAULT 0,
  `confidence` REAL NOT NULL DEFAULT 0.0,
  `markeddown_comment` TEXT,
  `is_deleted` BOOLEAN NOT NULL DEFAULT 0,
  `is_moderated` BOOLEAN NOT NULL DEFAULT 0,
  `is_from_email` BOOLEAN NOT NULL DEFAULT 0,
  `hat_id` INTEGER,
  `depth` INTEGER NOT NULL DEFAULT 0,
  `reply_count` INTEGER NOT NULL DEFAULT 0,
  `last_reply_at` DATETIME,
  `last_edited_at` DATETIME NOT NULL,
  `token` VARCHAR(255) NOT NULL UNIQUE,
  FOREIGN KEY (`story_id`) REFERENCES `stories`(`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`parent_comment_id`) REFERENCES `comments`(`id`)
);

-- Invitations table
CREATE TABLE IF NOT EXISTS `invitations` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `user_id` INTEGER NOT NULL,
  `email` VARCHAR(255),
  `code` VARCHAR(255),
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `memo` TEXT,
  `used_at` DATETIME,
  `new_user_id` INTEGER,
  `token` VARCHAR(255) NOT NULL UNIQUE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`new_user_id`) REFERENCES `users`(`id`)
);

-- Taggings table (junction table for stories and tags)
CREATE TABLE IF NOT EXISTS `taggings` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `story_id` INTEGER NOT NULL,
  `tag_id` INTEGER NOT NULL,
  UNIQUE(`story_id`, `tag_id`),
  FOREIGN KEY (`story_id`) REFERENCES `stories`(`id`),
  FOREIGN KEY (`tag_id`) REFERENCES `tags`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
);

-- Votes table
CREATE TABLE IF NOT EXISTS `votes` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `user_id` INTEGER NOT NULL,
  `story_id` INTEGER NOT NULL,
  `comment_id` INTEGER,
  `vote` INTEGER NOT NULL,
  `reason` VARCHAR(1) NOT NULL DEFAULT '',
  `updated_at` DATETIME NOT NULL,
  UNIQUE(`user_id`, `story_id`),
  UNIQUE(`user_id`, `comment_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`story_id`) REFERENCES `stories`(`id`),
  FOREIGN KEY (`comment_id`) REFERENCES `comments`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_stories_created_at ON stories(created_at);
CREATE INDEX IF NOT EXISTS idx_stories_hotness ON stories(hotness);
CREATE INDEX IF NOT EXISTS idx_stories_score ON stories(score);
CREATE INDEX IF NOT EXISTS idx_comments_story_id ON comments(story_id);
CREATE INDEX IF NOT EXISTS idx_comments_user_id ON comments(user_id);
CREATE INDEX IF NOT EXISTS idx_votes_story_id ON votes(story_id);
CREATE INDEX IF NOT EXISTS idx_votes_user_id ON votes(user_id);

-- Insert initial data
INSERT OR IGNORE INTO `categories` (`id`, `category`, `created_at`, `updated_at`, `token`) VALUES
(1, 'Technology', datetime('now'), datetime('now'), 'tech'),
(2, 'Security', datetime('now'), datetime('now'), 'security'),
(3, 'Business', datetime('now'), datetime('now'), 'business'),
(4, 'General', datetime('now'), datetime('now'), 'general');

INSERT OR IGNORE INTO `tags` (`id`, `tag`, `description`, `category_id`, `token`, `created_at`, `updated_at`) VALUES
(1, 'security', 'Information Security', 2, 'sec', datetime('now'), datetime('now')),
(2, 'audit', 'Audit and Compliance', 2, 'audit', datetime('now'), datetime('now')),
(3, 'risk', 'Risk Management', 2, 'risk', datetime('now'), datetime('now')),
(4, 'tech', 'Technology', 1, 'technology', datetime('now'), datetime('now')),
(5, 'business', 'Business', 3, 'biz', datetime('now'), datetime('now'));

-- Create admin user for testing  
INSERT OR IGNORE INTO `users` (`id`, `username`, `email`, `password_digest`, `created_at`, `is_admin`, `karma`, `session_token`, `token`) VALUES
(1, 'admin', 'admin@assurify.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', datetime('now'), 1, 1000, 'admin_session_token_12345', 'admin_token_12345');