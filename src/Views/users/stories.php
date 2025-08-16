<?php $this->layout('layout', ['title' => $title]) ?>

<div class="user-stories-page">
    <!-- User Header -->
    <div class="user-header">
        <div class="user-info">
            <div class="user-avatar">
                <?php 
                $avatarUrl = $user['avatar_url'] ?? null;
                if (!$avatarUrl) {
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
            
            <div class="user-details">
                <h1>
                    <a href="/u/<?=$this->e($user['username'])?>" class="username-link">
                        <?=$this->e($user['username'])?>
                    </a>
                    <?php if ($user['is_admin']) : ?>
                        <span class="badge admin">admin</span>
                    <?php elseif ($user['is_moderator']) : ?>
                        <span class="badge moderator">mod</span>
                    <?php endif ?>
                </h1>
                
                <div class="page-stats">
                    <span class="stories-count"><?=$total_stories?> stories submitted</span>
                    <span class="karma">karma: <?=$user['karma']?></span>
                </div>
            </div>
        </div>
        
        <!-- Navigation Links -->
        <div class="user-nav">
            <a href="/u/<?=$this->e($user['username'])?>" class="nav-link">Profile</a>
            <a href="/u/<?=$this->e($user['username'])?>/stories" class="nav-link active">Stories</a>
        </div>
    </div>

    <!-- Stories List -->
    <div class="stories-content">
        <?php if (empty($stories)) : ?>
            <div class="no-stories">
                <p><?=$this->e($user['username'])?> hasn't submitted any stories yet.</p>
            </div>
        <?php else : ?>
            <ol class="stories">
                <?php foreach ($stories as $index => $story) : ?>
                    <li class="story">
                        <div class="story_liner">
                            <div class="voters">
                                <?php if (isset($current_user_id) && $current_user_id) : ?>
                                    <button class="upvoter" data-story-id="<?=$story['id']?>" data-vote="1" title="Upvote">
                                        <?=$story['score']?>
                                    </button>
                                <?php else : ?>
                                    <span class="upvoter-guest" title="Login to vote">
                                        <?=$story['score']?>
                                    </span>
                                <?php endif ?>
                            </div>
                            
                            <div class="details">
                                <div class="link">
                                    <?php if ($story['url']) : ?>
                                        <a href="<?=$this->e($story['url'])?>" target="_blank" rel="noopener">
                                            <?=$this->e($story['title'])?>
                                        </a>
                                        <span class="domain">(<?=$this->e($story['domain'])?>)</span>
                                    <?php else : ?>
                                        <a href="/s/<?=$story['short_id']?>/<?=$story['slug']?>">
                                            <?=$this->e($story['title'])?>
                                        </a>
                                        <span class="ask-story">[Ask]</span>
                                    <?php endif ?>
                                </div>
                                
                                <?php if ($story['description']) : ?>
                                    <div class="description">
                                        <?=nl2br($this->e($story['description']))?>
                                    </div>
                                <?php endif ?>
                                
                                <div class="byline">
                                    <span class="time"><?=$story['time_ago']?></span>
                                    
                                    <?php if (!empty($story['tags'])) : ?>
                                        <span class="tags">
                                            <?php foreach ($story['tags'] as $tag) : ?>
                                                <a href="/t/<?=$this->e($tag['tag'])?>" class="tag" title="<?=$this->e($tag['description'] ?? $tag['tag'])?>">
                                                    <?=$this->e($tag['tag'])?>
                                                </a>
                                            <?php endforeach ?>
                                        </span>
                                    <?php endif ?>
                                    
                                    <a href="/s/<?=$story['short_id']?>/<?=$story['slug']?>" class="comments">
                                        <?=$story['comments_count']?> comment<?=$story['comments_count'] != 1 ? 's' : ''?>
                                    </a>
                                    
                                    <?php if ($story['can_edit']) : ?>
                                        <a href="/s/<?=$story['short_id']?>/edit" class="edit">edit</a>
                                    <?php endif ?>
                                </div>
                            </div>
                        </div>
                    </li>
                <?php endforeach ?>
            </ol>
        <?php endif ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1) : ?>
        <div class="pagination">
            <?php if ($has_prev) : ?>
                <a href="/u/<?=$this->e($user['username'])?>/stories?page=<?=$page - 1?>" class="page-link prev">← Previous</a>
            <?php endif ?>
            
            <span class="page-info">
                Page <?=$page?> of <?=$total_pages?> (<?=$total_stories?> stories)
            </span>
            
            <?php if ($has_next) : ?>
                <a href="/u/<?=$this->e($user['username'])?>/stories?page=<?=$page + 1?>" class="page-link next">Next →</a>
            <?php endif ?>
        </div>
    <?php endif ?>
