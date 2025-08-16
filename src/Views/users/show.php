<?php $this->layout('layout', ['title' => $title]) ?>

<div class="user-profile">
    <div class="user-header">
        <div class="user-basic-info">
            <div class="user-avatar">
                <?php 
                $avatarUrl = $user['avatar_url'] ?? null;
                if (!$avatarUrl) {
                    // Generate a simple avatar using the username initials
                    $initials = strtoupper(substr($user['username'], 0, 1));
                    if (strlen($user['username']) > 1) {
                        $initials .= strtoupper(substr($user['username'], 1, 1));
                    }
                }
                ?>
                <?php if ($avatarUrl) : ?>
                    <img src="<?=$this->e($avatarUrl)?>" alt="<?=$this->e($user['username'])?>'s avatar" class="avatar-img">
                <?php else : ?>
                    <div class="avatar-placeholder"><?=$initials?></div>
                <?php endif ?>
            </div>
            
            <div class="user-title-info">
                <h1 class="username">
                    <?=$this->e($user['username'])?>
                    <?php if ($user['is_admin']) : ?>
                        <span class="badge admin">admin</span>
                    <?php elseif ($user['is_moderator']) : ?>
                        <span class="badge moderator">mod</span>
                    <?php endif ?>
                </h1>
                
                <?php if (!empty($user['hats'])) : ?>
                    <div class="user-hats">
                        <?php foreach ($user['hats'] as $hat) : ?>
                            <span class="hat">
                                <?php if ($hat['link']) : ?>
                                    <a href="<?=$this->e($hat['link'])?>"><?=$this->e($hat['hat'])?></a>
                                <?php else : ?>
                                    <?=$this->e($hat['hat'])?>
                                <?php endif ?>
                            </span>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>

    <!-- Enhanced Status Section -->
    <div class="user-status-section">
        <div class="status-item">
            <strong>Status:</strong> 
            <?php if ($user['is_admin']) : ?>
                Administrator
            <?php elseif ($user['is_moderator']) : ?>
                Moderator
            <?php else : ?>
                Active user
            <?php endif ?>
        </div>
        
        <div class="status-item">
            <strong>Joined:</strong> 
            <?=$user['created_at_formatted']?>
            <?php if ($user['invited_by']) : ?>
                by invitation from <a href="/u/<?=$this->e($user['invited_by']['username'])?>"><?=$this->e($user['invited_by']['username'])?></a>
            <?php endif ?>
        </div>
        
        <div class="status-item">
            <strong>Karma:</strong> <?=$user['karma']?>
        </div>
        
        <div class="status-item">
            <strong>Stories Submitted:</strong> 
            <a href="/u/<?=$this->e($user['username'])?>/stories" class="stories-link">
                <?=$user['stats']['stories_count']?>
            </a>
            <?php if (!empty($user['stats']['top_tags']) && count($user['stats']['top_tags']) > 0) : ?>
                , most commonly tagged
                <?php 
                $topTags = array_slice($user['stats']['top_tags'], 0, 3);
                $tagNames = array_map(function($tag) { return $tag['name']; }, $topTags);
                echo implode(', ', $tagNames);
                ?>
            <?php endif ?>
        </div>
        
        <div class="status-item">
            <strong>Comments Posted:</strong> <?=$user['stats']['comments_count']?>
        </div>
    </div>

    <?php if ($user['about'] || $user['homepage'] || $user['github_username'] || $user['twitter_username'] || $user['mastodon_username'] || $user['linkedin_username'] || $user['bluesky_username'] || ($user['show_email'] && $user['email'])) : ?>
        <div class="user-info">
            <?php if ($user['about']) : ?>
                <div class="user-about">
                    <h3>About</h3>
                    <div class="about-content">
                        <?php if ($user['markeddown_about']) : ?>
                            <?=$user['markeddown_about']?>
                        <?php else : ?>
                            <?=nl2br($this->e($user['about']))?>
                        <?php endif ?>
                    </div>
                </div>
            <?php endif ?>
            
            <?php if ($user['homepage'] || $user['github_username'] || $user['twitter_username'] || $user['mastodon_username'] || $user['linkedin_username'] || $user['bluesky_username'] || ($user['show_email'] && $user['email'])) : ?>
                <div class="user-links">
                    <h3>Links</h3>
                    <ul>
                        <?php if ($user['show_email'] && $user['email']) : ?>
                            <li><strong>Email:</strong> <a href="mailto:<?=$this->e($user['email'])?>"><?=$this->e($user['email'])?></a></li>
                        <?php endif ?>
                        <?php if ($user['homepage']) : ?>
                            <li><strong>Homepage:</strong> <a href="<?=$this->e($user['homepage'])?>" target="_blank" rel="noopener"><?=$this->e($user['homepage'])?></a></li>
                        <?php endif ?>
                        <?php if ($user['github_username']) : ?>
                            <li><strong>GitHub:</strong> <a href="https://github.com/<?=$this->e($user['github_username'])?>" target="_blank" rel="noopener">@<?=$this->e($user['github_username'])?></a></li>
                        <?php endif ?>
                        <?php if ($user['twitter_username']) : ?>
                            <li><strong>Twitter:</strong> <a href="https://twitter.com/<?=$this->e($user['twitter_username'])?>" target="_blank" rel="noopener">@<?=$this->e($user['twitter_username'])?></a></li>
                        <?php endif ?>
                        <?php if ($user['mastodon_username']) : ?>
                            <li><strong>Mastodon:</strong> <?=$this->e($user['mastodon_username'])?></li>
                        <?php endif ?>
                        <?php if ($user['linkedin_username']) : ?>
                            <li><strong>LinkedIn:</strong> <a href="https://www.linkedin.com/in/<?=$this->e($user['linkedin_username'])?>" target="_blank" rel="noopener">linkedin.com/in/<?=$this->e($user['linkedin_username'])?></a></li>
                        <?php endif ?>
                        <?php if ($user['bluesky_username']) : ?>
                            <li><strong>Bluesky:</strong> <a href="https://bsky.app/profile/<?=$this->e($user['bluesky_username'])?>" target="_blank" rel="noopener">@<?=$this->e($user['bluesky_username'])?></a></li>
                        <?php endif ?>
                    </ul>
                </div>
            <?php endif ?>
        </div>
    <?php endif ?>

    <div class="user-activity">
        <nav class="activity-tabs">
            <a href="/u/<?=$this->e($user['username'])?>?tab=stories" class="tab <?= $tab === 'stories' ? 'active' : '' ?>">
                Stories (<?=$user['stats']['stories_count']?>)
            </a>
            <a href="/u/<?=$this->e($user['username'])?>?tab=comments" class="tab <?= $tab === 'comments' ? 'active' : '' ?>">
                Comments (<?=$user['stats']['comments_count']?>)
            </a>
            <a href="/u/<?=$this->e($user['username'])?>?tab=threads" class="tab <?= $tab === 'threads' ? 'active' : '' ?>">
                Threads
            </a>
            <?php if ($current_user_id && ($current_user_id === $user['id'] || (isset($_SESSION['is_admin']) && $_SESSION['is_admin']))) : ?>
                <a href="/u/<?=$this->e($user['username'])?>?tab=saved" class="tab <?= $tab === 'saved' ? 'active' : '' ?>">
                    Saved Stories
                </a>
            <?php endif ?>
        </nav>

        <div class="activity-content">
            <?php if ($tab === 'stories') : ?>
                <div class="stories-section">
                    <?php if (empty($user['stats']['recent_stories'])) : ?>
                        <p class="no-content">No stories submitted yet.</p>
                    <?php else : ?>
                        <div class="stories-list">
                            <?php foreach ($user['stats']['recent_stories'] as $story) : ?>
                                <div class="story-item">
                                    <div class="story-score"><?=$story['score']?></div>
                                    <div class="story-content">
                                        <h3 class="story-title">
                                            <?php if ($story['url']) : ?>
                                                <a href="<?=$this->e($story['url'])?>" target="_blank" rel="noopener">
                                                    <?=$this->e($story['title'])?>
                                                </a>
                                                <span class="story-domain">(<?=$this->e($story['domain'])?>)</span>
                                            <?php else : ?>
                                                <a href="/s/<?=$story['short_id']?>/<?=$story['slug']?>">
                                                    <?=$this->e($story['title'])?>
                                                </a>
                                            <?php endif ?>
                                        </h3>
                                        <div class="story-meta">
                                            <a href="/s/<?=$story['short_id']?>/<?=$story['slug']?>"><?=$story['comments_count']?> comments</a>
                                            | <?=$story['time_ago']?>
                                            <?php if ($story['can_edit'] ?? false) : ?>
                                                | <a href="/s/<?=$story['short_id']?>/edit">edit</a>
                                            <?php endif ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                </div>
            
            <?php elseif ($tab === 'comments') : ?>
                <div class="comments-section">
                    <?php if (empty($user['stats']['recent_comments'])) : ?>
                        <p class="no-content">No comments posted yet.</p>
                    <?php else : ?>
                        <div class="comments-list">
                            <?php foreach ($user['stats']['recent_comments'] as $comment) : ?>
                                <div class="comment-item">
                                    <div class="comment-score"><?=$comment['score']?></div>
                                    <div class="comment-content">
                                        <div class="comment-story">
                                            on <a href="/s/<?=$comment['story_short_id']?>/<?=$comment['story_slug']?>">
                                                <?=$this->e($comment['story_title'])?>
                                            </a>
                                        </div>
                                        <div class="comment-text">
                                            <?php if ($comment['markeddown_comment']) : ?>
                                                <?=$comment['markeddown_comment']?>
                                            <?php else : ?>
                                                <?=nl2br($this->e($comment['comment']))?>
                                            <?php endif ?>
                                        </div>
                                        <div class="comment-meta">
                                            <a href="/comments/<?=$comment['short_id']?>"><?=$comment['time_ago']?></a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                </div>
            <?php elseif ($tab === 'threads') : ?>
                <div class="threads-section">
                    <?php if (empty($user_threads)) : ?>
                        <p class="no-content">No threaded conversations yet.</p>
                    <?php else : ?>
                        <div class="threads-list">
                            <?php foreach ($user_threads as $thread) : ?>
                                <div class="thread-item">
                                    <div class="thread-header">
                                        <div class="thread-avatar">
                                            <?php if ($user['avatar_url']) : ?>
                                                <img src="<?=$this->e($user['avatar_url'])?>" alt="<?=$this->e($user['username'])?>" class="thread-avatar-img">
                                            <?php else : ?>
                                                <div class="thread-avatar-placeholder"><?=$initials?></div>
                                            <?php endif ?>
                                        </div>
                                        <div class="thread-meta">
                                            <strong><?=$this->e($user['username'])?></strong>
                                            <span class="thread-time"><?=$thread['time_ago']?></span>
                                            <?php if ($thread['score'] > 0) : ?>
                                                <span class="thread-score">[<?=$thread['score']?>]</span>
                                            <?php endif ?>
                                        </div>
                                    </div>
                                    
                                    <div class="thread-context">
                                        on <a href="/s/<?=$thread['story_short_id']?>/<?=$thread['story_slug']?>#comment-<?=$thread['short_id']?>" class="thread-story-link">
                                            <?=$this->e($thread['story_title'])?>
                                        </a>
                                    </div>
                                    
                                    <div class="thread-content">
                                        <?php if ($thread['markeddown_comment']) : ?>
                                            <?=$thread['markeddown_comment']?>
                                        <?php else : ?>
                                            <?=nl2br($this->e($thread['comment']))?>
                                        <?php endif ?>
                                    </div>
                                    
                                    <?php if (!empty($thread['replies'])) : ?>
                                        <div class="thread-replies">
                                            <?php foreach ($thread['replies'] as $reply) : ?>
                                                <div class="thread-reply">
                                                    <div class="reply-meta">
                                                        <strong><?=$this->e($reply['username'])?></strong> 
                                                        <span class="reply-time"><?=$reply['time_ago']?></span>
                                                        <?php if ($reply['score'] > 0) : ?>
                                                            <span class="reply-score">[<?=$reply['score']?>]</span>
                                                        <?php endif ?>
                                                    </div>
                                                    <div class="reply-content">
                                                        <?php if ($reply['markeddown_comment']) : ?>
                                                            <?=$reply['markeddown_comment']?>
                                                        <?php else : ?>
                                                            <?=nl2br($this->e($reply['comment']))?>
                                                        <?php endif ?>
                                                    </div>
                                                </div>
                                            <?php endforeach ?>
                                        </div>
                                    <?php endif ?>
                                </div>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                </div>
            
            <?php elseif ($tab === 'saved') : ?>
                <div class="stories-section">
                    <?php if (empty($saved_stories)) : ?>
                        <p class="no-content">No saved stories yet.</p>
                    <?php else : ?>
                        <div class="stories-list">
                            <?php foreach ($saved_stories as $story) : ?>
                                <div class="story-item">
                                    <div class="story-score"><?=$story['score']?></div>
                                    <div class="story-content">
                                        <h3 class="story-title">
                                            <?php if ($story['url']) : ?>
                                                <a href="<?=$this->e($story['url'])?>" target="_blank" rel="noopener">
                                                    <?=$this->e($story['title'])?>
                                                </a>
                                                <span class="story-domain">(<?=$this->e($story['domain'])?>)</span>
                                            <?php else : ?>
                                                <a href="/s/<?=$story['short_id']?>/<?=$story['slug']?>">
                                                    <?=$this->e($story['title'])?>
                                                </a>
                                            <?php endif ?>
                                        </h3>
                                        <div class="story-meta">
                                            <a href="/s/<?=$story['short_id']?>/<?=$story['slug']?>"><?=$story['comment_count']?> comments</a>
                                            | <?=$story['time_ago']?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                </div>
            <?php endif ?>
        </div>
    </div>
