-- Create user_profiles table for Phase 6 Advanced Features
CREATE TABLE user_profiles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    display_name VARCHAR(255),
    bio TEXT,
    location VARCHAR(255),
    website VARCHAR(255),
    twitter_handle VARCHAR(100),
    github_handle VARCHAR(100),
    linkedin_handle VARCHAR(100),
    company VARCHAR(255),
    job_title VARCHAR(255),
    expertise_tags TEXT, -- JSON array
    interests TEXT, -- JSON array
    timezone VARCHAR(50),
    preferred_language VARCHAR(10) DEFAULT 'en',
    profile_visibility VARCHAR(20) DEFAULT 'public', -- public, private, members_only
    show_email BOOLEAN DEFAULT 0,
    show_real_name BOOLEAN DEFAULT 0,
    show_location BOOLEAN DEFAULT 1,
    show_social_links BOOLEAN DEFAULT 1,
    allow_messages_from VARCHAR(20) DEFAULT 'members', -- anyone, members, followed_users, none
    email_on_mention BOOLEAN DEFAULT 1,
    email_on_reply BOOLEAN DEFAULT 1,
    email_on_follow BOOLEAN DEFAULT 0,
    push_on_mention BOOLEAN DEFAULT 1,
    push_on_reply BOOLEAN DEFAULT 1,
    push_on_follow BOOLEAN DEFAULT 1,
    last_active_at DATETIME,
    profile_views INTEGER DEFAULT 0,
    follower_count INTEGER DEFAULT 0,
    following_count INTEGER DEFAULT 0,
    reputation_score INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_user_profiles_user_id ON user_profiles(user_id);
CREATE INDEX idx_user_profiles_visibility ON user_profiles(profile_visibility);
CREATE INDEX idx_user_profiles_reputation ON user_profiles(reputation_score);