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
            <a href="/moderation/flagged" class="mod-link">View Flagged Content</a>
            <a href="/moderation/log" class="mod-link">Moderation Log</a>
            <a href="/moderation/users" class="mod-link">User Management</a>
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
                                <span class="flag-indicator">FLAGGED</span>
                                <?php if ($comment['flag_reason']) : ?>
                                    <span class="flag-reason"><?= $this->e($comment['flag_reason']) ?></span>
                                <?php endif ?>
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
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.stat-box {
    background: #f5f5f5;
    padding: 20px;
    text-align: center;
    border-radius: 5px;
    border: 1px solid #ddd;
}

.stat-number {
    display: block;
    font-size: 24px;
    font-weight: bold;
    color: #333;
}

.stat-label {
    display: block;
    font-size: 14px;
    color: #666;
    margin-top: 5px;
}

.nav-links {
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
}

.mod-link {
    padding: 10px 20px;
    background: #007cba;
    color: white;
    text-decoration: none;
    border-radius: 5px;
}

.mod-link:hover {
    background: #005a87;
}

.flagged-item {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 15px;
    margin-bottom: 15px;
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
}

.score {
    padding: 2px 8px;
    border-radius: 3px;
    font-weight: bold;
    font-size: 12px;
}

.score-positive {
    background: #d4edda;
    color: #155724;
}

.score-negative {
    background: #f8d7da;
    color: #721c24;
}

.item-meta {
    font-size: 14px;
    color: #666;
    margin-bottom: 10px;
}

.flag-indicator {
    background: #dc3545;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
}

.flag-reason {
    font-style: italic;
    margin-left: 10px;
}

.item-excerpt {
    margin-bottom: 15px;
    color: #555;
}

.mod-actions {
    display: flex;
    gap: 10px;
}

.mod-btn {
    padding: 5px 15px;
    border: none;
    border-radius: 3px;
    cursor: pointer;
    font-size: 12px;
}

.mod-btn.approve {
    background: #28a745;
    color: white;
}

.mod-btn.flag {
    background: #ffc107;
    color: #212529;
}

.mod-btn.delete {
    background: #dc3545;
    color: white;
}

.mod-btn:hover {
    opacity: 0.8;
}

.no-content {
    text-align: center;
    color: #666;
    font-style: italic;
    padding: 40px;
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
            
            fetch(`/moderation/${type}s/${id}`, {
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