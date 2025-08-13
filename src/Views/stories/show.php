<?php

$this->layout('layout', ['title' => $title]) ?>

<div class="story-display">
    <div class="story-header">
        <div class="story-voting">
            <?php if (isset($_SESSION['user_id'])) : ?>
                <button class="upvoter" data-story-id="<?=$story['id']?>" data-vote="1" title="Upvote"></button>
                <span class="vote-score"><?=$story['score']?></span>
            <?php else : ?>
                <span class="vote-score-guest"><?=$story['score']?></span>
            <?php endif ?>
        </div>
        
        <div class="story-content">
            <h1 class="story-title">
                <?php if ($story['url']) : ?>
                    <a href="<?=$this->e($story['url'])?>" target="_blank" rel="noopener noreferrer">
                        <?=$this->e($story['title'])?>
                    </a>
                    <?php if ($story['tags']) : ?>
                        <span class="story-tags">
                            <?php foreach ($story['tags'] as $tag) : ?>
                                <a href="/t/<?=$this->e($tag)?>" class="tag"><?=$this->e($tag)?></a>
                            <?php endforeach ?>
                        </span>
                    <?php endif ?>
                    <span class="story-domain">(<?=$this->e($story['domain'])?>)</span>
                <?php else : ?>
                    <?=$this->e($story['title'])?>
                    <?php if ($story['tags']) : ?>
                        <span class="story-tags">
                            <?php foreach ($story['tags'] as $tag) : ?>
                                <a href="/t/<?=$this->e($tag)?>" class="tag"><?=$this->e($tag)?></a>
                            <?php endforeach ?>
                        </span>
                    <?php endif ?>
                <?php endif ?>
            </h1>
            
            <div class="story-meta">
                by <a href="/u/<?=$this->e($story['username'])?>"><?=$this->e($story['username'])?></a>
                <?=$story['created_at_formatted']?>
                
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Story voting (same as homepage)
    document.querySelectorAll('.upvoter').forEach(button => {
        button.addEventListener('click', function() {
            const storyId = this.dataset.storyId;
            const vote = parseInt(this.dataset.vote);
            
            fetch(`/stories/${storyId}/vote`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ vote: vote })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const scoreElement = this.parentElement.querySelector('.vote-score');
                    if (scoreElement) {
                        scoreElement.textContent = data.score;
                    }
                    
                    const votingDiv = this.parentElement;
                    if (data.voted) {
                        votingDiv.classList.add('upvoted');
                    } else {
                        votingDiv.classList.remove('upvoted');
                    }
                } else {
                    alert(data.error || 'Failed to vote');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to vote');
            });
        });
    });

    // Comment form submission
    document.querySelectorAll('.comment-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const storyId = this.dataset.storyId || document.querySelector('[data-story-id]')?.dataset.storyId;
            const parentId = this.dataset.parentId || null;
            const textarea = this.querySelector('textarea[name="comment"]');
            const comment = textarea.value.trim();
            
            if (!comment) {
                alert('Please enter a comment.');
                return;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Posting...';
            submitBtn.disabled = true;
            
            fetch('/comments', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    story_id: storyId,
                    comment: comment,
                    parent_comment_id: parentId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirect to comment
                    window.location.href = data.redirect;
                } else {
                    alert(data.error || 'Failed to post comment');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to post comment');
            })
            .finally(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });
    });

    // Reply link functionality
    document.querySelectorAll('.reply-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const commentId = this.dataset.commentId;
            const replyForm = document.getElementById(`reply-form-${commentId}`);
            
            if (replyForm.style.display === 'none') {
                replyForm.style.display = 'block';
                replyForm.querySelector('textarea').focus();
                this.textContent = 'cancel reply';
            } else {
                replyForm.style.display = 'none';
                this.textContent = 'reply';
            }
        });
    });

    // Cancel reply buttons
    document.querySelectorAll('.cancel-reply').forEach(button => {
        button.addEventListener('click', function() {
            const form = this.closest('.reply-form');
            const commentId = form.id.replace('reply-form-', '');
            const replyLink = document.querySelector(`[data-comment-id="${commentId}"].reply-link`);
            
            form.style.display = 'none';
            form.querySelector('textarea').value = '';
            if (replyLink) {
                replyLink.textContent = 'reply';
            }
        });
    });
    
    // Comment voting
    document.querySelectorAll('.comment-voting .upvoter').forEach(button => {
        button.addEventListener('click', function() {
            const commentId = this.dataset.commentId;
            const vote = parseInt(this.dataset.vote);
            
            fetch(`/comments/${commentId}/vote`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ vote: vote })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const scoreElement = this.parentElement.querySelector('.vote-score');
                    if (scoreElement) {
                        scoreElement.textContent = data.score;
                    }
                    
                    // Update button state
                    const votingDiv = this.parentElement;
                    if (data.voted) {
                        votingDiv.classList.add('upvoted');
                    } else {
                        votingDiv.classList.remove('upvoted');
                    }
                } else {
                    alert(data.error || 'Failed to vote');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to vote');
            });
        });
    });
});
</script>