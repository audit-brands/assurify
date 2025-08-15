-- Messages table for private messaging system
CREATE TABLE IF NOT EXISTS `messages` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `short_id` VARCHAR(10) UNIQUE NOT NULL,
  `token` VARCHAR(32) UNIQUE NOT NULL,
  
  `author_user_id` INTEGER NOT NULL,
  `recipient_user_id` INTEGER NOT NULL,
  
  `subject` VARCHAR(100) NOT NULL,
  `body` TEXT NOT NULL,
  
  `has_been_read` BOOLEAN NOT NULL DEFAULT 0,
  `deleted_by_author` BOOLEAN NOT NULL DEFAULT 0,
  `deleted_by_recipient` BOOLEAN NOT NULL DEFAULT 0,
  
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  
  FOREIGN KEY (`author_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`recipient_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Message replies table for threaded conversations
CREATE TABLE IF NOT EXISTS `message_replies` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `short_id` VARCHAR(10) UNIQUE NOT NULL,
  `token` VARCHAR(32) UNIQUE NOT NULL,
  
  `message_id` INTEGER NOT NULL,
  `author_user_id` INTEGER NOT NULL,
  
  `body` TEXT NOT NULL,
  
  `has_been_read` BOOLEAN NOT NULL DEFAULT 0,
  `deleted_by_author` BOOLEAN NOT NULL DEFAULT 0,
  `deleted_by_recipient` BOOLEAN NOT NULL DEFAULT 0,
  
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  
  FOREIGN KEY (`message_id`) REFERENCES `messages`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`author_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS `idx_messages_author_created` ON `messages` (`author_user_id`, `created_at`);
CREATE INDEX IF NOT EXISTS `idx_messages_recipient_created` ON `messages` (`recipient_user_id`, `created_at`);
CREATE INDEX IF NOT EXISTS `idx_messages_recipient_read` ON `messages` (`recipient_user_id`, `has_been_read`);
CREATE INDEX IF NOT EXISTS `idx_messages_short_id` ON `messages` (`short_id`);

CREATE INDEX IF NOT EXISTS `idx_message_replies_message_created` ON `message_replies` (`message_id`, `created_at`);
CREATE INDEX IF NOT EXISTS `idx_message_replies_author` ON `message_replies` (`author_user_id`);
CREATE INDEX IF NOT EXISTS `idx_message_replies_short_id` ON `message_replies` (`short_id`);