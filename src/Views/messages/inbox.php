<?php $this->layout('layout', ['title' => $title]) ?>

<div class="messages-page">
    <div class="messages-header">
        <h1>Messages</h1>
        <div class="messages-nav">
            <a href="/messages" class="nav-link active">Inbox</a>
            <a href="/messages/sent" class="nav-link">Sent</a>
            <a href="/messages/compose" class="nav-link compose-link">Compose</a>
            <a href="/messages/search" class="nav-link">Search</a>
        </div>
    </div>

    <?php if (isset($error)) : ?>
        <div class="error-message">
            <p><?= $this->e($error) ?></p>
        </div>
    <?php endif ?>

    <?php if ($unread_count > 0) : ?>
        <div class="unread-summary">
            <p>You have <strong><?= $unread_count ?></strong> unread message<?= $unread_count !== 1 ? 's' : '' ?></p>
        </div>
    <?php endif ?>

    <div class="messages-list">
        <?php if (empty($messages)) : ?>
            <div class="no-messages">
                <p>Your inbox is empty.</p>
                <p><a href="/messages/compose">Send your first message</a></p>
            </div>
        <?php else : ?>
            <?php foreach ($messages as $message) : ?>
                <div class="message-item <?= $message['has_unread_messages'] ? 'unread' : '' ?>">
                    <div class="message-participants">
                        <?php if ($message['is_author']) : ?>
                            <span class="participant-label">To:</span>
                            <a href="/u/<?= $this->e($message['recipient_username']) ?>" class="participant-link">
                                <?= $this->e($message['recipient_username']) ?>
                            </a>
                        <?php else : ?>
                            <span class="participant-label">From:</span>
                            <a href="/u/<?= $this->e($message['author_username']) ?>" class="participant-link">
                                <?= $this->e($message['author_username']) ?>
                            </a>
                        <?php endif ?>
                        
                        <?php if ($message['has_unread_messages']) : ?>
                            <span class="unread-indicator">●</span>
                        <?php endif ?>
                    </div>

                    <div class="message-content">
                        <h3 class="message-subject">
                            <a href="/messages/<?= $this->e($message['short_id']) ?>">
                                <?= $this->e($message['subject']) ?>
                            </a>
                        </h3>
                        
                        <div class="message-preview">
                            <?= $this->e(substr(strip_tags($message['body']), 0, 150)) ?>
                            <?php if (strlen(strip_tags($message['body'])) > 150) : ?>...<?php endif ?>
                        </div>
                    </div>

                    <div class="message-meta">
                        <span class="message-time" title="<?= $this->e($message['created_at']) ?>">
                            <?= $this->e($message['time_ago']) ?>
                        </span>
                        
                        <?php if ($message['reply_count'] > 0) : ?>
                            <span class="reply-count">
                                <?= $message['reply_count'] ?> repl<?= $message['reply_count'] !== 1 ? 'ies' : 'y' ?>
                            </span>
                        <?php endif ?>
                        
                        <div class="message-actions">
                            <a href="/messages/<?= $this->e($message['short_id']) ?>" class="action-link">View</a>
                            <a href="/messages/compose?to=<?= urlencode($message['other_participant']) ?>&subject=<?= urlencode('Re: ' . $message['subject']) ?>" class="action-link">Reply</a>
                        </div>
                    </div>
                </div>
            <?php endforeach ?>
        <?php endif ?>
    </div>

    <?php if (count($messages) >= 25) : ?>
        <div class="pagination">
            <?php if ($current_page > 1) : ?>
                <a href="/messages?page=<?= $current_page - 1 ?>" class="pagination-link">&laquo; Previous</a>
            <?php endif ?>
            <span class="pagination-info">Page <?= $current_page ?></span>
            <a href="/messages?page=<?= $current_page + 1 ?>" class="pagination-link">Next &raquo;</a>
        </div>
    <?php endif ?>
</div>

