-- Create user_bookmarks table for Phase 6 Advanced Features
CREATE TABLE user_bookmarks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    story_id INTEGER NOT NULL,
    collection_id INTEGER, -- Optional: organize bookmarks into collections
    notes TEXT, -- Personal notes about the bookmark
    tags TEXT, -- JSON array of personal tags for organization
    is_favorite BOOLEAN DEFAULT 0, -- Mark as favorite bookmark
    read_at DATETIME, -- When user marked as read
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
    FOREIGN KEY (collection_id) REFERENCES user_collections(id) ON DELETE SET NULL,
    UNIQUE(user_id, story_id)
);

CREATE INDEX idx_user_bookmarks_user_id ON user_bookmarks(user_id);
CREATE INDEX idx_user_bookmarks_story_id ON user_bookmarks(story_id);
CREATE INDEX idx_user_bookmarks_collection_id ON user_bookmarks(collection_id);
CREATE INDEX idx_user_bookmarks_favorite ON user_bookmarks(is_favorite);
CREATE INDEX idx_user_bookmarks_read_at ON user_bookmarks(read_at);
CREATE INDEX idx_user_bookmarks_created_at ON user_bookmarks(created_at);