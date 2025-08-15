<?php $this->layout('layout', ['title' => $title]) ?>

<div class="moderation-log-page">
    <h1>Moderation Log</h1>
    
    <p>This page shows all moderation actions taken by community moderators and administrators. This transparency helps ensure fair and consistent enforcement of community guidelines.</p>
    
    <?php if (!empty($moderations)): ?>
        <div class="moderation-stats">
            <p><strong><?= number_format($total_count) ?></strong> total moderation actions</p>
            <?php if ($total_pages > 1): ?>
                <p>Page <?= $current_page ?> of <?= $total_pages ?></p>
            <?php endif ?>
        </div>
        
        <div class="moderation-entries">
            <?php foreach ($moderations as $moderation): ?>
                <div class="moderation-entry">
                    <div class="moderation-header">
                        <span class="moderation-time"><?= $this->e($moderation['time_ago']) ?></span>
                        <span class="moderation-moderator">by <?= $this->e($moderation['moderator']) ?></span>
                    </div>
                    <div class="moderation-action">
                        <span class="action-type"><?= $this->e($moderation['action']) ?></span>
                        <span class="target-type"><?= $this->e($moderation['target_type']) ?></span>
                        <a href="<?= $this->e($moderation['target_url']) ?>"><?= $this->e($moderation['target_title']) ?></a>
                    </div>
                    <?php if (!empty($moderation['reason'])): ?>
                        <div class="moderation-reason">
                            <strong>Reason:</strong> <?= $this->e($moderation['reason']) ?>
                        </div>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($current_page > 1): ?>
                    <a href="/moderation-log?page=<?= $current_page - 1 ?>" class="pagination-link">← Previous</a>
                <?php endif ?>
                
                <?php for ($i = max(1, $current_page - 5); $i <= min($total_pages, $current_page + 5); $i++): ?>
                    <?php if ($i == $current_page): ?>
                        <span class="pagination-current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="/moderation-log?page=<?= $i ?>" class="pagination-link"><?= $i ?></a>
                    <?php endif ?>
                <?php endfor ?>
                
                <?php if ($current_page < $total_pages): ?>
                    <a href="/moderation-log?page=<?= $current_page + 1 ?>" class="pagination-link">Next →</a>
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