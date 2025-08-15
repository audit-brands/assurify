<?php $this->layout('layout', ['title' => $title]) ?>

<div class="tags-page">
    <h1>Tags</h1>
    
    <p>You can <a href="/filter">filter out stories by tag</a>, even if you don't have an account.</p>
    
    <?php if ($can_edit): ?>
        <div class="admin-tools">
            <p><strong>Admin Tools:</strong> 
                <a href="/admin/categories">Manage Tag Categories</a>
            </p>
        </div>
    <?php endif ?>
    
    <?php if (!empty($popular_tags)): ?>
        <h2>Most Popular</h2>
        <div class="popular-tags">
            <?php foreach ($popular_tags as $tag): ?>
                <a href="/t/<?= $this->e($tag['tag']) ?>" class="tag popular-tag">
                    <?= $this->e($tag['tag']) ?>
                    <?php if (!empty($tag['story_count'])): ?>
                        <span class="count">(<?= $tag['story_count'] ?>)</span>
                    <?php endif ?>
                </a>
            <?php endforeach ?>
        </div>
    <?php endif ?>
    
    <?php if (!empty($categorized_tags)): ?>
        <?php foreach ($categorized_tags as $categoryName => $categoryTags): ?>
            <h2 id="<?= strtolower(str_replace(' ', '_', $categoryName)) ?>"><?= $this->e($categoryName) ?></h2>
            <ol class="category_tags">
                <?php foreach ($categoryTags as $tag): ?>
                    <li id="<?= $this->e($tag['tag']) ?>" data-tag-id="<?= $tag['id'] ?>">
                        <a href="/t/<?= $this->e($tag['tag']) ?>" class="tag"><?= $this->e($tag['tag']) ?></a>
                        <?php if ($can_edit): ?>
                            <span class="tag-description" data-tag-id="<?= $tag['id'] ?>"><?= $this->e($tag['description'] ?? 'No description available') ?></span>
                            <input type="text" class="description-input" data-tag-id="<?= $tag['id'] ?>" value="<?= $this->e($tag['description'] ?? '') ?>" style="display: none;">
                            <button class="save-description" data-tag-id="<?= $tag['id'] ?>" style="display: none;">Save</button>
                            <button class="cancel-description" data-tag-id="<?= $tag['id'] ?>" style="display: none;">Cancel</button>
                            <button class="admin-edit-btn" data-tag-id="<?= $tag['id'] ?>">[edit]</button>
                        <?php else: ?>
                            <span><?= $this->e($tag['description'] ?? 'No description available') ?></span>
                        <?php endif ?>
                        <span class="byline">
                            <?php if (!empty($tag['story_count'])): ?>
                                | <?= $tag['story_count'] ?> stories
                            <?php endif ?>
                        </span>
                    </li>
                <?php endforeach ?>
            </ol>
        <?php endforeach ?>
    <?php endif ?>
</div>

<style>
/* Lobste.rs-style tag page styling */
.tags-page {
    max-width: 800px;
    margin: 0 auto;
    padding: 1em;
}

.category_tags {
    list-style: none;
    padding: 0;
    margin: 0 0 2em 0;
}

.category_tags li {
    margin: 0.2em 0;
    padding: 0;
}

.category_tags .tag {
    font-weight: bold;
    margin-right: 0.5em;
}

.category_tags .byline {
    color: var(--color-fg-contrast-4-5, #999);
    font-size: 9.5pt;
}

.popular-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5em;
    margin: 1em 0;
}

.popular-tag {
    display: inline-block;
    padding: 0.3em 0.6em;
    background: #f5f5f5;
    border: 1px solid #ddd;
    border-radius: 3px;
    text-decoration: none;
    font-size: 0.9em;
}

.popular-tag:hover {
    background: #e5e5e5;
}

.popular-tag .count {
    color: #666;
    font-size: 0.8em;
}

/* Minimal admin editing styles */
.admin-edit-btn {
    background: none;
    border: none;
    color: #ff4444;
    cursor: pointer;
    font-size: 8pt;
    margin-left: 0.5em;
    padding: 0;
}

.admin-edit-btn:hover {
    text-decoration: underline;
}

.description-input {
    width: 300px;
    padding: 2px 4px;
    border: 1px solid #ccc;
    font-size: inherit;
}

.save-description, .cancel-description {
    background: #ff4444;
    color: white;
    border: none;
    padding: 2px 8px;
    margin: 0 2px;
    cursor: pointer;
    font-size: 8pt;
    border-radius: 2px;
}

.cancel-description {
    background: #666;
}

.save-description:hover {
    background: #cc3333;
}

.cancel-description:hover {
    background: #444;
}

.admin-tools {
    border: 1px solid #ccc;
    padding: 0.75em;
    margin: 1em 0;
}

