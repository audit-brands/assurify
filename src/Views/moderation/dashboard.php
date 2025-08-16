<?php $this->layout('layout', ['title' => $title]) ?>

<div class="moderation-dashboard">
    <h1>Moderation Dashboard</h1>
    
    <div class="mod-stats">
        <h2>Statistics</h2>
        <div class="stats-grid">
            <div class="stat-box">
                <span class="stat-number"><?= number_format($stats['total_stories']) ?></span>
                <span class="stat-label">Total Stories</span>
            </div>
            <div class="stat-box">
                <span class="stat-number"><?= number_format($stats['total_comments']) ?></span>
                <span class="stat-label">Total Comments</span>
            </div>
            <div class="stat-box">
                <span class="stat-number"><?= number_format($stats['flagged_stories']) ?></span>
                <span class="stat-label">Flagged Stories</span>
            </div>
            <div class="stat-box">
                <span class="stat-number"><?= number_format($stats['flagged_comments']) ?></span>
                <span class="stat-label">Flagged Comments</span>
            </div>
            <div class="stat-box">
                <span class="stat-number"><?= number_format($stats['low_score_stories']) ?></span>
                <span class="stat-label">Low Score Stories</span>
            </div>
            <div class="stat-box">
                <span class="stat-number"><?= number_format($stats['low_score_comments']) ?></span>
                <span class="stat-label">Low Score Comments</span>
            </div>
            <div class="stat-box">
                <span class="stat-number"><?= number_format($stats['total_users']) ?></span>
                <span class="stat-label">Total Users</span>
            </div>
            <div class="stat-box">
                <span class="stat-number"><?= number_format($stats['banned_users']) ?></span>
                <span class="stat-label">Banned Users</span>
            </div>
        </div>
    </div>
    
    <div class="mod-navigation">
        <h2>Quick Actions</h2>
        <div class="nav-links">
            <a href="/mod/flagged" class="mod-link">View Flagged Content</a>
            <a href="/moderations" class="mod-link">Moderation Log</a>
            <a href="/users" class="mod-link">User Management</a>
        </div>
    </div>
    
    <div class="recent-flagged">
        <h2>Recent Flagged Content</h2>
        
        <?php if (!empty($flagged_stories)) : ?>
            <h3>Flagged Stories</h3>
            <div class="flagged-list">
                <?php foreach (array_slice($flagged_stories, 0, 5) as $story) : ?>
                    <div class="flagged-item" data-type="story" data-id="<?= $story['id'] ?>">
                        <div class="item-header">
                            <h4><a href="/s/<?= $this->e($story['short_id']) ?>"><?= $this->e($story['title']) ?></a></h4>
                            <span class="score score-<?= $story['score'] < 0 ? 'negative' : 'positive' ?>"><?= $story['score'] ?></span>
                        </div>
                        <div class="item-meta">
                            by <a href="/u/<?= $this->e($story['user']) ?>"><?= $this->e($story['user']) ?></a>
                            <span class="time"><?= $story['time_ago'] ?></span>
                            <?php if ($story['is_flagged']) : ?>
                                <span class="flag-indicator">FLAGGED</span>
                                <?php if ($story['flag_reason']) : ?>
                                    <span class="flag-reason"><?= $this->e($story['flag_reason']) ?></span>
                                <?php endif ?>
                            <?php endif ?>
                        </div>
                        <div class="mod-actions">
                            <button class="mod-btn approve" data-action="approve">Approve</button>
                            <button class="mod-btn flag" data-action="flag">Flag</button>
                            <button class="mod-btn delete" data-action="delete">Delete</button>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endif ?>
        
        <?php if (!empty($flagged_comments)) : ?>
            <h3>Flagged Comments</h3>
            <div class="flagged-list">
                <?php foreach (array_slice($flagged_comments, 0, 5) as $comment) : ?>
                    <div class="flagged-item" data-type="comment" data-id="<?= $comment['id'] ?>">
                        <div class="item-header">
                            <h4>
                                <a href="/s/<?= $this->e($comment['story']['short_id']) ?>#comment-<?= $this->e($comment['short_id']) ?>">
                                    Comment on: <?= $this->e($comment['story']['title']) ?>
                                </a>
                            </h4>
                            <span class="score score-<?= $comment['score'] < 0 ? 'negative' : 'positive' ?>"><?= $comment['score'] ?></span>
                        </div>
                        <div class="item-meta">
                            by <a href="/u/<?= $this->e($comment['user']) ?>"><?= $this->e($comment['user']) ?></a>
                            <span class="time"><?= $comment['time_ago'] ?></span>
                            <?php if ($comment['is_flagged']) : ?>
                                <span class="flag-indicator">FLAGGED (<?= $comment['flag_count'] ?> flag<?= $comment['flag_count'] != 1 ? 's' : '' ?>)</span>
                            <?php endif ?>
                        </div>
                        <div class="item-excerpt">
                            <?= $this->e(substr(strip_tags($comment['comment']), 0, 200)) ?><?= strlen($comment['comment']) > 200 ? '...' : '' ?>
                        </div>
                        <div class="mod-actions">
                            <button class="mod-btn approve" data-action="approve">Approve</button>
                            <button class="mod-btn flag" data-action="flag">Flag</button>
                            <button class="mod-btn delete" data-action="delete">Delete</button>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endif ?>
        
        <?php if (empty($flagged_stories) && empty($flagged_comments)) : ?>
            <p class="no-content">No flagged content at this time.</p>
        <?php endif ?>
    </div>
