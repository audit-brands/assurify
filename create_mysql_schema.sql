-- Create Assurify MySQL Database Schema
-- Converted from Rails schema for user acceptance testing

DROP DATABASE IF EXISTS assurify_dev;
CREATE DATABASE assurify_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE assurify_dev;

-- Users table (core table)
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_digest` varchar(75) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `password_reset_token` varchar(75) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_token` varchar(75) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `about` mediumtext COLLATE utf8mb4_unicode_ci,
  `invited_by_user_id` bigint unsigned DEFAULT NULL,
  `is_moderator` tinyint(1) NOT NULL DEFAULT '0',
  `pushover_mentions` tinyint(1) NOT NULL DEFAULT '0',
  `rss_token` varchar(75) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mailing_list_token` varchar(75) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mailing_list_mode` int NOT NULL DEFAULT '0',
  `karma` int NOT NULL DEFAULT '0',
  `banned_at` datetime DEFAULT NULL,
  `banned_by_user_id` bigint unsigned DEFAULT NULL,
  `banned_reason` varchar(256) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `disabled_invite_at` datetime DEFAULT NULL,
  `disabled_invite_by_user_id` bigint unsigned DEFAULT NULL,
  `disabled_invite_reason` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `settings` mediumtext COLLATE utf8mb4_unicode_ci,
  `show_email` tinyint(1) NOT NULL DEFAULT '0',
  `last_read_newest_story` datetime DEFAULT NULL,
  `last_read_newest_comment` datetime DEFAULT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `index_users_on_email` (`email`),
  UNIQUE KEY `session_hash` (`session_token`),
  UNIQUE KEY `password_reset_token` (`password_reset_token`),
  UNIQUE KEY `rss_token` (`rss_token`),
  UNIQUE KEY `mailing_list_token` (`mailing_list_token`),
  UNIQUE KEY `index_users_on_token` (`token`),
  KEY `users_invited_by_user_id_fk` (`invited_by_user_id`),
  KEY `users_banned_by_user_id_fk` (`banned_by_user_id`),
  KEY `users_disabled_invite_by_user_id_fk` (`disabled_invite_by_user_id`),
  KEY `mailing_list_enabled` (`mailing_list_mode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories table
CREATE TABLE `categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `index_categories_on_category` (`category`),
  UNIQUE KEY `index_categories_on_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tags table
CREATE TABLE `tags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tag` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `privileged` tinyint(1) NOT NULL DEFAULT '0',
  `is_media` tinyint(1) NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `hotness_mod` float NOT NULL DEFAULT '0',
  `permit_by_new_users` tinyint(1) NOT NULL DEFAULT '1',
  `category_id` bigint unsigned NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tag` (`tag`),
  UNIQUE KEY `index_tags_on_token` (`token`),
  KEY `index_tags_on_category_id` (`category_id`),
  CONSTRAINT `tags_category_fk` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Domains table
CREATE TABLE `domains` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `domain` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `banned_at` datetime DEFAULT NULL,
  `banned_by_user_id` bigint unsigned DEFAULT NULL,
  `banned_reason` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `selector` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `replacement` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stories_count` int NOT NULL DEFAULT '0',
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `index_domains_on_domain` (`domain`),
  UNIQUE KEY `index_domains_on_token` (`token`),
  KEY `index_domains_on_banned_by_user_id` (`banned_by_user_id`),
  CONSTRAINT `domains_banned_by_user_fk` FOREIGN KEY (`banned_by_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stories table  
CREATE TABLE `stories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_at` datetime DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `url` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `normalized_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` text COLLATE utf8mb4_unicode_ci,
  `short_id` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `score` int NOT NULL DEFAULT '1',
  `flags` int unsigned NOT NULL DEFAULT '0',
  `is_moderated` tinyint(1) NOT NULL DEFAULT '0',
  `hotness` decimal(20,10) NOT NULL DEFAULT '0.0000000000',
  `markeddown_description` mediumtext COLLATE utf8mb4_unicode_ci,
  `comments_count` int NOT NULL DEFAULT '0',
  `merged_story_id` bigint unsigned DEFAULT NULL,
  `unavailable_at` datetime DEFAULT NULL,
  `twitter_id` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_is_author` tinyint(1) NOT NULL DEFAULT '0',
  `user_is_following` tinyint(1) NOT NULL DEFAULT '0',
  `domain_id` bigint unsigned DEFAULT NULL,
  `mastodon_id` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origin_id` bigint unsigned DEFAULT NULL,
  `last_comment_at` datetime DEFAULT NULL,
  `stories_count` int NOT NULL DEFAULT '0',
  `updated_at` datetime NOT NULL,
  `last_edited_at` datetime NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_short_id` (`short_id`),
  UNIQUE KEY `index_stories_on_token` (`token`),
  KEY `index_stories_on_user_id` (`user_id`),
  KEY `index_stories_on_created_at` (`created_at`),
  KEY `hotness_idx` (`hotness`),
  KEY `index_stories_on_score` (`score`),
  KEY `index_stories_on_domain_id` (`domain_id`),
  KEY `index_stories_on_merged_story_id` (`merged_story_id`),
  KEY `index_stories_on_normalized_url` (`normalized_url`),
  KEY `index_stories_on_last_comment_at` (`last_comment_at`),
  KEY `index_stories_on_mastodon_id` (`mastodon_id`),
  KEY `url` (`url`(191)),
  KEY `index_stories_on_id_and_is_deleted` (`id`,`is_deleted`),
  CONSTRAINT `stories_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `stories_domain_id_fk` FOREIGN KEY (`domain_id`) REFERENCES `domains` (`id`),
  CONSTRAINT `stories_merged_story_id_fk` FOREIGN KEY (`merged_story_id`) REFERENCES `stories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comments table
CREATE TABLE `comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `short_id` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `story_id` bigint unsigned NOT NULL,
  `confidence_order` binary(3) NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `parent_comment_id` bigint unsigned DEFAULT NULL,
  `thread_id` bigint unsigned DEFAULT NULL,
  `comment` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `score` int NOT NULL DEFAULT '1',
  `flags` int unsigned NOT NULL DEFAULT '0',
  `confidence` decimal(20,19) NOT NULL DEFAULT '0.0000000000000000000',
  `markeddown_comment` mediumtext COLLATE utf8mb4_unicode_ci,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `is_moderated` tinyint(1) NOT NULL DEFAULT '0',
  `is_from_email` tinyint(1) NOT NULL DEFAULT '0',
  `hat_id` bigint unsigned DEFAULT NULL,
  `depth` int NOT NULL DEFAULT '0',
  `reply_count` int NOT NULL DEFAULT '0',
  `last_reply_at` datetime DEFAULT NULL,
  `last_edited_at` datetime NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `short_id` (`short_id`),
  UNIQUE KEY `index_comments_on_token` (`token`),
  KEY `index_comments_on_user_id` (`user_id`),
  KEY `story_id_short_id` (`story_id`,`short_id`),
  KEY `comments_parent_comment_id_fk` (`parent_comment_id`),
  KEY `thread_id` (`thread_id`),
  KEY `confidence_idx` (`confidence`),
  KEY `index_comments_on_score` (`score`),
  FULLTEXT KEY `index_comments_on_comment` (`comment`),
  CONSTRAINT `comments_story_id_fk` FOREIGN KEY (`story_id`) REFERENCES `stories` (`id`),
  CONSTRAINT `comments_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `comments_parent_comment_id_fk` FOREIGN KEY (`parent_comment_id`) REFERENCES `comments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invitations table
CREATE TABLE `invitations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `memo` text COLLATE utf8mb4_unicode_ci,
  `used_at` datetime DEFAULT NULL,
  `new_user_id` bigint unsigned DEFAULT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `index_invitations_on_token` (`token`),
  KEY `invitations_user_id_fk` (`user_id`),
  KEY `invitations_new_user_id_fk` (`new_user_id`),
  CONSTRAINT `invitations_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `invitations_new_user_id_fk` FOREIGN KEY (`new_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Taggings table (junction table for stories and tags)
CREATE TABLE `taggings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `story_id` bigint unsigned NOT NULL,
  `tag_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `story_id_tag_id` (`story_id`,`tag_id`),
  KEY `taggings_tag_id_fk` (`tag_id`),
  CONSTRAINT `taggings_story_id_fk` FOREIGN KEY (`story_id`) REFERENCES `stories` (`id`),
  CONSTRAINT `taggings_tag_id_fk` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Votes table
CREATE TABLE `votes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `story_id` bigint unsigned NOT NULL,
  `comment_id` bigint unsigned DEFAULT NULL,
  `vote` tinyint NOT NULL,
  `reason` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id_story_id` (`user_id`,`story_id`),
  KEY `user_id_comment_id` (`user_id`,`comment_id`),
  KEY `votes_story_id_fk` (`story_id`),
  KEY `index_votes_on_comment_id` (`comment_id`),
  CONSTRAINT `votes_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `votes_story_id_fk` FOREIGN KEY (`story_id`) REFERENCES `stories` (`id`),
  CONSTRAINT `votes_comment_id_fk` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert initial data
INSERT INTO `categories` (`category`, `created_at`, `updated_at`, `token`) VALUES
('Technology', NOW(), NOW(), 'tech'),
('Security', NOW(), NOW(), 'security'),
('Business', NOW(), NOW(), 'business'),
('General', NOW(), NOW(), 'general');

INSERT INTO `tags` (`tag`, `description`, `category_id`, `token`, `created_at`, `updated_at`) VALUES
('security', 'Information Security', 2, 'sec', NOW(), NOW()),
('audit', 'Audit and Compliance', 2, 'audit', NOW(), NOW()),
('risk', 'Risk Management', 2, 'risk', NOW(), NOW()),
('tech', 'Technology', 1, 'technology', NOW(), NOW()),
('business', 'Business', 3, 'biz', NOW(), NOW());

-- Create admin user for testing  
INSERT INTO `users` (`username`, `email`, `password_digest`, `created_at`, `is_admin`, `karma`, `session_token`, `token`) VALUES
('admin', 'admin@assurify.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NOW(), 1, 1000, 'admin_session_token_12345', 'admin_token_12345');