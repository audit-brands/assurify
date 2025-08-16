<?php $this->layout('layout', ['title' => $title]) ?>

<div class="moderation-flagged">
    <div class="breadcrumb">
        <a href="/mod" class="back-link">← Moderation Dashboard</a>
    </div>
    <h1>Flagged Content</h1>
    
    <?php if (isset($error)) : ?>
        <div class="error-message">
            <p><strong>Error:</strong> <?= $this->e($error) ?></p>
        </div>
    <?php endif ?>
    
    <div class="filter-nav">
        <a href="?type=all" class="filter-link <?= $type === 'all' ? 'active' : '' ?>">All</a>
        <a href="?type=stories" class="filter-link <?= $type === 'stories' ? 'active' : '' ?>">Stories</a>
        <a href="?type=comments" class="filter-link <?= $type === 'comments' ? 'active' : '' ?>">Comments</a>
    </div>
    
    <?php if ($type === 'all' || $type === 'stories') : ?>
        <?php if (!empty($flagged_stories)) : ?>
            <div class="flagged-section">
                <h2>Flagged Stories (<?= count($flagged_stories) ?>)</h2>
                <div class="flagged-list">
                    <?php foreach ($flagged_stories as $story) : ?>
                        <div class="flagged-item" data-type="story" data-id="<?= $story['id'] ?>">
                            <div class="item-header">
                                <h3>
                                    <a href="/s/<?= $this->e($story['short_id']) ?>" target="_blank">
                                        <?= $this->e($story['title']) ?>
                                    </a>
                                </h3>
                                <span class="score score-<?= $story['score'] < 0 ? 'negative' : 'positive' ?>">
                                    <?= $story['score'] ?> points
                                </span>
                            </div>
                            
                            <div class="item-meta">
                                <span class="author">by <a href="/u/<?= $this->e($story['user']) ?>"><?= $this->e($story['user']) ?></a></span>
                                <span class="time"><?= $story['time_ago'] ?></span>
                                <?php if ($story['is_flagged']) : ?>
                                    <span class="flag-indicator">FLAGGED</span>
                                    <?php if ($story['flag_reason']) : ?>
                                        <span class="flag-reason">Reason: <?= $this->e($story['flag_reason']) ?></span>
                                    <?php endif ?>
                                <?php endif ?>
                                <?php if ($story['score'] < 0) : ?>
                                    <span class="low-score">LOW SCORE</span>
                                <?php endif ?>
                            </div>
                            
                            <?php if ($story['description']) : ?>
                                <div class="item-description">
                                    <?= $this->e(substr($story['description'], 0, 200)) ?><?= strlen($story['description']) > 200 ? '...' : '' ?>
                                </div>
                            <?php endif ?>
                            
                            <div class="mod-actions">
                                <a href="/mod/stories/<?= $story['short_id'] ?>/edit" class="mod-btn edit">Edit</a>
                                <button class="mod-btn approve" data-action="approve">Approve</button>
                                <button class="mod-btn flag" data-action="flag">Flag</button>
                                <button class="mod-btn delete" data-action="delete">Delete</button>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
        <?php elseif ($type === 'stories') : ?>
            <div class="no-content">
                <p>No flagged stories at this time.</p>
            </div>
        <?php endif ?>
    <?php endif ?>
    
    <?php if ($type === 'all' || $type === 'comments') : ?>
        <?php if (!empty($flagged_comments)) : ?>
            <div class="flagged-section">
                <h2>Flagged Comments (<?= count($flagged_comments) ?>)</h2>
                <div class="flagged-list">
                    <?php foreach ($flagged_comments as $comment) : ?>
                        <div class="flagged-item" data-type="comment" data-id="<?= $comment['id'] ?>">
                            <div class="item-header">
                                <h3>
                                    <a href="/s/<?= $this->e($comment['story']['short_id']) ?>#comment-<?= $this->e($comment['short_id']) ?>" target="_blank">
                                        Comment on: <?= $this->e($comment['story']['title']) ?>
                                    </a>
                                </h3>
                                <span class="score score-<?= $comment['score'] < 0 ? 'negative' : 'positive' ?>">
                                    <?= $comment['score'] ?> points
                                </span>
                            </div>
                            
                            <div class="item-meta">
                                <span class="author">by <a href="/u/<?= $this->e($comment['user']) ?>"><?= $this->e($comment['user']) ?></a></span>
                                <span class="time"><?= $comment['time_ago'] ?></span>
                                <?php if ($comment['is_flagged']) : ?>
                                    <span class="flag-indicator">FLAGGED (<?= $comment['flag_count'] ?> flag<?= $comment['flag_count'] != 1 ? 's' : '' ?>)</span>
                                <?php endif ?>
                                <?php if ($comment['score'] < 0) : ?>
                                    <span class="low-score">LOW SCORE</span>
                                <?php endif ?>
                            </div>
                            
                            <div class="item-content">
                                <div class="comment-text">
                                    <?= $this->e(substr(strip_tags($comment['comment']), 0, 300)) ?><?= strlen($comment['comment']) > 300 ? '...' : '' ?>
                                </div>
                            </div>
                            
                            <div class="mod-actions">
                                <a href="/mod/comments/<?= $comment['short_id'] ?>/edit" class="mod-btn edit">Edit</a>
                                <button class="mod-btn approve" data-action="approve">Approve</button>
                                <button class="mod-btn flag" data-action="flag">Flag</button>
                                <button class="mod-btn delete" data-action="delete">Delete</button>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
        <?php elseif ($type === 'comments') : ?>
            <div class="no-content">
                <p>No flagged comments at this time.</p>
            </div>
        <?php endif ?>
    <?php endif ?>
    
    <?php if (empty($flagged_stories) && empty($flagged_comments) && $type === 'all') : ?>
        <div class="no-content">
            <p>No flagged content at this time. Great job keeping the community clean!</p>
        </div>
    <?php endif ?>
