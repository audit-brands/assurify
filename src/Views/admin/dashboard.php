<?php $this->layout('layout', ['title' => $title]) ?>

<div class="admin-dashboard">
    <h1>Admin Dashboard</h1>
    
    <div class="admin-nav">
        <nav class="admin-navigation">
            <a href="/admin" class="nav-link active">Dashboard</a>
            <a href="/admin/performance" class="nav-link">Performance</a>
            <a href="/admin/cache" class="nav-link">Cache</a>
            <a href="/admin/users" class="nav-link">Users</a>
            <a href="/admin/logs" class="nav-link">Logs</a>
            <a href="/admin/settings" class="nav-link">Settings</a>
        </nav>
    </div>

    <!-- System Health Overview -->
    <div class="health-overview">
        <h2>System Health</h2>
        <div class="health-status status-<?= $overview['health_checks']['overall'] ?>">
            <span class="status-indicator"></span>
            <span class="status-text"><?= ucfirst($overview['health_checks']['overall']) ?></span>
            <span class="last-check">Last check: <?= date('H:i:s', $overview['health_checks']['last_check']) ?></span>
        </div>
        
        <div class="health-checks">
            <?php foreach ($overview['health_checks']['checks'] as $name => $check) : ?>
                <div class="health-check status-<?= $check['status'] ?>">
                    <span class="check-name"><?= ucfirst(str_replace('_', ' ', $name)) ?></span>
                    <span class="check-status"><?= $check['status'] ?></span>
                    <span class="check-message"><?= $check['message'] ?></span>
                </div>
            <?php endforeach ?>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="quick-stats">
        <h2>Quick Stats</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($overview['application_stats']['total_users']) ?></div>
                <div class="stat-label">Total Users</div>
                <div class="stat-change">+<?= number_format($overview['application_stats']['active_users_24h']) ?> active (24h)</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= number_format($overview['application_stats']['total_stories']) ?></div>
                <div class="stat-label">Total Stories</div>
                <div class="stat-change">+<?= number_format($overview['application_stats']['stories_today']) ?> today</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= number_format($overview['application_stats']['total_comments']) ?></div>
                <div class="stat-label">Total Comments</div>
                <div class="stat-change">+<?= number_format($overview['application_stats']['comments_today']) ?> today</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $overview['performance_metrics']['memory']['usage_percentage'] ?>%</div>
                <div class="stat-label">Memory Usage</div>
                <div class="stat-change"><?= $overview['performance_metrics']['memory']['current_usage_formatted'] ?> used</div>
            </div>
        </div>
    </div>

    <!-- System Information -->
    <div class="system-info">
        <h2>System Information</h2>
        <div class="info-grid">
            <div class="info-section">
                <h3>Server</h3>
                <ul>
                    <li><strong>PHP Version:</strong> <?= $overview['system_info']['php_version'] ?></li>
                    <li><strong>Server:</strong> <?= $overview['system_info']['server_software'] ?? 'Unknown' ?></li>
                    <li><strong>OS:</strong> <?= $overview['system_info']['os'] ?></li>
                    <li><strong>Uptime:</strong> <?= gmdate('H:i:s', $overview['system_info']['uptime']) ?></li>
                </ul>
            </div>
            
            <div class="info-section">
                <h3>Database</h3>
                <ul>
                    <li><strong>Status:</strong> <?= $overview['health_checks']['checks']['database']['status'] ?></li>
                    <li><strong>Total Queries:</strong> <?= number_format($overview['performance_metrics']['database']['total_queries']) ?></li>
                    <li><strong>Avg Query Time:</strong> <?= number_format($overview['performance_metrics']['database']['average_query_time'] * 1000, 2) ?>ms</li>
                    <li><strong>Size:</strong> <?= $overview['application_stats']['database_size'] ?></li>
                </ul>
            </div>
            
            <div class="info-section">
                <h3>Cache</h3>
                <ul>
                    <li><strong>Status:</strong> <?= $overview['health_checks']['checks']['cache']['status'] ?></li>
                    <li><strong>Hit Rate:</strong> <?= number_format($overview['performance_metrics']['cache']['hit_rate'], 2) ?>%</li>
                    <li><strong>Hits:</strong> <?= number_format($overview['performance_metrics']['cache']['hits']) ?></li>
                    <li><strong>Misses:</strong> <?= number_format($overview['performance_metrics']['cache']['misses']) ?></li>
                </ul>
            </div>
            
            <div class="info-section">
                <h3>Performance</h3>
                <ul>
                    <li><strong>Avg Response:</strong> <?= number_format($overview['performance_metrics']['http']['average_response_time'] * 1000, 2) ?>ms</li>
                    <li><strong>Total Requests:</strong> <?= number_format($overview['performance_metrics']['http']['total_requests']) ?></li>
                    <li><strong>Memory Peak:</strong> <?= $overview['performance_metrics']['memory']['peak_usage_formatted'] ?></li>
                    <li><strong>Disk Usage:</strong> <?= number_format($overview['system_info']['disk_space']['usage_percentage'], 1) ?>%</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="recent-activity">
        <h2>Recent Activity</h2>
        <div class="activity-sections">
            <div class="activity-section">
                <h3>Recent Stories</h3>
                <ul class="activity-list">
                    <?php foreach ($overview['recent_activity']['recent_stories'] as $story) : ?>
                        <li>
                            <strong><?= $this->e($story['title']) ?></strong>
                            <span class="activity-meta">by <?= $this->e($story['user']) ?> - <?= $story['score'] ?> points</span>
                            <span class="activity-time"><?= date('H:i', strtotime($story['created_at'])) ?></span>
                        </li>
                    <?php endforeach ?>
                </ul>
            </div>
            
            <div class="activity-section">
                <h3>Recent Comments</h3>
                <ul class="activity-list">
                    <?php foreach ($overview['recent_activity']['recent_comments'] as $comment) : ?>
                        <li>
                            <strong><?= $this->e($comment['comment']) ?></strong>
                            <span class="activity-meta">by <?= $this->e($comment['user']) ?> on <?= $this->e($comment['story_title']) ?></span>
                            <span class="activity-time"><?= date('H:i', strtotime($comment['created_at'])) ?></span>
                        </li>
                    <?php endforeach ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <h2>Quick Actions</h2>
        <div class="action-buttons">
            <button class="action-btn" onclick="flushCache()">Flush Cache</button>
            <button class="action-btn" onclick="cleanupSystem()">Cleanup System</button>
            <button class="action-btn" onclick="exportData()">Export Data</button>
            <button class="action-btn" onclick="refreshHealth()">Refresh Health</button>
        </div>
    </div>
