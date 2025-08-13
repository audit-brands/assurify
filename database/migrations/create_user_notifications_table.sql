-- Create user_notifications table for Phase 6 Advanced Features
CREATE TABLE user_notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    type VARCHAR(50) NOT NULL, -- mention, reply, follow, vote, message, system
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    source_user_id INTEGER, -- User who triggered the notification
    source_type VARCHAR(50), -- Story, Comment, User, etc.
    source_id INTEGER,
    action_url VARCHAR(500), -- URL to view/respond to notification
    is_read BOOLEAN DEFAULT 0,
    is_email_sent BOOLEAN DEFAULT 0,
    is_push_sent BOOLEAN DEFAULT 0,
    metadata TEXT, -- JSON additional context data
    priority VARCHAR(10) DEFAULT 'normal', -- low, normal, high, urgent
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (source_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_user_notifications_user_id ON user_notifications(user_id);
CREATE INDEX idx_user_notifications_type ON user_notifications(type);
CREATE INDEX idx_user_notifications_is_read ON user_notifications(is_read);
CREATE INDEX idx_user_notifications_priority ON user_notifications(priority);
CREATE INDEX idx_user_notifications_source ON user_notifications(source_type, source_id);
CREATE INDEX idx_user_notifications_created_at ON user_notifications(created_at);