</div>

<style>
/* Enhanced User Profile Styling - Lobste.rs inspired */
.user-profile {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

/* User Header with Avatar */
.user-basic-info {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    margin-bottom: 25px;
}

.user-avatar {
    flex-shrink: 0;
}

.avatar-img,
.avatar-placeholder {
    width: 80px;
    height: 80px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.5em;
}

.avatar-img {
    object-fit: cover;
    border: 2px solid var(--color-fg-contrast-5);
}

.avatar-placeholder {
    background: var(--color-accent);
    color: white;
    text-transform: uppercase;
}

.user-title-info {
    flex: 1;
}

.username {
    margin: 0 0 8px 0;
    font-size: 1.8em;
    color: var(--color-fg);
}

.badge {
    font-size: 0.6em;
    padding: 2px 6px;
    border-radius: 3px;
    text-transform: uppercase;
    font-weight: bold;
    margin-left: 8px;
}

.badge.admin {
    background: var(--color-accent);
    color: white;
}

.badge.moderator {
    background: #ffa726;
    color: white;
}

/* Enhanced Status Section */
.user-status-section {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--color-fg-contrast-5);
    border-radius: 5px;
    padding: 15px;
    margin-bottom: 25px;
    line-height: 1.6;
}

.status-item {
    margin-bottom: 8px;
    color: var(--color-fg-contrast-10);
}

