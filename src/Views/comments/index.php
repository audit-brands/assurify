<?php $this->layout('layout', ['title' => $title]) ?>

<ol class="comments comments1">
    <?php if (empty($comments)) : ?>
        <p>No comments yet.</p>
    <?php else : ?>
        <?php foreach ($comments as $comment) : ?>
            <li>
                <input id="comment_folder_<?=$comment['short_id']?>" class="comment_folder_button" type="checkbox">
                
                <div class="comment_parent_tree_line"></div>
                
                <div id="c_<?=$comment['short_id']?>" data-shortid="<?=$comment['short_id']?>" class="comment">
                    <div class="voters">
                        <label for="comment_folder_<?=$comment['short_id']?>" class="comment_folder"></label>
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $comment['user_id']) : ?>
                            <a href="#" class="upvoter" data-comment-id="<?=$comment['id']?>" data-vote="1" title="Upvote"><?=$comment['score']?></a>
                        <?php else : ?>
                            <span class="upvoter"><?=$comment['score']?></span>
                        <?php endif ?>
                    </div>
                    
                    <div class="details">
                        <div class="byline">
                            <a name="c_<?=$comment['short_id']?>"></a>
                            <span>
                                <img src="/assets/avatars/user.png" class="avatar" alt="avatar" width="16" height="16" onerror="this.style.display='none'">
                                <a href="/u/<?=$this->e($comment['username'])?>"><?=$this->e($comment['username'])?></a>
                                <?=$comment['time_ago']?>
                                | on: <a href="/s/<?=$comment['story_short_id']?>/<?=$comment['story_slug']?>"><?=$this->e($comment['story_title'])?></a>
                                <?php if (isset($_SESSION['user_id'])) : ?>
                                    | <a href="#" class="reply-link" data-comment-id="<?=$comment['id']?>">reply</a>
                                <?php endif ?>
                            </span>
                        </div>
                        <div class="comment_text">
                            <?=$comment['content']?>
                        </div>
                    </div>
                </div>
                
                <!-- Reply form for this comment -->
                <?php if (isset($_SESSION['user_id'])) : ?>
                <div id="reply-form-<?=$comment['id']?>" class="reply-form" style="display: none; background: #222; border: 1px solid #444; padding: 15px; margin: 10px 0; border-radius: 4px; position: relative; z-index: 100;">
                    <form class="comment-form" data-story-id="<?=$comment['story_id']?>" data-parent-id="<?=$comment['id']?>">
                        <div class="form-group">
                            <textarea name="comment" placeholder="Write a reply..." rows="4" required style="width: 100%; background: #333; color: #fff; border: 1px solid #555; padding: 8px; border-radius: 3px;"></textarea>
                        </div>
                        <div class="form-actions" style="margin-top: 10px;">
                            <button type="submit" style="background: var(--color-accent); color: white; border: 1px solid var(--color-accent); padding: 8px 16px; border-radius: 3px; margin-right: 8px; cursor: pointer;">Post Reply</button>
                            <button type="button" class="cancel-reply" style="background: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 3px; cursor: pointer;">Cancel</button>
                        </div>
                    </form>
                </div>
                <?php endif ?>
                
                
                <ol class="comments"></ol>
            </li>
        <?php endforeach ?>
    <?php endif ?>
</ol>

<?php if (!empty($comments)) : ?>
<div class="morelink">
    <?php if ($page > 1) : ?>
        <a href="?page=<?=$page - 1?>">&lt;&lt; Page <?=$page - 1?></a>
    <?php endif ?>
    
    <?php if ($page > 1 && $hasMore) : ?>
        |
    <?php endif ?>
    
    <?php if ($hasMore) : ?>
        <a href="?page=<?=$page + 1?>">Page <?=$page + 1?> &gt;&gt;</a>
    <?php endif ?>
</div>
<?php endif ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Comments page JavaScript loaded');
    
    // Debug: Check if user is logged in
    <?php if (isset($_SESSION['user_id'])) : ?>
        console.log('User is logged in, user_id:', <?=$_SESSION['user_id']?>);
    <?php else : ?>
        console.log('User is NOT logged in');
    <?php endif ?>

    // Comment voting functionality
    document.querySelectorAll('.upvoter[data-comment-id]').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
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
                    // Update score display
                    this.textContent = data.score;
                    
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
    
    // Reply link functionality
    function setupReplyFunctionality() {
        console.log('Setting up reply functionality...');
        const replyLinks = document.querySelectorAll('.reply-link');
        console.log('Found', replyLinks.length, 'reply links');
        
        // Debug: also check for reply forms
        const replyForms = document.querySelectorAll('.reply-form');
        console.log('Found', replyForms.length, 'reply forms');
        
        replyForms.forEach((form, index) => {
            console.log('Reply form', index, 'ID:', form.id);
        });
        
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
            // Force show the form with aggressive styling
            replyForm.style.setProperty('display', 'block', 'important');
            replyForm.style.setProperty('visibility', 'visible', 'important');
            replyForm.style.setProperty('opacity', '1', 'important');
            replyForm.style.setProperty('height', 'auto', 'important');
            
            // Remove any hiding classes and add a show class
            replyForm.classList.remove('hidden');
            replyForm.classList.add('force-visible');
            
            console.log('After setting display:', window.getComputedStyle(replyForm).display);
            
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
            replyForm.classList.remove('force-visible');
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
            
            const formData = new FormData(this);
            const storyId = this.dataset.storyId;
            const parentCommentId = this.dataset.parentId; // Fixed: should be parentId, not parentCommentId
            
            const data = {
                story_id: storyId,
                comment: formData.get('comment'),
                parent_comment_id: parentCommentId
            };
            
            console.log('Submitting comment:', data);
            
            fetch('/comments', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                console.log('Comment response:', data);
                
                if (data.success) {
                    // Close the reply form instead of redirecting
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
                            // Refresh page to show new comment
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
                // Reset submission flag
                this.dataset.submitting = 'false';
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