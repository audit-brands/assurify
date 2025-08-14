<?php

$this->layout('layout', ['title' => $title]) ?>

<div class="story-display">
    <div class="story-header">
        <div class="story-voting">
            <?php if (isset($_SESSION['user_id'])) : ?>
                <button class="upvoter" data-story-id="<?=$story['id']?>" data-vote="1" title="Upvote"></button>
                <span class="vote-score"><?=$story['score']?></span>
            <?php else : ?>
                <span class="upvoter-guest" title="Login to vote"></span>
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
        <?php if ($can_edit ?? false) : ?>
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
    // Handle guest upvoter clicks - redirect to login
    document.querySelectorAll('.upvoter-guest').forEach(element => {
        element.addEventListener('click', function() {
            window.location.href = '/auth/login';
        });
    });

    // Story voting (authenticated users)
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
            
            // Prevent duplicate submissions
            if (this.dataset.submitting === 'true') {
                console.log('Form already submitting, ignoring duplicate submission');
                return;
            }
            this.dataset.submitting = 'true';
            
            const storyId = this.dataset.storyId || document.querySelector('[data-story-id]')?.dataset.storyId;
            const parentId = this.dataset.parentId || null;
            const textarea = this.querySelector('textarea[name="comment"]');
            const comment = textarea.value.trim();
            
            if (!comment) {
                alert('Please enter a comment.');
                this.dataset.submitting = 'false';
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
                    // Close the reply form
                    const replyForm = this.closest('.reply-form');
                    if (replyForm) {
                        replyForm.style.setProperty('display', 'none', 'important');
                        replyForm.classList.remove('force-visible');
                        
                        // Find and update the reply link text
                        const formId = replyForm.id.replace('reply-form-', '');
                        const replyLink = document.querySelector(`[data-comment-id="${formId}"].reply-link`);
                        if (replyLink) {
                            replyLink.textContent = 'reply';
                        }
                    }
                    
                    // Clear the form
                    this.querySelector('textarea').value = '';
                    
                    // Show success message briefly
                    const successMsg = document.createElement('div');
                    successMsg.style.cssText = 'background: #28a745; color: white; padding: 8px; border-radius: 4px; margin: 10px 0; text-align: center;';
                    successMsg.textContent = 'Reply posted successfully!';
                    
                    if (replyForm) {
                        replyForm.parentNode.insertBefore(successMsg, replyForm.nextSibling);
                        setTimeout(() => {
                            successMsg.remove();
                            // Optionally refresh page to show new comment
                            window.location.reload();
                        }, 1500);
                    }
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
                this.dataset.submitting = 'false';
            });
        });
    });

    // Reply link functionality - simplified and more robust
    function setupReplyFunctionality() {
        console.log('Setting up reply functionality...');
        const replyLinks = document.querySelectorAll('.reply-link');
        console.log('Found', replyLinks.length, 'reply links');
        
        replyLinks.forEach((link, index) => {
            console.log('Setting up reply link', index, 'with comment ID:', link.dataset.commentId);
            
            // Remove any existing listeners to prevent duplicates
            link.removeEventListener('click', handleReplyClick);
            link.addEventListener('click', handleReplyClick);
        });
    }
    
    function handleReplyClick(e) {
        e.preventDefault();
        console.log('Reply link clicked');
        
        const commentId = this.dataset.commentId;
        console.log('Comment ID:', commentId);
        
        const replyForm = document.getElementById(`reply-form-${commentId}`);
        console.log('Reply form element:', replyForm);
        
        if (!replyForm) {
            console.error('Reply form not found for comment ID:', commentId);
            alert('Error: Reply form not found. Please refresh the page and try again.');
            return;
        }
        
        // Check if form is currently hidden
        const currentDisplay = window.getComputedStyle(replyForm).display;
        const isHidden = currentDisplay === 'none';
        
        console.log('Current display:', currentDisplay, 'Is hidden:', isHidden);
        
        if (isHidden) {
            // Force show the form by removing display none and adding explicit class
            replyForm.style.setProperty('display', 'block', 'important');
            replyForm.style.setProperty('visibility', 'visible', 'important');
            replyForm.style.setProperty('opacity', '1', 'important');
            replyForm.style.setProperty('height', 'auto', 'important');
            
            // Remove any hiding classes and add a show class
            replyForm.classList.remove('hidden');
            replyForm.classList.add('force-visible');
            
            // Also try removing the initial display: none from the style attribute
            replyForm.style.removeProperty('display');
            replyForm.style.setProperty('display', 'block', 'important');
            
            console.log('After setting display:', window.getComputedStyle(replyForm).display);
            
            // Double-check if it's still hidden by other CSS
            const computedStyle = window.getComputedStyle(replyForm);
            console.log('All computed styles - display:', computedStyle.display, 'visibility:', computedStyle.visibility, 'opacity:', computedStyle.opacity);
            
            // Focus on textarea
            const textarea = replyForm.querySelector('textarea');
            if (textarea) {
                setTimeout(() => {
                    textarea.focus();
                    console.log('Textarea focused');
                }, 100);
            }
            
            this.textContent = 'cancel reply';
            console.log('Reply form shown for comment:', commentId);
        } else {
            // Hide the form
            replyForm.style.setProperty('display', 'none', 'important');
            this.textContent = 'reply';
            console.log('Reply form hidden for comment:', commentId);
        }
    }
    
    // Call setup function
    setupReplyFunctionality();

    // Cancel reply buttons
    document.querySelectorAll('.cancel-reply').forEach(button => {
        button.addEventListener('click', function() {
            console.log('Cancel button clicked');
            const form = this.closest('.reply-form');
            const commentId = form.id.replace('reply-form-', '');
            const replyLink = document.querySelector(`[data-comment-id="${commentId}"].reply-link`);
            
            console.log('Hiding form:', form.id);
            
            // Use the same aggressive hiding approach as the toggle
            form.style.setProperty('display', 'none', 'important');
            form.classList.remove('force-visible');
            
            // Clear the textarea
            const textarea = form.querySelector('textarea');
            if (textarea) {
                textarea.value = '';
            }
            
            // Reset the reply link text
            if (replyLink) {
                replyLink.textContent = 'reply';
                console.log('Reset reply link text');
            }
        });
    });
    
    // Comment collapse/expand functionality
    document.querySelectorAll('.comment-toggle').forEach(button => {
        button.addEventListener('click', function() {
            const commentId = this.dataset.commentId;
            const commentBody = document.querySelector(`.comment-body[data-comment-id="${commentId}"]`);
            const isCollapsed = commentBody.style.display === 'none';
            
            if (isCollapsed) {
                // Expand comment
                commentBody.style.display = 'block';
                this.textContent = '[-]';
                this.title = 'Collapse comment';
            } else {
                // Collapse comment
                commentBody.style.display = 'none';
                this.textContent = '[+]';
                this.title = 'Expand comment';
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

<style>
.force-visible {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    height: auto !important;
    max-height: none !important;
    overflow: visible !important;
}
</style>