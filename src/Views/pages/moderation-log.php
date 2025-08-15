<?php $this->layout('layout', ['title' => $title]) ?>

<div class="moderation-log-page">
    <h1>Moderation Log</h1>
    
    <p>This page shows all moderation actions taken by community moderators and administrators. This transparency helps ensure fair and consistent enforcement of community guidelines.</p>
    
    <!-- Filters (like Lobste.rs) -->
    <div class="moderation-filters">
        <form method="GET" action="/moderations" class="filter-form">
            <div class="filter-row">
                <label for="moderator">Moderator:</label>
                <select name="moderator" id="moderator">
                    <option value="">All moderators</option>
                    <?php foreach ($available_moderators as $mod): ?>
                        <option value="<?= $this->e($mod) ?>" <?= $filters['moderator'] === $mod ? 'selected' : '' ?>>
                            <?= $this->e($mod) ?>
                        </option>
                    <?php endforeach ?>
                </select>
                
                <label for="action">Action:</label>
                <select name="action" id="action">
                    <option value="">All actions</option>
                    <?php foreach ($available_actions as $act): ?>
                        <option value="<?= $this->e($act) ?>" <?= $filters['action'] === $act ? 'selected' : '' ?>>
                            <?= $this->e($act) ?>
                        </option>
                    <?php endforeach ?>
                </select>
                
                <label for="subject_type">Type:</label>
                <select name="subject_type" id="subject_type">
                    <option value="">All types</option>
                    <?php foreach ($available_subject_types as $type): ?>
                        <option value="<?= $this->e($type) ?>" <?= $filters['subject_type'] === $type ? 'selected' : '' ?>>
                            <?= $this->e(ucfirst($type)) ?>
                        </option>
                    <?php endforeach ?>
                </select>
                
                <button type="submit">Filter</button>
                <a href="/moderations" class="clear-filters">Clear</a>
            </div>
        </form>
    </div>
    
    <?php if (!empty($moderations)): ?>
        <div class="moderation-stats">
            <p>Page <?= $page ?> of <?= $total_pages ?></p>
        </div>
        
        <div class="moderation-entries">
            <?php foreach ($moderations as $moderation): ?>
                <div class="moderation-entry">
                    <div class="moderation-header">
                        <span class="moderation-time"><?= $moderation->created_at->format('Y-m-d H:i') ?></span>
                        <span class="moderation-moderator">by 
                            <a href="/~<?= $this->e($moderation->moderator->username) ?>">
                                <?= $this->e($moderation->moderator->username) ?>
                            </a>
                        </span>
                    </div>
                    <div class="moderation-action">
                        <span class="action-type"><?= $this->e($moderation->action) ?></span>
                        <?php if ($moderation->subject_link): ?>
                            <a href="<?= $this->e($moderation->subject_link) ?>" class="target-link">
                                <?= $this->e($moderation->subject_title ?: ($moderation->subject_type . ' #' . $moderation->subject_id)) ?>
                            </a>
                        <?php else: ?>
                            <span class="target-title">
                                <?= $this->e($moderation->subject_title ?: ($moderation->subject_type . ' #' . $moderation->subject_id)) ?>
                            </span>
                        <?php endif ?>
                    </div>
                    <?php if ($moderation->reason): ?>
                        <div class="moderation-reason">
                            <strong>Reason:</strong> <?= $this->e($moderation->reason) ?>
                        </div>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($has_prev): ?>
                    <a href="/moderations?page=<?= $page - 1 ?><?= $filters['moderator'] ? '&moderator=' . urlencode($filters['moderator']) : '' ?><?= $filters['action'] ? '&action=' . urlencode($filters['action']) : '' ?><?= $filters['subject_type'] ? '&subject_type=' . urlencode($filters['subject_type']) : '' ?>" class="pagination-link">← Previous</a>
                <?php endif ?>
                
                <?php for ($i = max(1, $page - 5); $i <= min($total_pages, $page + 5); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="pagination-current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="/moderations?page=<?= $i ?><?= $filters['moderator'] ? '&moderator=' . urlencode($filters['moderator']) : '' ?><?= $filters['action'] ? '&action=' . urlencode($filters['action']) : '' ?><?= $filters['subject_type'] ? '&subject_type=' . urlencode($filters['subject_type']) : '' ?>" class="pagination-link"><?= $i ?></a>
                    <?php endif ?>
                <?php endfor ?>
                
                <?php if ($has_next): ?>
                    <a href="/moderations?page=<?= $page + 1 ?><?= $filters['moderator'] ? '&moderator=' . urlencode($filters['moderator']) : '' ?><?= $filters['action'] ? '&action=' . urlencode($filters['action']) : '' ?><?= $filters['subject_type'] ? '&subject_type=' . urlencode($filters['subject_type']) : '' ?>" class="pagination-link">Next →</a>
                <?php endif ?>
            </div>
        <?php endif ?>
        
    <?php else: ?>
        <div class="empty-state">
            <p>No moderation actions have been logged yet.</p>
            <p>When moderators take actions such as editing story titles, removing inappropriate content, or managing user accounts, those actions will be displayed here for community transparency.</p>
        </div>
    <?php endif ?>
    
    <div class="moderation-info">
        <h2>About Moderation</h2>
        
        <p>Community moderation is performed by volunteer moderators and administrators who help maintain the quality of discussions and ensure adherence to community guidelines.</p>
        
        <h3>Common Moderation Actions</h3>
        <ul>
            <li><strong>Edit Title:</strong> Clarifying or correcting story titles for better understanding</li>
            <li><strong>Add/Remove Tags:</strong> Improving content categorization</li>
            <li><strong>Delete Story:</strong> Removing content that violates community guidelines</li>
            <li><strong>Delete Comment:</strong> Removing inappropriate or off-topic comments</li>
            <li><strong>Flag Content:</strong> Marking content for review</li>
            <li><strong>User Actions:</strong> Account warnings, suspensions, or privilege changes</li>
        </ul>
        
        <h3>Transparency Policy</h3>
        <p>All moderation actions are logged and made publicly visible to ensure accountability and consistency. This helps the community understand what standards are being enforced and provides a record of moderation decisions.</p>
        
        <p>If you have questions or concerns about moderation actions, please contact the moderation team through the appropriate channels.</p>
    </div>
</div>

<style>
.moderation-log-page {
    max-width: 1000px;
    margin: 0 auto;
    padding: 1em;
    background: var(--color-bg, black);
    color: var(--color-fg);
}

.moderation-filters {
    background: var(--color-bg, black);
    border: 1px solid var(--color-border, #555555);
    color: var(--color-fg);
    padding: 1em;
    margin-bottom: 1em;
}

.filter-row {
    display: flex;
    align-items: center;
    gap: 1em;
    flex-wrap: wrap;
}

.filter-row label {
    font-weight: bold;
    margin: 0;
    color: var(--color-fg);
}

.filter-row select {
    padding: 0.3em;
    border: 1px solid var(--color-border, #555555);
    background: var(--color-bg, black);
    color: var(--color-fg);
    font-family: inherit;
}

.filter-row button {
    background: var(--color-accent, #ff4444);
    color: white;
    border: none;
    padding: 0.4em 0.8em;
    cursor: pointer;
    font-family: inherit;
}

.filter-row button:hover {
    background: var(--color-accent-hover, #ff6666);
}

.clear-filters {
    color: var(--color-fg-contrast-7-5, #999);
    text-decoration: none;
    padding: 0.4em 0.8em;
}

.clear-filters:hover {
    color: var(--color-fg-link, #4a9eff);
    text-decoration: underline;
}

.moderation-stats {
    margin: 1em 0;
    padding: 0.5em 1em;
    background: var(--color-bg, black);
    border: 1px solid var(--color-border, #555555);
    color: var(--color-fg-contrast-4-5, #999);
}

.moderation-entries {
    border: 1px solid var(--color-border, #555555);
}

.moderation-entry {
    border-bottom: 1px solid var(--color-border, #555555);
    padding: 1em;
    background: var(--color-bg, black);
    color: var(--color-fg);
}

.moderation-entry:last-child {
    border-bottom: none;
}

.moderation-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9em;
    color: var(--color-fg-contrast-4-5, #999);
    margin-bottom: 0.5em;
}

.moderation-action {
    font-weight: normal;
    margin-bottom: 0.5em;
    color: var(--color-fg);
}

.action-type {
    font-weight: bold;
    color: var(--color-accent, #ff4444);
}

.target-link {
    color: var(--color-fg-link, #4a9eff);
    text-decoration: none;
}

.target-link:hover {
    color: var(--color-fg-link-hover, #6bb6ff);
    text-decoration: underline;
}

.target-title {
    color: var(--color-fg);
}

.moderation-reason {
    font-size: 0.9em;
    color: var(--color-fg-contrast-4-5, #999);
    font-style: italic;
}

.pagination {
    margin: 2em 0;
    text-align: center;
}

.pagination-link,
.pagination-current {
    display: inline-block;
    padding: 0.5em 0.8em;
    margin: 0 0.2em;
    text-decoration: none;
    border: 1px solid var(--color-border, #555555);
    background: var(--color-bg, black);
    color: var(--color-fg);
}

.pagination-link {
    color: var(--color-fg-link, #4a9eff);
}

.pagination-link:hover {
    background: var(--color-tag-bg, #333333);
    color: var(--color-fg-link-hover, #6bb6ff);
}

.pagination-current {
    background: var(--color-accent, #ff4444);
    color: white;
    border-color: var(--color-accent, #ff4444);
}

.empty-state {
    text-align: center;
    padding: 3em 1em;
    color: var(--color-fg-contrast-4-5, #999);
    background: var(--color-bg, black);
    border: 1px solid var(--color-border, #555555);
}

.moderation-info {
    margin-top: 3em;
    padding: 2em 1em;
    border: 1px solid var(--color-border, #555555);
    background: var(--color-bg, black);
    color: var(--color-fg);
}

.moderation-info h2 {
    color: var(--color-fg);
    margin-bottom: 1em;
}

.moderation-info h3 {
    color: var(--color-fg-contrast-10, #ccc);
    margin: 1.5em 0 0.5em 0;
}

.moderation-info ul {
    margin: 0.5em 0 1em 1.5em;
}

.moderation-info li {
    margin: 0.3em 0;
    color: var(--color-fg);
}

@media (max-width: 768px) {
    .filter-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-row > * {
        margin-bottom: 0.5em;
    }
    
    .moderation-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.3em;
    }
}
</style>