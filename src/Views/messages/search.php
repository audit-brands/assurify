<?php $this->layout('layout', ['title' => $title]) ?>

<div class="messages-page">
    <div class="messages-header">
        <h1>Search Messages</h1>
        <div class="messages-nav">
            <a href="/messages" class="nav-link">Inbox</a>
            <a href="/messages/sent" class="nav-link">Sent</a>
            <a href="/messages/compose" class="nav-link compose-link">Compose</a>
            <a href="/messages/search" class="nav-link active">Search</a>
        </div>
    </div>

    <div class="search-form-container">
        <form method="GET" action="/messages/search" class="search-form">
            <div class="search-input-group">
                <input type="text" 
                       name="q" 
                       value="<?= $this->e($query) ?>"
                       placeholder="Search messages by subject or content..."
                       class="search-input"
                       autocomplete="off">
                <button type="submit" class="search-button">Search</button>
            </div>
            <div class="search-hint">
                Search through your message subjects and content. Searches both sent and received messages.
            </div>
        </form>
    </div>

    <?php if ($query) : ?>
        <div class="search-results">
            <div class="results-header">
                <h2>Results for "<?= $this->e($query) ?>"</h2>
                <?php if (!empty($results)) : ?>
                    <span class="results-count"><?= count($results) ?> message<?= count($results) !== 1 ? 's' : '' ?> found</span>
                <?php endif ?>
            </div>

            <?php if (empty($results)) : ?>
                <div class="no-results">
                    <p>No messages found matching your search query.</p>
                    <div class="search-suggestions">
                        <strong>Search tips:</strong>
                        <ul>
                            <li>Try using different keywords</li>
                            <li>Check your spelling</li>
                            <li>Use shorter, more general terms</li>
                            <li>Search for specific usernames or subjects</li>
                        </ul>
                    </div>
                </div>
            <?php else : ?>
                <div class="messages-list">
                    <?php foreach ($results as $message) : ?>
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
                                        <?= $this->highlightSearchTerm($message['subject'], $query) ?>
                                    </a>
                                </h3>
                                
                                <div class="message-preview">
                                    <?= $this->highlightSearchTerm(substr(strip_tags($message['body']), 0, 200), $query) ?>
                                    <?php if (strlen(strip_tags($message['body'])) > 200) : ?>...<?php endif ?>
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
                </div>

                <?php if (count($results) >= 25) : ?>
                    <div class="pagination">
                        <?php if ($current_page > 1) : ?>
                            <a href="/messages/search?q=<?= urlencode($query) ?>&page=<?= $current_page - 1 ?>" class="pagination-link">&laquo; Previous</a>
                        <?php endif ?>
                        <span class="pagination-info">Page <?= $current_page ?></span>
                        <a href="/messages/search?q=<?= urlencode($query) ?>&page=<?= $current_page + 1 ?>" class="pagination-link">Next &raquo;</a>
                    </div>
                <?php endif ?>
            <?php endif ?>
        </div>
    <?php else : ?>
        <div class="search-help">
            <h3>Search your messages</h3>
            <p>Use the search form above to find specific messages by subject line or content. You can search through all your sent and received messages.</p>
            
            <div class="search-tips">
                <h4>Search Tips:</h4>
                <ul>
                    <li><strong>Keyword search:</strong> Enter words that might appear in the subject or message body</li>
                    <li><strong>Username search:</strong> Search for messages from or to specific users</li>
                    <li><strong>Phrase search:</strong> Put exact phrases in quotes for precise matching</li>
                    <li><strong>Multiple terms:</strong> Use multiple keywords to narrow down results</li>
                </ul>
            </div>
        </div>
    <?php endif ?>
</div>

<style>
.search-form-container {
    background-color: var(--color-bg-contrast-1);
    border: 1px solid var(--color-border);
    border-radius: 6px;
    padding: 20px;
    margin-bottom: 30px;
}

.search-form {
    max-width: 600px;
    margin: 0 auto;
}

