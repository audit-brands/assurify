<?php

$this->layout('layout', ['title' => $title]) ?>

<div class="story-display">
    <div class="story-header">
        <div class="story-voting">
            <button class="vote-up" data-story-id="<?=$story['id']?>" data-vote="1">
                ▲
            </button>
            <div class="score"><?=$story['score']?></div>
            <button class="vote-down" data-story-id="<?=$story['id']?>" data-vote="-1">
                ▼
            </button>
        </div>
        
        <div class="story-content">
            <h1 class="story-title">
                <?php if ($story['url']) : ?>
                    <a href="<?=$this->e($story['url'])?>" target="_blank" rel="noopener noreferrer">
                        <?=$this->e($story['title'])?>
                    </a>
                    <span class="story-domain">(<?=$this->e($story['domain'])?>)</span>
                <?php else : ?>
                    <?=$this->e($story['title'])?>
                <?php endif ?>
            </h1>
            
            <div class="story-meta">
                by <a href="/u/<?=$this->e($story['username'])?>"><?=$this->e($story['username'])?></a>
                <?=$story['created_at_formatted']?>
                
                <?php if ($story['tags']) : ?>
                    <div class="story-tags">
                        <?php foreach ($story['tags'] as $tag) : ?>
                            <a href="/t/<?=$this->e($tag)?>" class="tag"><?=$this->e($tag)?></a>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
                
                <?php if ($story['user_is_author']) : ?>
                    <span class="author-badge">author</span>
                <?php endif ?>
            </div>
        </div>
    </div>
    
    <?php if ($story['description']) : ?>
        <div class="story-description">
            <?=nl2br($this->e($story['description']))?>
        </div>
    <?php endif ?>
    
    <div class="story-actions">
        <a href="/s/<?=$this->e($story['short_id'])?>#comments">discuss</a>
        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $story['user_id']) : ?>
            <a href="/s/<?=$this->e($story['short_id'])?>/edit">edit</a>
        <?php endif ?>
    </div>
    
    <div class="comments-section" id="comments">
        <h3>Comments (<?=$total_comments ?? 0?>)</h3>
        
        <?php if (isset($_SESSION['user_id'])) : ?>
            <div class="comment-form-container">
                <h4>Add a comment</h4>
                <form class="comment-form" data-story-id="<?=$story['id']?>">
                    <div class="form-group">
                        <textarea name="comment" placeholder="Write a comment..." rows="5" required></textarea>
                        <small>Supports Markdown formatting</small>
                    </div>
                    <div class="form-actions">
                        <button type="submit">Post Comment</button>
                    </div>
                </form>
            </div>
        <?php else : ?>
            <div class="login-prompt">
                <p><a href="/auth/login">Log in</a> to post a comment.</p>
            </div>
        <?php endif ?>
        
        <div class="comments-list">
            <?php if (empty($comments)) : ?>
                <p class="no-comments">No comments yet. Be the first to comment!</p>
            <?php else : ?>
                <?php foreach ($comments as $comment) : ?>
                    <?=$this->insert('comments/_comment', ['comment' => $comment])?>
                <?php endforeach ?>
            <?php endif ?>
        </div>
    </div>
</div>