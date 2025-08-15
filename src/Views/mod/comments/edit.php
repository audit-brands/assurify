<?php $this->layout('layout', ['title' => $title]) ?>

<div class="mod-page">
    <h1>Edit Comment (Moderation)</h1>
    
    <div class="comment-info">
        <p><strong>Comment:</strong> <a href="/c/<?= $this->e($comment->short_id) ?>" target="_blank">#<?= $this->e($comment->short_id) ?></a></p>
        <p><strong>Author:</strong> <a href="/~<?= $this->e($comment->user->username) ?>"><?= $this->e($comment->user->username) ?></a></p>
        <p><strong>Story:</strong> <a href="/s/<?= $this->e($comment->story->short_id) ?>"><?= $this->e($comment->story->title) ?></a></p>
        <p><strong>Posted:</strong> <?= $comment->created_at->format('Y-m-d H:i:s') ?></p>
        <?php if ($comment->is_deleted): ?>
            <p><strong>Status:</strong> <span class="deleted-status">DELETED</span></p>
        <?php endif ?>
        
        <div class="original-comment">
            <h3>Original Comment:</h3>
            <div class="comment-content">
                <?= nl2br($this->e($comment->comment)) ?>
            </div>
        </div>
    </div>

    <form id="mod-comment-form" class="mod-form">
        <div class="form-group">
            <label for="comment">Comment Content:</label>
            <textarea id="comment" name="comment" rows="10" required maxlength="5000"><?= $this->e($comment->comment) ?></textarea>
            <small>You can edit the comment content. Be careful not to change the meaning.</small>
        </div>

        <div class="form-group">
            <label for="moderation_reason">Moderation Reason:</label>
            <input type="text" id="moderation_reason" name="moderation_reason" 
                   value="<?= $this->e($comment->moderation_reason ?? '') ?>" maxlength="200"
                   placeholder="Brief explanation of changes or action taken">
        </div>

        <div class="form-actions">
            <button type="submit" class="save-btn">Save Changes</button>
            <a href="/c/<?= $this->e($comment->short_id) ?>" class="cancel-btn">Cancel</a>
            
            <?php if ($comment->is_deleted): ?>
                <button type="button" class="undelete-btn" data-comment-id="<?= $comment->short_id ?>">Undelete Comment</button>
            <?php else: ?>
                <button type="button" class="delete-btn" data-comment-id="<?= $comment->short_id ?>">Delete Comment</button>
            <?php endif ?>
        </div>
    </form>

    <!-- Moderation History -->
    <div class="moderation-history">
        <h2>Moderation History</h2>
        <?php if (empty($moderations)): ?>
            <p>No moderation actions recorded for this comment.</p>
        <?php else: ?>
            <table class="mod-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Moderator</th>
                        <th>Action</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($moderations as $moderation): ?>
                        <tr>
                            <td><?= $moderation->created_at->format('Y-m-d H:i') ?></td>
                            <td>
                                <a href="/~<?= $this->e($moderation->moderator->username) ?>">
                                    <?= $this->e($moderation->moderator->username) ?>
                                </a>
                            </td>
                            <td><?= $this->e($moderation->action) ?></td>
                            <td><?= $this->e($moderation->reason ?? '-') ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        <?php endif ?>
    </div>
</div>

<style>
.mod-page {
    max-width: 800px;
    margin: 0 auto;
    padding: 1em;
}

