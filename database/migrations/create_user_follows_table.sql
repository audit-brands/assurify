-- Create user_follows table for Phase 6 Advanced Features
CREATE TABLE user_follows (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    follower_user_id INTEGER NOT NULL,
    following_user_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (follower_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (following_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(follower_user_id, following_user_id)
);

CREATE INDEX idx_user_follows_follower ON user_follows(follower_user_id);
CREATE INDEX idx_user_follows_following ON user_follows(following_user_id);
CREATE INDEX idx_user_follows_created_at ON user_follows(created_at);