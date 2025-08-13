-- Create user_activities table for Phase 6 Advanced Features
CREATE TABLE user_activities (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    activity_type VARCHAR(50) NOT NULL, -- story_posted, story_voted, comment_posted, comment_voted, user_followed, story_bookmarked
    target_type VARCHAR(50), -- Story, Comment, User
    target_id INTEGER,
    metadata TEXT, -- JSON data specific to activity type
    is_public BOOLEAN DEFAULT 1, -- Whether this activity should appear in public feeds
    points_earned INTEGER DEFAULT 0, -- Reputation points earned from this activity
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_user_activities_user_id ON user_activities(user_id);
CREATE INDEX idx_user_activities_type ON user_activities(activity_type);
CREATE INDEX idx_user_activities_target ON user_activities(target_type, target_id);
CREATE INDEX idx_user_activities_public ON user_activities(is_public);
CREATE INDEX idx_user_activities_created_at ON user_activities(created_at);