<style>
.messages-page {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.messages-header {
    margin-bottom: 30px;
    border-bottom: 1px solid var(--color-border);
    padding-bottom: 20px;
}

.messages-header h1 {
    margin: 0 0 15px 0;
    font-size: 2em;
    color: var(--color-fg);
}

.messages-nav {
    display: flex;
    gap: 20px;
}

.messages-nav .nav-link {
    padding: 8px 16px;
    text-decoration: none;
    color: var(--color-fg-contrast-4);
    border-radius: 4px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.messages-nav .nav-link:hover {
    background-color: var(--color-bg-contrast-2);
    color: var(--color-fg);
}

.messages-nav .nav-link.active {
    background-color: var(--color-accent);
    color: white;
}

.messages-nav .compose-link {
    background-color: var(--color-success);
    color: white;
}

.messages-nav .compose-link:hover {
    background-color: var(--color-success-dark);
}

.unread-summary {
    background-color: var(--color-info-bg);
    border: 1px solid var(--color-info-border);
    color: var(--color-info-fg);
    padding: 12px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.unread-summary p {
    margin: 0;
}

.messages-list {
    background-color: var(--color-bg);
    border: 1px solid var(--color-border);
    border-radius: 6px;
    overflow: hidden;
}

.message-item {
    padding: 16px;
    border-bottom: 1px solid var(--color-border);
    display: flex;
    align-items: flex-start;
    gap: 16px;
    transition: background-color 0.2s ease;
}

.message-item:last-child {
    border-bottom: none;
}

.message-item:hover {
    background-color: var(--color-bg-contrast-1);
}

.message-item.unread {
    background-color: var(--color-bg-highlight);
    border-left: 4px solid var(--color-accent);
}

.message-participants {
    flex-shrink: 0;
    min-width: 120px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.participant-label {
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

.unread-indicator {
    color: var(--color-accent);
    font-size: 1.2em;
    line-height: 1;
}

.message-content {
    flex-grow: 1;
    min-width: 0;
}

.message-subject {
    margin: 0 0 8px 0;
    font-size: 1.1em;
    line-height: 1.3;
}

.message-subject a {
    text-decoration: none;
    color: var(--color-fg);
}

.message-subject a:hover {
    color: var(--color-link);
}

.message-item.unread .message-subject a {
    font-weight: 600;
}

.message-preview {
    font-size: 0.9em;
    color: var(--color-fg-contrast-4);
    line-height: 1.4;
}

.message-meta {
    flex-shrink: 0;
    text-align: right;
    min-width: 140px;
    font-size: 0.85em;
    color: var(--color-fg-contrast-5);
}

.message-time {
    display: block;
    margin-bottom: 8px;
}

.reply-count {
    display: block;
    margin-bottom: 8px;
    color: var(--color-fg-contrast-4);
}

.message-actions {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.action-link {
    text-decoration: none;
    color: var(--color-link);
    font-size: 0.85em;
}

.action-link:hover {
    text-decoration: underline;
}

.no-messages {
    padding: 40px 20px;
    text-align: center;
    color: var(--color-fg-contrast-4);
}

.no-messages p {
    margin: 0 0 10px 0;
}

.no-messages a {
    color: var(--color-link);
    text-decoration: none;
    font-weight: 600;
}

.no-messages a:hover {
    text-decoration: underline;
}

.error-message {
    background-color: var(--color-error-bg);
    border: 1px solid var(--color-error-border);
    color: var(--color-error-fg);
    padding: 12px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.error-message p {
    margin: 0;
}

.pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
    padding: 16px 0;
    border-top: 1px solid var(--color-border);
}

.pagination-link {
    text-decoration: none;
    color: var(--color-link);
    padding: 8px 12px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
    font-size: 0.9em;
}

.pagination-link:hover {
    background-color: var(--color-bg-contrast-2);
    text-decoration: none;
}

.pagination-info {
    font-size: 0.9em;
    color: var(--color-fg-contrast-4);
}

@media (max-width: 768px) {
    .messages-page {
        padding: 15px;
    }
    
    .message-item {
        flex-direction: column;
        gap: 12px;
    }
    
    .message-participants {
        min-width: auto;
        order: 2;
    }
    
    .message-content {
        order: 1;
    }
    
    .message-meta {
        order: 3;
        text-align: left;
        min-width: auto;
    }
    
    .message-actions {
        flex-direction: row;
        gap: 12px;
    }
    
    .messages-nav {
        flex-wrap: wrap;
        gap: 10px;
    }
}
</style>