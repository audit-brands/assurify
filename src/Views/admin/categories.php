<?php $this->layout('layout', ['title' => $title]) ?>

<div class="admin-page">
    <h1>Tag Categories Management</h1>
    
    <div class="category-management">
        <!-- Add New Category Form -->
        <div class="add-category-section">
            <h2>Add Category</h2>
            <form id="add-category-form" class="category-form">
                <div class="form-row">
                    <input type="text" id="new-category-name" placeholder="Category Name" required maxlength="50">
                    <input type="text" id="new-category-description" placeholder="Description (optional)" maxlength="200">
                    <button type="submit">Add Category</button>
                </div>
            </form>
        </div>

        <!-- Existing Categories -->
        <div class="categories-list">
            <h2>Categories</h2>
            
            <?php if (empty($categories)): ?>
                <p>No categories found. Create your first category above.</p>
            <?php else: ?>
                <?php 
                // Sort categories alphabetically by name
                usort($categories, function($a, $b) {
                    return strcasecmp($a['name'], $b['name']);
                });
                ?>
                <div class="categories-container">
                    <?php foreach ($categories as $category): ?>
                        <div class="category-card" data-category-id="<?= $category['id'] ?>">
                            <div class="category-header">
                                <h3 class="category-name" data-category-id="<?= $category['id'] ?>"><?= $this->e($category['name']) ?></h3>
                                <div class="category-actions">
                                    <button class="edit-category-btn" data-category-id="<?= $category['id'] ?>">[edit]</button>
                                    <button class="delete-category-btn" data-category-id="<?= $category['id'] ?>">[delete]</button>
                                </div>
                            </div>
                            
                            <p class="category-description"><?= $this->e($category['description'] ?? 'No description') ?></p>
                            
                            <!-- Edit form (hidden by default) -->
                            <form class="edit-category-form" data-category-id="<?= $category['id'] ?>" style="display: none;">
                                <input type="text" class="edit-name" value="<?= $this->e($category['name']) ?>" required maxlength="50">
                                <input type="text" class="edit-description" value="<?= $this->e($category['description'] ?? '') ?>" maxlength="200">
                                <div class="edit-form-buttons">
                                    <button type="submit">Save</button>
                                    <button type="button" class="cancel-edit">Cancel</button>
                                </div>
                            </form>
                            
                            <div class="category-tags">
                                <h4>Tags (<?= count($category['tags']) ?>):</h4>
                                <div class="tags-list">
                                    <?php if (empty($category['tags'])): ?>
                                        <span class="no-tags">No tags assigned</span>
                                    <?php else: ?>
                                        <?php foreach ($category['tags'] as $tag): ?>
                                            <span class="tag-item" data-tag-id="<?= $tag['id'] ?>">
                                                <?= $this->e($tag['tag']) ?>
                                                <button class="remove-tag-btn" data-tag-id="<?= $tag['id'] ?>" data-category-id="<?= $category['id'] ?>">×</button>
                                            </span>
                                        <?php endforeach ?>
                                    <?php endif ?>
                                </div>
                            </div>
                            
                            <!-- Add Tags Section - moved below the tags -->
                            <?php 
                            // Get tags from other categories that can be moved to this category
                            $availableTags = [];
                            if ($category['name'] !== 'Other') {
                                // For non-Other categories, show tags from Other category
                                $otherCategory = array_filter($categories, fn($cat) => $cat['name'] === 'Other');
                                if (!empty($otherCategory)) {
                                    $otherCategory = reset($otherCategory);
                                    $availableTags = $otherCategory['tags'];
                                }
                            } else {
                                // For Other category, show tags from all other categories
                                foreach ($categories as $otherCat) {
                                    if ($otherCat['name'] !== 'Other') {
                                        $availableTags = array_merge($availableTags, $otherCat['tags']);
                                    }
                                }
                            }
                            ?>
                            
                            <?php if (!empty($availableTags)): ?>
                                <div class="add-tags-section">
                                    <h4 class="add-tags-toggle" data-category-id="<?= $category['id'] ?>">
                                        <span class="toggle-arrow">▶</span> Add Tags (<?= count($availableTags) ?>)
                                    </h4>
                                    <div class="add-tags-list" data-category-id="<?= $category['id'] ?>" style="display: none;">
                                        <?php foreach ($availableTags as $tag): ?>
                                            <span class="available-tag-item" data-tag-id="<?= $tag['id'] ?>">
                                                <?= $this->e($tag['tag']) ?>
                                                <button class="add-tag-btn" data-tag-id="<?= $tag['id'] ?>" data-category-id="<?= $category['id'] ?>">+</button>
                                            </span>
                                        <?php endforeach ?>
                                    </div>
                                </div>
                            <?php endif ?>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>

        <!-- Uncategorized Tags -->
        <?php if (!empty($uncategorized_tags)): ?>
            <div class="uncategorized-section">
                <h2>Uncategorized Tags (<?= count($uncategorized_tags) ?>)</h2>
                <p>Drag these tags to categories above, or assign them using the dropdown:</p>
                
                <div class="uncategorized-tags">
                    <?php foreach ($uncategorized_tags as $tag): ?>
                        <div class="uncategorized-tag" data-tag-id="<?= $tag['id'] ?>">
                            <span class="tag-name"><?= $this->e($tag['tag']) ?></span>
                            <select class="assign-category" data-tag-id="<?= $tag['id'] ?>">
                                <option value="">Select category...</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>"><?= $this->e($category['name']) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
        <?php endif ?>
    </div>
