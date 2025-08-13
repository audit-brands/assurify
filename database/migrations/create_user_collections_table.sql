-- Create user_collections table for Phase 6 Advanced Features
CREATE TABLE user_collections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    slug VARCHAR(255) NOT NULL,
    color VARCHAR(7) DEFAULT '#3b82f6', -- Hex color for visual organization
    is_public BOOLEAN DEFAULT 0, -- Whether collection can be shared/viewed by others
    is_default BOOLEAN DEFAULT 0, -- Default collection for bookmarks
    bookmark_count INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(user_id, slug)
);

CREATE INDEX idx_user_collections_user_id ON user_collections(user_id);
CREATE INDEX idx_user_collections_public ON user_collections(is_public);
CREATE INDEX idx_user_collections_default ON user_collections(is_default);
CREATE INDEX idx_user_collections_bookmark_count ON user_collections(bookmark_count);