</div>

<style>
/* User Stories Page Styling */
.user-stories-page {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

/* User Header */
.user-header {
    margin-bottom: 30px;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.user-avatar .avatar-img,
.user-avatar .avatar-placeholder {
    width: 50px;
    height: 50px;
    border-radius: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.2em;
}

.user-avatar .avatar-img {
    object-fit: cover;
    border: 2px solid var(--color-fg-contrast-5);
}

.user-avatar .avatar-placeholder {
    background: var(--color-accent);
    color: white;
    text-transform: uppercase;
}

.user-details h1 {
    margin: 0 0 5px 0;
    font-size: 1.5em;
}

.username-link {
    color: var(--color-fg);
    text-decoration: none;
}

.username-link:hover {
    color: var(--color-fg-link);
    text-decoration: underline;
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

.page-stats {
    color: var(--color-fg-contrast-7-5);
    font-size: 0.9em;
}

.page-stats span {
    margin-right: 15px;
}

/* Navigation */
.user-nav {
    display: flex;
    gap: 15px;
    border-bottom: 1px solid var(--color-fg-contrast-5);
    padding-bottom: 10px;
}

.nav-link {
    padding: 8px 12px;
    text-decoration: none;
    color: var(--color-fg-contrast-7-5);
    border-bottom: 2px solid transparent;
    transition: all 0.2s ease;
}

.nav-link:hover {
    color: var(--color-fg);
}

.nav-link.active {
    color: var(--color-accent);
    border-bottom-color: var(--color-accent);
}

/* Stories List - Reuse homepage styling */
.stories-content {
    margin-top: 20px;
}

.stories {
    list-style: none;
    padding: 0;
    margin: 0;
}

.story {
    clear: both;
    margin-bottom: 20px;
}

.story_liner {
    display: flex;
    gap: 10px;
}

.voters {
    flex-shrink: 0;
    width: 40px;
    text-align: center;
}

.details {
    flex: 1;
}

.link {
    margin-bottom: 5px;
}

.link a {
    color: var(--color-fg);
    text-decoration: none;
    font-weight: 500;
    line-height: 1.3;
}

.link a:hover {
    color: var(--color-fg-link);
    text-decoration: underline;
}

.domain {
    color: var(--color-fg-contrast-7-5);
    font-size: 0.9em;
    margin-left: 5px;
}

.ask-story {
    color: var(--color-accent);
    font-weight: bold;
    font-size: 0.9em;
    margin-left: 5px;
}

.description {
    color: var(--color-fg-contrast-10);
    font-size: 0.95em;
    line-height: 1.4;
    margin: 8px 0;
}

.byline {
    color: var(--color-fg-contrast-7-5);
    font-size: 0.9em;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.byline a {
    color: var(--color-fg-contrast-7-5);
    text-decoration: none;
}

.byline a:hover {
    color: var(--color-fg-link);
    text-decoration: underline;
}

.tags {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.tag {
    background: var(--color-tag-bg, #333);
    color: var(--color-tag-fg, #fff);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.8em;
    text-decoration: none;
    border: 1px solid var(--color-tag-border, #555);
}

.tag:hover {
    background: var(--color-tag-hover-bg, #444);
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 30px;
    padding: 20px 0;
    border-top: 1px solid var(--color-fg-contrast-5);
}

.page-link {
    padding: 8px 15px;
    background: var(--color-accent);
    color: white;
    text-decoration: none;
    border-radius: 4px;
    font-weight: 500;
    transition: background-color 0.2s ease;
}

.page-link:hover {
    background: var(--color-accent-hover);
}

.page-info {
    color: var(--color-fg-contrast-7-5);
    font-size: 0.9em;
}

.no-stories {
    text-align: center;
    color: var(--color-fg-contrast-7-5);
    font-style: italic;
    padding: 60px 20px;
    background: rgba(255, 255, 255, 0.02);
    border-radius: 5px;
    border: 1px solid var(--color-fg-contrast-5);
}

/* Responsive Design */
@media (max-width: 768px) {
    .user-info {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    
    .user-nav {
        justify-content: center;
    }
    
    .story_liner {
        flex-direction: column;
        gap: 5px;
    }
    
    .voters {
        width: auto;
        text-align: left;
    }
    
    .byline {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
    
    .pagination {
        flex-direction: column;
        gap: 10px;
    }
}
</style>