.status-item:last-child {
    margin-bottom: 0;
}

.status-item strong {
    color: var(--color-fg);
    font-weight: 600;
}

.status-item a {
    color: var(--color-fg-link);
    text-decoration: none;
}

.status-item a:hover {
    color: var(--color-fg-link-hover);
    text-decoration: underline;
}

.stories-link {
    font-weight: bold;
    color: var(--color-fg-link) !important;
}

.stories-link:hover {
    color: var(--color-fg-link-hover) !important;
}

/* User Info Section */
.user-info {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--color-fg-contrast-5);
    border-radius: 5px;
    padding: 15px;
    margin-bottom: 25px;
}

.user-about h3,
.user-links h3 {
    margin: 0 0 12px 0;
    color: var(--color-fg);
    font-size: 1.1em;
    font-weight: 600;
}

.about-content {
    color: var(--color-fg-contrast-10);
    line-height: 1.5;
    margin-bottom: 20px;
}

.user-links ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.user-links li {
    margin-bottom: 6px;
    color: var(--color-fg-contrast-10);
}

.user-links strong {
    color: var(--color-fg);
    font-weight: 600;
}

/* Activity Tabs */
.activity-tabs {
    display: flex;
    border-bottom: 1px solid var(--color-fg-contrast-5);
    margin-bottom: 20px;
    gap: 5px;
}