.admin-tools p {
    margin: 0;
}

.admin-tools a {
    color: #ff4444;
    text-decoration: none;
}

.admin-tools a:hover {
    text-decoration: underline;
}
</style>

<?php if ($can_edit): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle edit button clicks
    document.querySelectorAll('.admin-edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const tagId = this.dataset.tagId;
            enterEditMode(tagId);
        });
    });
    
    // Handle save button clicks
    document.querySelectorAll('.save-description').forEach(button => {
        button.addEventListener('click', function() {
            const tagId = this.dataset.tagId;
            saveDescription(tagId);
        });
    });
    
    // Handle cancel button clicks
    document.querySelectorAll('.cancel-description').forEach(button => {
        button.addEventListener('click', function() {
            const tagId = this.dataset.tagId;
            cancelEdit(tagId);
        });
    });
    
    function enterEditMode(tagId) {
        const li = document.querySelector(`li[data-tag-id="${tagId}"]`);
        const description = li.querySelector(`.tag-description[data-tag-id="${tagId}"]`);
        const input = li.querySelector(`.description-input[data-tag-id="${tagId}"]`);
        const saveBtn = li.querySelector(`.save-description[data-tag-id="${tagId}"]`);
        const cancelBtn = li.querySelector(`.cancel-description[data-tag-id="${tagId}"]`);
        const editBtn = li.querySelector(`.admin-edit-btn[data-tag-id="${tagId}"]`);
        
        // Store original value for cancel
        input.dataset.originalValue = input.value;
        
        // Hide description text and edit button, show input and save/cancel
        description.style.display = 'none';
        editBtn.style.display = 'none';
        input.style.display = 'inline';
        saveBtn.style.display = 'inline';
        cancelBtn.style.display = 'inline';
        
        input.focus();
        input.select();
    }
    
    function cancelEdit(tagId) {
        const li = document.querySelector(`li[data-tag-id="${tagId}"]`);
        const description = li.querySelector(`.tag-description[data-tag-id="${tagId}"]`);
        const input = li.querySelector(`.description-input[data-tag-id="${tagId}"]`);
        const saveBtn = li.querySelector(`.save-description[data-tag-id="${tagId}"]`);
        const cancelBtn = li.querySelector(`.cancel-description[data-tag-id="${tagId}"]`);
        const editBtn = li.querySelector(`.admin-edit-btn[data-tag-id="${tagId}"]`);
        
        // Restore original value
        input.value = input.dataset.originalValue;
        
        // Show description text and edit button, hide input and save/cancel
        description.style.display = 'inline';
        editBtn.style.display = 'inline';
        input.style.display = 'none';
        saveBtn.style.display = 'none';
        cancelBtn.style.display = 'none';
    }
    
    function saveDescription(tagId) {
        const li = document.querySelector(`li[data-tag-id="${tagId}"]`);
        const description = li.querySelector(`.tag-description[data-tag-id="${tagId}"]`);
        const input = li.querySelector(`.description-input[data-tag-id="${tagId}"]`);
        const saveBtn = li.querySelector(`.save-description[data-tag-id="${tagId}"]`);
        const cancelBtn = li.querySelector(`.cancel-description[data-tag-id="${tagId}"]`);
        const editBtn = li.querySelector(`.admin-edit-btn[data-tag-id="${tagId}"]`);
        
        const newDescription = input.value.trim();
        
        // Disable buttons during save
        saveBtn.disabled = true;
        cancelBtn.disabled = true;
        saveBtn.textContent = 'Saving...';
        
        console.log('Saving tag', tagId, 'with description:', newDescription);
        console.log('URL will be:', `/admin/tags/${tagId}/update`);
        
        fetch(`/admin/tags/${tagId}/update`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `description=${encodeURIComponent(newDescription)}`
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.log('Response text:', text);
                    throw new Error('Invalid JSON response: ' + text);
                }
            });
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                // Update description text
                description.textContent = newDescription || 'No description available';
                
                // Show description text and edit button, hide input and save/cancel
                description.style.display = 'inline';
                editBtn.style.display = 'inline';
                input.style.display = 'none';
                saveBtn.style.display = 'none';
                cancelBtn.style.display = 'none';
                
                // Reset button
                saveBtn.disabled = false;
                cancelBtn.disabled = false;
                saveBtn.textContent = 'Save';
            } else {
                alert('Error: ' + (data.error || 'Failed to update description'));
                // Reset button
                saveBtn.disabled = false;
                cancelBtn.disabled = false;
                saveBtn.textContent = 'Save';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating description: ' + error.message);
            // Reset button
            saveBtn.disabled = false;
            cancelBtn.disabled = false;
            saveBtn.textContent = 'Save';
        });
    }
});
</script>
<?php endif ?>