</div>

<style>
.moderation-dashboard {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    background: var(--color-bg);
    color: var(--color-fg);
}

.moderation-dashboard h1,
.moderation-dashboard h2,
.moderation-dashboard h3 {
    color: var(--color-fg);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.stat-box {
    background: rgba(255, 255, 255, 0.05);
    padding: 20px;
    text-align: center;
    border-radius: 5px;
    border: 1px solid var(--color-fg-contrast-5);
    transition: all 0.2s ease;
}

.stat-box:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--color-fg-contrast-7-5);
}

.stat-number {
    display: block;
    font-size: 24px;
    font-weight: bold;
    color: var(--color-fg);
}

.stat-label {
    display: block;
    font-size: 14px;
    color: var(--color-fg-contrast-7-5);
    margin-top: 5px;
}

.nav-links {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.mod-link {
    background-color: var(--color-accent);
    border: 1px solid var(--color-accent-dark);
    color: white;
    padding: 8px 16px;
    border-radius: 4px;
    text-decoration: none;
    display: inline-block;
    font-weight: bold;
    margin: 0 0.25rem;
}

.mod-link:hover {
    background-color: var(--color-accent-hover);
    text-decoration: none;
}

.flagged-item {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--color-fg-contrast-5);
    border-radius: 5px;
    padding: 15px;
    margin-bottom: 15px;
    border-left: 4px solid var(--color-accent);
}

.item-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}

.item-header h4 {
    margin: 0;
    flex: 1;
    color: var(--color-fg);
}

.item-header h4 a {
    color: var(--color-fg-link);
    text-decoration: none;
}

.item-header h4 a:hover {
    color: var(--color-fg-link-hover);
    text-decoration: underline;
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
    font-size: 14px;
    color: var(--color-fg-contrast-7-5);
    margin-bottom: 10px;
}

.item-meta a {
    color: var(--color-fg-link);
    text-decoration: none;
}

.item-meta a:hover {
    color: var(--color-fg-link-hover);
    text-decoration: underline;
}

.flag-indicator {
    background: var(--color-accent);
    color: var(--color-bg);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.8em;
    font-weight: bold;
}

.flag-reason {
    font-style: italic;
    margin-left: 10px;
    color: var(--color-accent);
}

.item-excerpt {
    margin-bottom: 15px;
    color: var(--color-fg-contrast-10);
    line-height: 1.4;
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
    transition: all 0.2s ease;
    font-family: inherit;
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
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle moderation actions
    document.querySelectorAll('.mod-btn').forEach(button => {
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
                    item.style.opacity = '0.5';
                    setTimeout(() => item.remove(), 500);
                } else {
                    alert('Error: ' + (data.error || 'Action failed'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
            });
        });
    });
});
</script>