.activity-tabs .tab {
    padding: 10px 15px;
    text-decoration: none;
    color: var(--color-fg-contrast-7-5);
    border-bottom: 2px solid transparent;
    transition: all 0.2s ease;
    font-weight: 500;
}

.activity-tabs .tab:hover {
    color: var(--color-fg);
    background: rgba(255, 255, 255, 0.03);
}

.activity-tabs .tab.active {
    color: var(--color-accent);
    border-bottom-color: var(--color-accent);
}

/* Threads View Styling */
.threads-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.thread-item {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid var(--color-fg-contrast-5);
    border-radius: 5px;
    padding: 15px;
    border-left: 3px solid var(--color-accent);
}

.thread-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
}

.thread-avatar-img,
.thread-avatar-placeholder {
    width: 24px;
    height: 24px;
    border-radius: 3px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7em;
    font-weight: bold;
}

.thread-avatar-img {
    object-fit: cover;
}

.thread-avatar-placeholder {
    background: var(--color-accent);
    color: white;
    text-transform: uppercase;
}

.thread-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
}

.thread-meta strong {
    color: var(--color-fg);
}

.thread-time {
    color: var(--color-fg-contrast-7-5);
    font-size: 0.9em;
}

.thread-score {
    color: var(--color-accent);
    font-weight: bold;
    font-size: 0.9em;
}