.comment-info {
    background: var(--color-bg, black);
    border: 1px solid var(--color-border, #555555);
    color: var(--color-fg);
    padding: 1em;
    margin-bottom: 1em;
}

.comment-info p {
    margin: 0.5em 0;
}

.deleted-status {
    color: #dc3545;
    font-weight: bold;
}

.original-comment {
    margin-top: 1em;
    padding-top: 1em;
    border-top: 1px solid var(--color-border, #555555);
}

.original-comment h3 {
    margin: 0 0 0.5em 0;
    font-size: 1em;
    color: var(--color-muted, #999);
}

.comment-content {
    background: var(--color-bg, black);
    border: 1px solid var(--color-border, #555555);
    color: var(--color-fg);
    padding: 0.75em;
    max-height: 200px;
    overflow-y: auto;
    font-family: inherit;
    line-height: 1.4;
}

.mod-form {
    border: 1px solid var(--color-border, #555555);
    padding: 1em;
    margin-bottom: 2em;
    background: var(--color-bg, black);
    color: var(--color-fg);
}

.form-group {
    margin-bottom: 1em;
}

.form-group label {
    display: block;
    font-weight: bold;
    margin-bottom: 0.3em;
    color: var(--color-fg);
}

.form-group input[type="text"],
.form-group textarea {
    width: 100%;
    padding: 0.5em;
    border: 1px solid var(--color-border, #555555);
    background: var(--color-bg, black);
    color: var(--color-fg);
    font-family: inherit;
    box-sizing: border-box;
}

.form-group textarea {
    resize: vertical;
    min-height: 150px;
    line-height: 1.4;
}

.form-group small {
    display: block;
    color: var(--color-muted, #999);
    font-size: 0.9em;
    margin-top: 0.3em;
}

.form-actions {
    display: flex;
    gap: 1em;
    align-items: center;
    padding-top: 1em;
    border-top: 1px solid var(--color-border, #555555);
}

.save-btn {
    background: #ff4444;
    color: white;
    border: none;
    padding: 0.6em 1.2em;
    cursor: pointer;
}

.cancel-btn {
    background: var(--color-muted, #666);
    color: white;
    text-decoration: none;
    padding: 0.6em 1.2em;
}

.delete-btn {
    background: #dc3545;
    color: white;
    border: none;
    padding: 0.6em 1.2em;
    cursor: pointer;
}

.undelete-btn {
    background: #007bff;
    color: white;
    border: none;
    padding: 0.6em 1.2em;
    cursor: pointer;
}

.moderation-history {
    border: 1px solid var(--color-border, #555555);
    padding: 1em;
    background: var(--color-bg, black);
    color: var(--color-fg);
}

.mod-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1em;
}

.mod-table th,
.mod-table td {
    border: 1px solid var(--color-border, #555555);
    padding: 0.5em;
    text-align: left;
    background: var(--color-bg, black);
    color: var(--color-fg);
}

.mod-table th {
    font-weight: bold;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle form submission
    document.getElementById('mod-comment-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        
        const submitBtn = this.querySelector('.save-btn');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Saving...';
        submitBtn.disabled = true;
        
        fetch('/mod/comments/<?= $comment->short_id ?>/update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    location.reload();
                }
            } else {
                alert('Error: ' + (data.error || 'Failed to save changes'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to save changes');
        })
        .finally(() => {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
    });
    
    // Handle delete button
    document.querySelector('.delete-btn')?.addEventListener('click', function() {
        const commentId = this.dataset.commentId;
        const reason = prompt('Reason for deletion (required for other users\' comments):');
        
        if (reason === null) return; // User cancelled
        
        if (confirm('Are you sure you want to delete this comment?')) {
            fetch(`/mod/comments/${commentId}/delete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ reason: reason })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    } else {
                        location.reload();
                    }
                } else {
                    alert('Error: ' + (data.error || 'Failed to delete comment'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to delete comment');
            });
        }
    });
    
    // Handle undelete button
    document.querySelector('.undelete-btn')?.addEventListener('click', function() {
        const commentId = this.dataset.commentId;
        const reason = prompt('Reason for undeletion (optional):');
        
        if (reason === null) return; // User cancelled
        
        if (confirm('Are you sure you want to undelete this comment?')) {
            fetch(`/mod/comments/${commentId}/undelete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ reason: reason })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    } else {
                        location.reload();
                    }
                } else {
                    alert('Error: ' + (data.error || 'Failed to undelete comment'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to undelete comment');
            });
        }
    });
});
</script>