</div>

<style>
.moderation-flagged {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    background: var(--color-bg);
    color: var(--color-fg);
}

.breadcrumb {
    margin-bottom: 15px;
}

.back-link {
    display: inline-flex;
    align-items: center;
    padding: 8px 12px;
    text-decoration: none;
    color: var(--color-fg-link);
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--color-fg-contrast-5);
    border-radius: 5px;
    font-size: 0.9em;
    transition: all 0.2s ease;
}

.back-link:hover {
    color: var(--color-fg-link-hover);
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--color-fg-contrast-7-5);
}

.moderation-flagged h1 {
    color: var(--color-fg);
    margin-bottom: 20px;
}

.error-message {
    background: rgba(255, 68, 68, 0.1);
    border: 1px solid var(--color-accent);
    border-radius: 5px;
    padding: 15px;
    margin-bottom: 20px;
    color: var(--color-accent);
}

.filter-nav {
    margin-bottom: 30px;
    border-bottom: 1px solid var(--color-fg-contrast-5);
}

.filter-link {
    display: inline-block;
    padding: 10px 20px;
    margin-right: 10px;
    text-decoration: none;
    color: var(--color-fg-contrast-7-5);
    border-bottom: 2px solid transparent;
    transition: all 0.2s ease;
}

.filter-link:hover {
    color: var(--color-fg);
}

.filter-link.active {
    color: var(--color-accent);
    border-bottom-color: var(--color-accent);
}

.flagged-section {
    margin-bottom: 40px;
}

.flagged-section h2 {
    color: var(--color-fg);
    margin-bottom: 20px;
    font-size: 1.5em;
}

.flagged-item {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--color-fg-contrast-5);
    border-radius: 5px;
    padding: 20px;
    margin-bottom: 20px;
    border-left: 4px solid var(--color-accent);
}

.item-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}

.item-header h3 {
    margin: 0;
    flex: 1;
    font-size: 1.1em;
    color: var(--color-fg);
}

.item-header h3 a {
    text-decoration: none;
    color: var(--color-fg-link);
}

.item-header h3 a:hover {
    color: var(--color-fg-link-hover);
}

