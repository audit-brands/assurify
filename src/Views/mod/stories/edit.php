<?php $this->layout('layout', ['title' => $title]) ?>

<div class="mod-page">
    <h1>Edit Story (Moderation)</h1>
    
    <div class="story-info">
        <p><strong>Story:</strong> <a href="/s/<?= $this->e($story->short_id) ?>" target="_blank"><?= $this->e($story->title) ?></a></p>
        <p><strong>Author:</strong> <a href="/~<?= $this->e($story->user->username) ?>"><?= $this->e($story->user->username) ?></a></p>
        <p><strong>Posted:</strong> <?= $story->created_at->format('Y-m-d H:i:s') ?></p>
        <?php if ($story->is_deleted): ?>
            <p><strong>Status:</strong> <span class="deleted-status">DELETED</span></p>
        <?php endif ?>
    </div>

    <form id="mod-story-form" class="mod-form">
        <div class="form-group">
            <label for="title">Title:</label>
            <input type="text" id="title" name="title" value="<?= $this->e($story->title) ?>" required maxlength="200">
        </div>

        <div class="form-group">
            <label for="url">URL:</label>
            <input type="url" id="url" name="url" value="<?= $this->e($story->url ?? '') ?>" maxlength="500">
        </div>

        <div class="form-group">
            <label for="description">Description:</label>
            <textarea id="description" name="description" rows="6" maxlength="2000"><?= $this->e($story->description ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label for="tags" class="tags-toggle" style="cursor: pointer; user-select: none;">
                <span class="toggle-arrow">▶</span> Tags:
            </label>
            <div class="tags-input" id="tags-section" style="display: none;">
                <?php foreach ($all_tags as $tag): ?>
                    <label class="tag-checkbox">
                        <input type="checkbox" name="tags[]" value="<?= $this->e($tag->tag) ?>" 
                               <?= in_array($tag->tag, $story->tags->pluck('tag')->toArray()) ? 'checked' : '' ?>>
                        <?= $this->e($tag->tag) ?>
                    </label>
                <?php endforeach ?>
            </div>
        </div>

        <div class="form-group">
            <label for="merge_story_short_id">Merge Into Story (Short ID):</label>
            <input type="text" id="merge_story_short_id" name="merge_story_short_id" 
                   value="<?= $story->merged_into_story_id ? $this->e($story->mergedIntoStory->short_id ?? '') : '' ?>"
                   placeholder="e.g., abc123" maxlength="10">
            <small>Enter the short ID of the story to merge this into. This will mark this story as deleted.</small>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" id="is_unavailable" name="is_unavailable" <?= $story->is_unavailable ? 'checked' : '' ?>>
                Source URL is unavailable
            </label>
            <small>Check if the original URL is no longer accessible</small>
        </div>

        <div class="form-group">
            <label for="moderation_reason">Moderation Reason:</label>
            <input type="text" id="moderation_reason" name="moderation_reason" 
                   value="<?= $this->e($story->moderation_reason ?? '') ?>" maxlength="200"
                   placeholder="Brief explanation of changes or action taken">
        </div>

        <div class="form-actions">
            <button type="submit" class="save-btn">Save Changes</button>
            <a href="/s/<?= $this->e($story->short_id) ?>" class="cancel-btn">Cancel</a>
            
            <?php if ($story->is_deleted): ?>
                <button type="button" class="undelete-btn" data-story-id="<?= $story->short_id ?>">Undelete Story</button>
            <?php else: ?>
                <button type="button" class="delete-btn" data-story-id="<?= $story->short_id ?>">Delete Story</button>
            <?php endif ?>
        </div>
    </form>

    <!-- Moderation History -->
    <div class="moderation-history">
        <h2>Moderation History</h2>
        <?php if (empty($moderations)): ?>
            <p>No moderation actions recorded for this story.</p>
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

.story-info {
    background: var(--color-bg, black);
    border: 1px solid var(--color-border, #555555);
    color: var(--color-fg);
    padding: 1em;
    margin-bottom: 1em;
}

.story-info p {
    margin: 0.5em 0;
}

.deleted-status {
    color: #dc3545;
    font-weight: bold;
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
.form-group input[type="url"],
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
    min-height: 100px;
}

.form-group small {
    display: block;
    color: var(--color-muted, #999);
    font-size: 0.9em;
    margin-top: 0.3em;
}

.tags-input {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 0.5em;
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid var(--color-border, #555555);
    padding: 0.5em;
    background: var(--color-bg, black);
}

.tag-checkbox {
    display: flex;
    align-items: center;
    font-weight: normal !important;
    font-size: 0.9em;
    color: var(--color-fg);
    background: var(--color-bg, black);
    padding: 0.2em 0.4em;
    border: 1px solid var(--color-border, #555555);
    margin: 0.1em;
}

.tag-checkbox:hover {
    background: var(--color-tag-bg, #333333);
    border-color: var(--color-tag-border, #777777);
}

.tag-checkbox input {
    margin-right: 0.3em;
    width: auto !important;
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

.toggle-arrow {
    display: inline-block;
    transition: transform 0.2s ease;
    font-size: 0.8em;
    margin-right: 0.3em;
}

.toggle-arrow.expanded {
    transform: rotate(90deg);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle tags section toggle
    document.querySelector('.tags-toggle').addEventListener('click', function() {
        const arrow = this.querySelector('.toggle-arrow');
        const tagsSection = document.getElementById('tags-section');
        
        if (tagsSection.style.display === 'none') {
            tagsSection.style.display = 'grid';
            arrow.classList.add('expanded');
        } else {
            tagsSection.style.display = 'none';
            arrow.classList.remove('expanded');
        }
    });

    // Handle form submission
    document.getElementById('mod-story-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = {};
        
        // Convert FormData to regular object
        for (const [key, value] of formData.entries()) {
            if (key === 'tags[]') {
                if (!data.tags) data.tags = [];
                data.tags.push(value);
            } else {
                data[key] = value;
            }
        }
        
        // Handle checkboxes that aren't checked
        data.is_unavailable = formData.has('is_unavailable');
        
        const submitBtn = this.querySelector('.save-btn');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Saving...';
        submitBtn.disabled = true;
        
        fetch('/mod/stories/<?= $story->short_id ?>/update', {
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
        const storyId = this.dataset.storyId;
        const reason = prompt('Reason for deletion (required for other users\' stories):');
        
        if (reason === null) return; // User cancelled
        
        if (confirm('Are you sure you want to delete this story?')) {
            fetch(`/mod/stories/${storyId}/delete`, {
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
                    alert('Error: ' + (data.error || 'Failed to delete story'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to delete story');
            });
        }
    });
    
    // Handle undelete button
    document.querySelector('.undelete-btn')?.addEventListener('click', function() {
        const storyId = this.dataset.storyId;
        const reason = prompt('Reason for undeletion (optional):');
        
        if (reason === null) return; // User cancelled
        
        if (confirm('Are you sure you want to undelete this story?')) {
            fetch(`/mod/stories/${storyId}/undelete`, {
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
                    alert('Error: ' + (data.error || 'Failed to undelete story'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to undelete story');
            });
        }
    });
});
</script>