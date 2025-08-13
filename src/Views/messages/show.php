<?php $this->layout('layout', ['title' => $title]) ?>

<div class="messages-page">
    <div class="messages-header">
        <h1>Message Thread</h1>
        <div class="messages-nav">
            <a href="/messages" class="nav-link">Inbox</a>
            <a href="/messages/sent" class="nav-link">Sent</a>
            <a href="/messages/compose" class="nav-link">Compose</a>
            <a href="/messages/search" class="nav-link">Search</a>
        </div>
    </div>

    <?php if (isset($_GET['error'])) : ?>
        <div class="error-message">
            <p><?= $this->e($_GET['error']) ?></p>
        </div>
    <?php endif ?>

    <div class="message-thread">
        <!-- Main Message -->
        <div class="thread-message main-message">
            <div class="message-header">
                <div class="message-participants">
                    <span class="from-label">From:</span>
                    <a href="/u/<?= $this->e($thread['message']['author_username']) ?>" class="participant-link">
                        <?= $this->e($thread['message']['author_username']) ?>
                    </a>
                    <span class="to-label">To:</span>
                    <a href="/u/<?= $this->e($thread['message']['recipient_username']) ?>" class="participant-link">
                        <?= $this->e($thread['message']['recipient_username']) ?>
                    </a>
                </div>
                
                <div class="message-meta">
                    <span class="message-time" title="<?= $this->e($thread['message']['created_at']) ?>">
                        <?= $this->e($thread['message']['time_ago']) ?>
                    </span>
                    <div class="message-actions">
                        <a href="/messages/compose?to=<?= urlencode($thread['message']['other_participant']) ?>&subject=<?= urlencode('Re: ' . $thread['message']['subject']) ?>" class="action-link">Reply</a>
                        <a href="/messages/<?= $this->e($thread['message']['short_id']) ?>/delete" class="action-link delete-link" 
                           onclick="return confirm('Are you sure you want to delete this message? This action cannot be undone.')">Delete</a>
                    </div>
                </div>
            </div>

            <div class="message-subject">
                <h2><?= $this->e($thread['message']['subject']) ?></h2>
            </div>

            <div class="message-body">
                <?= $thread['message']['body_html'] ?>
            </div>
        </div>

        <!-- Replies -->
        <?php if (!empty($thread['replies'])) : ?>
            <div class="thread-replies">
                <h3 class="replies-header">Replies (<?= count($thread['replies']) ?>)</h3>
                
                <?php foreach ($thread['replies'] as $reply) : ?>
                    <div class="thread-message reply-message">
                        <div class="message-header">
                            <div class="message-participants">
                                <span class="from-label">From:</span>
                                <a href="/u/<?= $this->e($reply['author_username']) ?>" class="participant-link">
                                    <?= $this->e($reply['author_username']) ?>
                                </a>
                                <span class="to-label">To:</span>
                                <a href="/u/<?= $this->e($reply['recipient_username']) ?>" class="participant-link">
                                    <?= $this->e($reply['recipient_username']) ?>
                                </a>
                            </div>
                            
                            <div class="message-meta">
                                <span class="message-time" title="<?= $this->e($reply['created_at']) ?>">
                                    <?= $this->e($reply['time_ago']) ?>
                                </span>
                            </div>
                        </div>

                        <div class="message-body">
                            <?= $reply['body_html'] ?>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endif ?>

        <!-- Reply Form -->
        <div class="reply-form-container">
            <h3>Reply to this conversation</h3>
            <form method="POST" action="/messages/<?= $this->e($thread['message']['short_id']) ?>/reply" class="reply-form">
                <div class="form-group">
                    <label for="body">Your Reply:</label>
                    <textarea id="body" 
                              name="body" 
                              placeholder="Write your reply here..."
                              class="form-textarea"
                              rows="8"
                              required></textarea>
                    <div class="form-hint">
                        Markdown formatting supported. Maximum 65,535 characters.
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Send Reply</button>
                    <a href="/messages" class="btn btn-secondary">Back to Inbox</a>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.message-thread {
    max-width: 800px;
    margin: 0 auto;
}

.thread-message {
    background-color: var(--color-bg);
    border: 1px solid var(--color-border);
    border-radius: 6px;
    margin-bottom: 20px;
    overflow: hidden;
}

.main-message {
    border-left: 4px solid var(--color-accent);
}

.reply-message {
    margin-left: 30px;
    border-left: 4px solid var(--color-secondary);
}