.score {
    padding: 4px 8px;
    border-radius: 3px;
    font-weight: bold;
    font-size: 0.9em;
    white-space: nowrap;
}

.score-positive {
    background: rgba(40, 167, 69, 0.2);
    color: #4caf50;
    border: 1px solid #4caf50;
}

.score-negative {
    background: rgba(255, 68, 68, 0.2);
    color: var(--color-accent);
    border: 1px solid var(--color-accent);
}

.item-meta {
    font-size: 0.9em;
    color: var(--color-fg-contrast-7-5);
    margin-bottom: 15px;
}

.item-meta span {
    margin-right: 15px;
}

.flag-indicator {
    background: var(--color-accent);
    color: var(--color-bg);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.8em;
    font-weight: bold;
}

.low-score {
    background: rgba(255, 68, 68, 0.2);
    color: var(--color-accent);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.8em;
    font-weight: bold;
    border: 1px solid var(--color-accent);
}

.flag-reason {
    font-style: italic;
    color: var(--color-accent);
}

.item-description,
.item-content {
    margin-bottom: 15px;
    color: var(--color-fg-contrast-10);
    line-height: 1.4;
}

.comment-text {
    background: rgba(255, 255, 255, 0.03);
    padding: 10px;
    border-radius: 3px;
    border-left: 3px solid var(--color-fg-contrast-5);
    color: var(--color-fg-contrast-10);
}

.mod-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.mod-btn {
    padding: 6px 12px;
    border: none;
    border-radius: 3px;
    cursor: pointer;
    font-size: 0.85em;
    text-decoration: none;
    display: inline-block;
    transition: all 0.2s ease;
    font-family: inherit;
}

.mod-btn.edit {
    background: var(--color-fg-link);
    color: var(--color-bg);
}

.mod-btn.edit:hover {
    background: var(--color-fg-link-hover);
}

.mod-btn.approve {
    background: #4caf50;
    color: var(--color-bg);
}

.mod-btn.approve:hover {
    background: #66bb6a;
}

.mod-btn.flag {
    background: #ffa726;
    color: var(--color-bg);
}

.mod-btn.flag:hover {
    background: #ffb74d;
}

.mod-btn.delete {
    background: var(--color-accent);
    color: var(--color-bg);
}

.mod-btn.delete:hover {
    background: var(--color-accent-hover);
}

.no-content {
    text-align: center;
    color: var(--color-fg-contrast-7-5);
    font-style: italic;
    padding: 60px 20px;
    background: rgba(255, 255, 255, 0.02);
    border-radius: 5px;
    border: 1px solid var(--color-fg-contrast-5);
}

.author a {
    color: var(--color-fg-link);
    text-decoration: none;
}

.author a:hover {
    color: var(--color-fg-link-hover);
    text-decoration: underline;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle moderation actions
    document.querySelectorAll('.mod-btn[data-action]').forEach(button => {
        button.addEventListener('click', function() {
            const item = this.closest('.flagged-item');
            const type = item.dataset.type;
            const id = item.dataset.id;
            const action = this.dataset.action;
            
            let reason = null;
            if (action === 'flag' || action === 'delete') {
                reason = prompt(`Please provide a reason for ${action}ing this ${type}:`);
                if (!reason) return;
            }
            
            if (action === 'delete' && !confirm(`Are you sure you want to delete this ${type}?`)) {
                return;
            }
            
            // Show loading state
            this.textContent = 'Processing...';
            this.disabled = true;
            
            fetch(`/mod/${type}s/${id}/moderate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `action=${action}${reason ? '&reason=' + encodeURIComponent(reason) : ''}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    // Fade out and remove the item
                    item.style.opacity = '0.5';
                    item.style.transition = 'opacity 0.5s';
                    setTimeout(() => item.remove(), 500);
                } else {
                    alert('Error: ' + (data.error || 'Action failed'));
                    // Reset button
                    this.textContent = action.charAt(0).toUpperCase() + action.slice(1);
                    this.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while processing the request');
                // Reset button
                this.textContent = action.charAt(0).toUpperCase() + action.slice(1);
                this.disabled = false;
            });
        });
    });
});
</script>