.search-input-group {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
}

.search-input {
    flex-grow: 1;
    padding: 12px 16px;
    border: 1px solid var(--color-border);
    border-radius: 4px;
    font-size: 1em;
    background-color: var(--color-bg);
    color: var(--color-fg);
    transition: border-color 0.2s ease;
}

.search-input:focus {
    outline: none;
    border-color: var(--color-accent);
    box-shadow: 0 0 0 2px rgba(var(--color-accent-rgb), 0.2);
}

.search-button {
    padding: 12px 24px;
    background-color: var(--color-accent);
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 1em;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.search-button:hover {
    background-color: var(--color-accent-dark);
}

.search-hint {
    font-size: 0.9em;
    color: var(--color-fg-contrast-5);
    text-align: center;
}

.search-results {
    margin-top: 30px;
}

.results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--color-border);
}

.results-header h2 {
    margin: 0;
    font-size: 1.4em;
    color: var(--color-fg);
}

.results-count {
    font-size: 0.9em;
    color: var(--color-fg-contrast-4);
    font-weight: 500;
}

.no-results {
    text-align: center;
    padding: 40px 20px;
    color: var(--color-fg-contrast-4);
}

.no-results p {
    margin: 0 0 20px 0;
    font-size: 1.1em;
}

.search-suggestions {
    max-width: 400px;
    margin: 0 auto;
    text-align: left;
    background-color: var(--color-bg-contrast-1);
    border: 1px solid var(--color-border);
    border-radius: 4px;
    padding: 20px;
}

.search-suggestions strong {
    color: var(--color-fg);
    display: block;
    margin-bottom: 10px;
}

.search-suggestions ul {
    margin: 0;
    padding-left: 20px;
}

.search-suggestions li {
    margin-bottom: 6px;
    color: var(--color-fg-contrast-4);
    line-height: 1.4;
}

.search-help {
    max-width: 600px;
    margin: 40px auto;
    text-align: center;
    color: var(--color-fg-contrast-4);
}

.search-help h3 {
    margin: 0 0 15px 0;
    font-size: 1.3em;
    color: var(--color-fg);
}

.search-help p {
    margin: 0 0 30px 0;
    font-size: 1.05em;
    line-height: 1.5;
}

.search-tips {
    background-color: var(--color-bg-contrast-1);
    border: 1px solid var(--color-border);
    border-radius: 4px;
    padding: 20px;
    text-align: left;
}

.search-tips h4 {
    margin: 0 0 15px 0;
    color: var(--color-fg);
    font-size: 1.1em;
}

.search-tips ul {
    margin: 0;
    padding-left: 20px;
}

.search-tips li {
    margin-bottom: 10px;
    line-height: 1.5;
    color: var(--color-fg-contrast-4);
}

.search-tips strong {
    color: var(--color-fg);
}

/* Highlight search terms */
.search-highlight {
    background-color: yellow;
    color: #000;
    padding: 1px 2px;
    border-radius: 2px;
    font-weight: 600;
}

/* Reuse existing message list styles */
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
    
    .search-input-group {
        flex-direction: column;
    }
    
    .search-button {
        width: 100%;
    }
    
    .results-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
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
}
</style>

<script>
// Auto-focus search input when page loads
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('.search-input');
    if (searchInput && !searchInput.value) {
        searchInput.focus();
    }
});
</script>

<?php
// Helper function to highlight search terms (define this in your view class or as a helper)
if (!function_exists('highlightSearchTerm')) {
    function highlightSearchTerm($text, $term) {
        if (empty($term)) return htmlspecialchars($text);
        
        $highlighted = preg_replace(
            '/(' . preg_quote($term, '/') . ')/i',
            '<span class="search-highlight">$1</span>',
            htmlspecialchars($text)
        );
        
        return $highlighted;
    }
}

// Make this function available to the template
$this->highlightSearchTerm = 'highlightSearchTerm';
?>