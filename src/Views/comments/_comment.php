<?php
// Individual comment template (recursive for threading)
?>
<div class="comment" id="comment-<?=$comment['short_id']?>" data-comment-id="<?=$comment['id']?>">
    <div class="comment-header">
        <div class="comment-voting">
            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $comment['user_id']) : ?>
                <button class="upvoter" data-comment-id="<?=$comment['id']?>" data-vote="1" title="Upvote"></button>
            <?php endif ?>
            <span class="vote-score"><?=$comment['score']?></span>
        </div>
        
        <div class="comment-meta">
            <a href="/u/<?=$this->e($comment['username'])?>" class="username"><?=$this->e($comment['username'])?></a>
            <span class="timestamp">
                <a href="/comments/<?=$comment['short_id']?>" title="<?=$comment['created_at_formatted']?>">
                    <?=$comment['time_ago']?>
                </a>
            </span>
            
            <div class="comment-actions">
                <a href="#" class="reply-link" data-comment-id="<?=$comment['id']?>">reply</a>
                
                <?php if (isset($_SESSION['user_id'])) : ?>
                    <a href="#" class="flag-link" data-comment-id="<?=$comment['id']?>">flag</a>
                <?php endif ?>
                
                <?php if (isset($_SESSION['user_id']) && ($_SESSION['user_id'] == $comment['user_id'] || $_SESSION['is_moderator'] || $_SESSION['is_admin'])) : ?>
                    <a href="#" class="delete-link" data-comment-id="<?=$comment['id']?>">delete</a>
                <?php endif ?>
            </div>
        </div>
    </div>
    
    <div class="comment-content">
        <?php if ($comment['is_deleted']) : ?>
            <p class="deleted-comment">[deleted]</p>
        <?php else : ?>
            <?=$comment['markeddown_comment'] ?: nl2br($this->e($comment['comment']))?>
        <?php endif ?>
    </div>
    
    <!-- Reply form (hidden by default) -->
    <div class="reply-form" id="reply-form-<?=$comment['id']?>" style="display: none;">
        <form class="comment-form" data-parent-id="<?=$comment['id']?>">
            <div class="form-group">
                <textarea name="comment" placeholder="Write a reply..." rows="4" required></textarea>
            </div>
            <div class="form-actions">
                <button type="submit">Post Reply</button>
                <button type="button" class="cancel-reply">Cancel</button>
            </div>
        </form>
    </div>
    
    <!-- Nested replies -->
    <?php if (!empty($comment['replies'])) : ?>
        <div class="comment-replies">
            <?php foreach ($comment['replies'] as $reply) : ?>
                <?=$this->insert('comments/_comment', ['comment' => $reply])?>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>