.message-header {
    background-color: var(--color-bg-contrast-1);
    padding: 16px;
    border-bottom: 1px solid var(--color-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.message-participants {
    display: flex;
    align-items: center;
    gap: 8px;
}

.from-label,
.to-label {
    font-size: 0.85em;
    color: var(--color-fg-contrast-5);
    font-weight: 500;
}

.participant-link {
    text-decoration: none;
    color: var(--color-link);
    font-weight: 600;
}

.participant-link:hover {
    text-decoration: underline;
}

.message-meta {
    display: flex;
    align-items: center;
    gap: 16px;
    font-size: 0.9em;
    color: var(--color-fg-contrast-5);
}

.message-time {
    white-space: nowrap;
}

.message-actions {
    display: flex;
    gap: 12px;
}

.action-link {
    text-decoration: none;
    color: var(--color-link);
    font-size: 0.85em;
    font-weight: 600;
}

.action-link:hover {
    text-decoration: underline;
}

.delete-link {
    color: var(--color-error);
}

.message-subject {
    padding: 20px 20px 0 20px;
}

.message-subject h2 {
    margin: 0;
    font-size: 1.4em;
    color: var(--color-fg);
    line-height: 1.3;
}

.message-body {
    padding: 20px;
    line-height: 1.6;
    color: var(--color-fg);
}

.message-body p {
    margin: 0 0 16px 0;
}

.message-body p:last-child {
    margin-bottom: 0;
}

.message-body a {
    color: var(--color-link);
}

.message-body a:hover {
    text-decoration: underline;
}

.message-body code {
    background-color: var(--color-bg-contrast-2);
    padding: 2px 4px;
    border-radius: 3px;
    font-size: 0.9em;
}

.message-body pre {
    background-color: var(--color-bg-contrast-2);
    padding: 12px;
    border-radius: 4px;
    overflow-x: auto;
    margin: 16px 0;
}

.message-body blockquote {
    border-left: 4px solid var(--color-border);
    margin: 16px 0;
    padding-left: 16px;
    color: var(--color-fg-contrast-4);
    font-style: italic;
}

.thread-replies {
    margin-top: 30px;
}

.replies-header {
    margin: 0 0 20px 0;
    font-size: 1.2em;
    color: var(--color-fg);
    padding-bottom: 10px;
    border-bottom: 1px solid var(--color-border);
}

.reply-form-container {
    margin-top: 40px;
    padding: 24px;
    background-color: var(--color-bg-contrast-1);
    border: 1px solid var(--color-border);
    border-radius: 6px;
}

.reply-form-container h3 {
    margin: 0 0 20px 0;
    font-size: 1.2em;
    color: var(--color-fg);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    color: var(--color-fg);
}

.form-textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
    font-size: 1em;
    font-family: inherit;
    background-color: var(--color-bg);
    color: var(--color-fg);
    resize: vertical;
    transition: border-color 0.2s ease;
}

.form-textarea:focus {
    outline: none;
    border-color: var(--color-accent);
    box-shadow: 0 0 0 2px rgba(var(--color-accent-rgb), 0.2);
}

.form-hint {
    font-size: 0.85em;
    color: var(--color-fg-contrast-5);
    margin-top: 4px;
}

.form-actions {
    display: flex;
    gap: 12px;
}

.btn {
    padding: 10px 20px;
    border-radius: 4px;
    font-size: 1em;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    display: inline-block;
    text-align: center;
    transition: all 0.2s ease;
}

.btn-primary {
    background-color: var(--color-accent);
    color: white;
}

.btn-primary:hover {
    background-color: var(--color-accent-dark);
}

.btn-secondary {
    background-color: transparent;
    color: var(--color-fg-contrast-4);
    border: 1px solid var(--color-border);
}

.btn-secondary:hover {
    background-color: var(--color-bg-contrast-2);
    color: var(--color-fg);
    text-decoration: none;
}

@media (max-width: 768px) {
    .message-thread {
        margin: 0 15px;
    }
    
    .reply-message {
        margin-left: 15px;
    }
    
    .message-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .message-meta {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .message-actions {
        flex-direction: row;
    }
    
    .reply-form-container {
        padding: 16px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        text-align: center;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Character count for reply textarea
    const bodyTextarea = document.getElementById('body');
    const maxLength = 65535;
    
    function updateCharCount() {
        const currentLength = bodyTextarea.value.length;
        const remaining = maxLength - currentLength;
        
        let hint = bodyTextarea.parentElement.querySelector('.form-hint');
        if (remaining < 1000) {
            hint.innerHTML = `Markdown formatting supported. ${remaining} characters remaining.`;
            if (remaining < 100) {
                hint.style.color = 'var(--color-error)';
            } else {
                hint.style.color = 'var(--color-warning)';
            }
        } else {
            hint.innerHTML = 'Markdown formatting supported. Maximum 65,535 characters.';
            hint.style.color = 'var(--color-fg-contrast-5)';
        }
    }

    if (bodyTextarea) {
        bodyTextarea.addEventListener('input', updateCharCount);
        updateCharCount(); // Initial count
    }

    // Auto-focus reply textarea when page loads
    if (bodyTextarea && !window.location.hash) {
        bodyTextarea.focus();
    }
});
</script>