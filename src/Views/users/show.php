<?php $this->layout('layout', ['title' => $title]) ?>

<div class="user-profile">
    <div class="user-header">
        <div class="user-basic-info">
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

        <div class="user-stats">
            <div class="stat">
                <div class="stat-number"><?=$user['karma']?></div>
                <div class="stat-label">karma</div>
            </div>
            <div class="stat">
                <div class="stat-number"><?=$user['stats']['stories_count']?></div>
                <div class="stat-label">stories</div>
            </div>
            <div class="stat">
                <div class="stat-number"><?=$user['stats']['comments_count']?></div>
                <div class="stat-label">comments</div>
            </div>
            <div class="stat">
                <div class="stat-number"><?=$user['created_at_formatted']?></div>
                <div class="stat-label">joined</div>
            </div>
        </div>
    </div>

    <?php if ($user['about'] || $user['homepage'] || $user['github_username'] || $user['twitter_username'] || ($user['show_email'] && $user['email'])) : ?>
        <div class="user-info">
            <?php if ($user['about']) : ?>
                <div class="user-about">
                    <h3>About</h3>
                    <div class="about-content"><?=nl2br($this->e($user['about']))?></div>
                </div>
            <?php endif ?>
            
            <?php if ($user['homepage'] || $user['github_username'] || $user['twitter_username'] || ($user['show_email'] && $user['email'])) : ?>
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