</div>

<style>
.admin-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1em;
}

.category-form {
    border: 1px solid #ccc;
    padding: 1em;
    margin-bottom: 2em;
}

.form-row {
    display: flex;
    gap: 0.5em;
    align-items: center;
}

.form-row input {
    padding: 0.5em;
    border: 1px solid #ccc;
}

.form-row input:first-child {
    flex: 1;
}

.form-row input:nth-child(2) {
    flex: 2;
}

.form-row button {
    padding: 0.5em 1em;
    background: #ff4444;
    color: white;
    border: none;
    cursor: pointer;
}

.categories-list {
    margin: 1em 0;
}

.category-card {
    border: 1px solid #ccc;
    padding: 1em;
    margin-bottom: 1em;
}

.category-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5em;
}

.category-header h3 {
    margin: 0;
    color: #333;
}

.category-actions button {
    background: none;
    border: none;
    color: #ff4444;
    cursor: pointer;
    font-size: 8pt;
    margin-left: 0.5em;
}

.category-description {
    color: #666;
    font-size: 0.9em;
    margin-bottom: 1em;
}

.category-tags h4 {
    margin: 1em 0 0.5em 0;
    color: #333;
    font-size: 0.9em;
}

.tags-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25em;
}

.tag-item {
    background-color: var(--color-tag-bg, #333333);
    border: 1px solid var(--color-tag-border, #555555);
    color: var(--color-fg);
    padding: 0.1em 0.3em;
    font-size: 0.8em;
    display: inline-flex;
    align-items: center;
    margin: 0.1em;
}

.remove-tag-btn {
    background: none;
    border: none;
    color: #dc3545;
    cursor: pointer;
    margin-left: 0.8em;
    font-weight: bold;
    padding: 0.15em;
}

.uncategorized-section {
    margin-top: 2em;
    padding-top: 2em;
    border-top: 2px solid #eee;
}

.uncategorized-tags {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 0.5em;
    margin: 1em 0;
}

.uncategorized-tag {
    display: flex;
    align-items: center;
    gap: 0.5em;
    padding: 0.5em;
    border: 1px solid #ccc;
}

.tag-name {
    font-weight: bold;
    flex: 1;
}

.assign-category {
    padding: 0.2em;
    border: 1px solid #ccc;
}

.edit-category-form {
    display: flex;
    flex-direction: column;
    gap: 0.5em;
    margin: 0.5em 0;
}

.edit-category-form input {
    padding: 0.3em;
    border: 1px solid #ccc;
    width: 100%;
    max-width: 600px;
}

.edit-form-buttons {
    display: flex;
    gap: 0.5em;
}

.edit-category-form button {
    padding: 0.3em 0.6em;
    border: none;
    cursor: pointer;
}

.edit-category-form button[type="submit"] {
    background: #ff4444;
    color: white;
}

.cancel-edit {
    background: #666;
    color: white;
}

.no-tags {
    color: #999;
    font-style: italic;
}

.add-tags-section {
    margin-top: 1em;
    padding-top: 1em;
    border-top: 1px solid #eee;
}

.add-tags-toggle {
    color: #666;
    cursor: pointer;
    user-select: none;
    margin: 0 0 0.5em 0;
    font-size: 0.9em;
}

.add-tags-toggle:hover {
    color: #333;
}

.toggle-arrow {
    display: inline-block;
    transition: transform 0.2s ease;
    font-size: 0.8em;
}

.toggle-arrow.expanded {
    transform: rotate(90deg);
}

.add-tags-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25em;
    margin: 0.5em 0;
}

.available-tag-item {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    color: #495057;
    padding: 0.1em 0.3em;
    font-size: 0.8em;
    display: inline-flex;
    align-items: center;
    margin: 0.1em;
}

.add-tag-btn {
    background: none;
    border: none;
    color: #28a745;
    cursor: pointer;
    margin-left: 0.4em;
    font-weight: bold;
    padding: 0.15em;
}

.add-tag-btn:hover {
    color: #1e7e34;
}

.no-available-tags {
    color: #999;
    font-style: italic;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add new category
    document.getElementById('add-category-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const name = document.getElementById('new-category-name').value.trim();
        const description = document.getElementById('new-category-description').value.trim();
        
        if (!name) return;
        
        fetch('/admin/categories/add', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `name=${encodeURIComponent(name)}&description=${encodeURIComponent(description)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Clear the form
                document.getElementById('new-category-name').value = '';
                document.getElementById('new-category-description').value = '';
                // Reload to show the new category
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Failed to add category'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error adding category');
        });
    });
    
    // Edit category
    document.querySelectorAll('.edit-category-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const categoryId = this.dataset.categoryId;
            const card = document.querySelector(`[data-category-id="${categoryId}"]`);
            const header = card.querySelector('.category-header');
            const description = card.querySelector('.category-description');
            const form = card.querySelector('.edit-category-form');
            
            header.style.display = 'none';
            description.style.display = 'none';
            form.style.display = 'flex';
        });
    });
    
    // Cancel edit
    document.querySelectorAll('.cancel-edit').forEach(btn => {
        btn.addEventListener('click', function() {
            const form = this.closest('.edit-category-form');
            const card = form.closest('.category-card');
            const header = card.querySelector('.category-header');
            const description = card.querySelector('.category-description');
            
            form.style.display = 'none';
            header.style.display = 'flex';
            description.style.display = 'block';
        });
    });
    
    // Save category edit
    document.querySelectorAll('.edit-category-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const categoryId = this.dataset.categoryId;
            const name = this.querySelector('.edit-name').value.trim();
            const description = this.querySelector('.edit-description').value.trim();
            
            fetch(`/admin/categories/${categoryId}/update`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `name=${encodeURIComponent(name)}&description=${encodeURIComponent(description)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Failed to update category'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating category');
            });
        });
    });
    
    // Delete category
    document.querySelectorAll('.delete-category-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const categoryId = this.dataset.categoryId;
            const categoryName = this.closest('.category-card').querySelector('.category-name').textContent;
            
            if (confirm(`Are you sure you want to delete the "${categoryName}" category? Tags in this category will become uncategorized.`)) {
                fetch(`/admin/categories/${categoryId}/delete`, {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (data.error || 'Failed to delete category'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting category');
                });
            }
        });
    });
    
    // Assign tag to category
    document.querySelectorAll('.assign-category').forEach(select => {
        select.addEventListener('change', function() {
            const tagId = this.dataset.tagId;
            const categoryId = this.value;
            
            if (!categoryId) return;
            
            fetch('/admin/tags/assign-category', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `tag_id=${tagId}&category_id=${categoryId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Failed to assign tag'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error assigning tag');
            });
        });
    });
    
    // Toggle add tags section
    document.querySelectorAll('.add-tags-toggle').forEach(toggle => {
        toggle.addEventListener('click', function() {
            const categoryId = this.dataset.categoryId;
            const arrow = this.querySelector('.toggle-arrow');
            const addTagsList = document.querySelector(`.add-tags-list[data-category-id="${categoryId}"]`);
            
            if (addTagsList.style.display === 'none') {
                addTagsList.style.display = 'flex';
                arrow.classList.add('expanded');
            } else {
                addTagsList.style.display = 'none';
                arrow.classList.remove('expanded');
            }
        });
    });
    
    // Add tag to category
    document.querySelectorAll('.add-tag-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const tagId = this.dataset.tagId;
            const categoryId = this.dataset.categoryId;
            
            fetch('/admin/tags/assign-category', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `tag_id=${tagId}&category_id=${categoryId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Failed to add tag'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error adding tag');
            });
        });
    });
    
    // Remove tag from category
    document.querySelectorAll('.remove-tag-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const tagId = this.dataset.tagId;
            
            fetch('/admin/tags/remove-category', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `tag_id=${tagId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Failed to remove tag'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error removing tag');
            });
        });
    });
});
</script>