.thread-context {
    margin-bottom: 10px;
    color: var(--color-fg-contrast-7-5);
    font-size: 0.9em;
}

.thread-story-link {
    color: var(--color-fg-link);
    text-decoration: none;
    font-weight: 500;
}

.thread-story-link:hover {
    color: var(--color-fg-link-hover);
    text-decoration: underline;
}

.thread-content {
    color: var(--color-fg-contrast-10);
    line-height: 1.5;
    margin-bottom: 15px;
}

.thread-replies {
    border-left: 2px solid var(--color-fg-contrast-5);
    margin-left: 15px;
    padding-left: 15px;
}

.thread-reply {
    margin-bottom: 12px;
    padding: 8px 0;
}

.thread-reply:last-child {
    margin-bottom: 0;
}

.reply-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 5px;
}

.reply-meta strong {
    color: var(--color-fg);
    font-size: 0.9em;
}

.reply-time {
    color: var(--color-fg-contrast-7-5);
    font-size: 0.8em;
}

.reply-score {
    color: var(--color-accent);
    font-weight: bold;
    font-size: 0.8em;
}

.reply-content {
    color: var(--color-fg-contrast-10);
    line-height: 1.4;
    font-size: 0.95em;
}

/* Responsive Design */
@media (max-width: 768px) {
    .user-basic-info {
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 10px;
    }
    
    .activity-tabs {
        flex-wrap: wrap;
    }
    
    .activity-tabs .tab {
        padding: 8px 12px;
        font-size: 0.9em;
    }
    
    .thread-header {
        flex-wrap: wrap;
    }
}
</style>