</div>

<style>
.admin-dashboard {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.admin-navigation {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 30px;
}

.nav-link {
    display: inline-block;
    padding: 10px 20px;
    margin-right: 10px;
    text-decoration: none;
    color: #333;
    border-radius: 3px;
}

.nav-link.active,
.nav-link:hover {
    background: #007cba;
    color: white;
}

.health-overview {
    background: white;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 20px;
    margin-bottom: 30px;
}

.health-status {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
    font-size: 18px;
    font-weight: bold;
}

.status-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: 10px;
}

.status-healthy .status-indicator { background: #28a745; }
.status-warning .status-indicator { background: #ffc107; }
.status-error .status-indicator { background: #dc3545; }

.health-checks {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 15px;
}

.health-check {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 15px;
    border-radius: 3px;
    border-left: 4px solid;
}

.status-healthy { border-left-color: #28a745; background: #d4edda; }
.status-warning { border-left-color: #ffc107; background: #fff3cd; }
.status-error { border-left-color: #dc3545; background: #f8d7da; }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 20px;
    text-align: center;
}

.stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #007cba;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 14px;
    color: #666;
    margin-bottom: 5px;
}

.stat-change {
    font-size: 12px;
    color: #28a745;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.info-section {
    background: white;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 20px;
}

.info-section h3 {
    margin: 0 0 15px 0;
    color: #333;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.info-section ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.info-section li {
    padding: 5px 0;
    border-bottom: 1px solid #f8f9fa;
}

.activity-sections {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 30px;
}

.activity-section {
    background: white;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 20px;
}

.activity-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.activity-list li {
    padding: 10px 0;
    border-bottom: 1px solid #f8f9fa;
    display: flex;
    flex-direction: column;
}

.activity-meta {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}

.activity-time {
    font-size: 11px;
    color: #999;
    margin-top: 5px;
}

.action-buttons {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.action-btn {
    padding: 10px 20px;
    background: #007cba;
    color: white;
    border: none;
    border-radius: 3px;
    cursor: pointer;
    font-size: 14px;
}

.action-btn:hover {
    background: #005a87;
}

.last-check {
    margin-left: auto;
    font-size: 12px;
    color: #666;
    font-weight: normal;
}
</style>

<script>
function flushCache() {
    if (confirm('Are you sure you want to flush the cache?')) {
        fetch('/admin/flush-cache', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Cache flushed successfully');
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Failed to flush cache'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
            });
    }
}

function cleanupSystem() {
    if (confirm('Are you sure you want to run system cleanup?')) {
        fetch('/admin/cleanup', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('System cleanup completed: ' + JSON.stringify(data.results));
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Cleanup failed'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred');
            });
    }
}

function exportData() {
    window.location.href = '/admin/export';
}

function refreshHealth() {
    location.reload();
}

// Auto-refresh every 30 seconds
setInterval(function() {
    location.reload();
}, 30000);
</script>