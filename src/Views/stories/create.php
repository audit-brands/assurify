<?php

$this->layout('layout', ['title' => $title]) ?>

<div class="story-form">
    <h1>Submit a Story</h1>
    
    <?php if ($error) : ?>
        <div class="error-message">
            <?=$this->e($error)?>
        </div>
    <?php endif ?>
    
    <form action="/stories" method="post">
        <div class="form-group">
            <label for="title">Title:</label>
            <input type="text" name="title" id="title" required maxlength="150">
        </div>

        <div class="form-group">
            <label for="url">URL:</label>
            <input type="url" name="url" id="url" placeholder="https://example.com" value="<?=$this->e($url ?? '')?>">
            <small>Leave blank to submit a text post</small>
        </div>

        <div class="form-group">
            <label for="description">Text:</label>
            <textarea name="description" id="description" rows="10" placeholder="Optional description or text content"></textarea>
            <small>Supports Markdown formatting</small>
        </div>

        <div class="form-group tags-group">
            <label for="tags">Tags:</label>
            
            <?php if (!empty($suggested_tags)) : ?>
                <div class="suggested-tags">
                    <span class="suggestion-label">Suggested:</span>
                    <?php foreach ($suggested_tags as $suggestedTag) : ?>
                        <button type="button" class="tag-suggestion" data-tag="<?=$this->e($suggestedTag)?>"><?=$this->e($suggestedTag)?></button>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
            
            <div class="tags-input-container">
                <div class="selected-tags" id="selected-tags"></div>
                <input type="text" id="tag-input" placeholder="Start typing to add tags..." autocomplete="off">
                <input type="hidden" name="tags" id="tags" value="<?=$this->e(implode(', ', $suggested_tags ?? []))?>">
            </div>
            
            <div class="tag-autocomplete" id="tag-autocomplete" style="display: none;"></div>
            
            <small>Type to search existing tags or create new ones (max 5). Popular tags will appear as you type.</small>
            
            <?php if (!empty($all_tags)) : ?>
                <details class="popular-tags">
                    <summary>Popular tags</summary>
                    <div class="popular-tags-list">
                        <?php foreach (array_slice($all_tags, 0, 20) as $tag) : ?>
                            <button type="button" class="popular-tag" data-tag="<?=$this->e($tag['tag'])?>" title="<?=$this->e($tag['description'])?>">
                                <?=$this->e($tag['tag'])?> 
                                <?php if ($tag['story_count'] > 0) : ?>
                                    <span class="tag-count">(<?=$tag['story_count']?>)</span>
                                <?php endif ?>
                            </button>
                        <?php endforeach ?>
                    </div>
                </details>
            <?php endif ?>
        </div>
        
        <div class="form-group">
            <label>
                <input type="checkbox" name="user_is_author" value="1">
                I am the author of this content
            </label>
        </div>

        <div class="form-actions">
            <button type="submit">Submit Story</button>
            <a href="/" class="cancel">Cancel</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tagInput = document.getElementById('tag-input');
    const selectedTags = document.getElementById('selected-tags');
    const tagsHidden = document.getElementById('tags');
    const autocompleteDiv = document.getElementById('tag-autocomplete');
    
    let currentTags = [];
    let allTags = <?= json_encode($all_tags ?? []) ?>;
    
    // Initialize with suggested tags
    const initialTags = <?= json_encode($suggested_tags ?? []) ?>;
    initialTags.forEach(tag => addTag(tag));
    
    // Tag input handling
    tagInput.addEventListener('input', function() {
        const query = this.value.trim().toLowerCase();
        
        if (query.length < 1) {
            hideAutocomplete();
            return;
        }
        
        // Filter tags based on query
        const matches = allTags.filter(tag => 
            tag.tag.toLowerCase().includes(query) && 
            !currentTags.includes(tag.tag)
        ).slice(0, 8);
        
        showAutocomplete(matches, query);
    });
    
    tagInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === 'Tab' || e.key === ',') {
            e.preventDefault();
            const tag = this.value.trim();
            if (tag) {
                addTag(tag);
                this.value = '';
                hideAutocomplete();
            }
        } else if (e.key === 'Backspace' && this.value === '' && currentTags.length > 0) {
            removeTag(currentTags[currentTags.length - 1]);
        }
    });
    
    // Handle suggestion buttons
    document.querySelectorAll('.tag-suggestion, .popular-tag').forEach(button => {
        button.addEventListener('click', function() {
            const tag = this.dataset.tag;
            if (tag && !currentTags.includes(tag)) {
                addTag(tag);
            }
        });
    });
    
    function addTag(tagName) {
        if (currentTags.length >= 5) {
            alert('Maximum 5 tags allowed');
            return;
        }
        
        tagName = tagName.toLowerCase().trim();
        if (tagName && !currentTags.includes(tagName)) {
            currentTags.push(tagName);
            updateDisplay();
            updateHiddenField();
        }
    }
    
    function removeTag(tagName) {
        currentTags = currentTags.filter(tag => tag !== tagName);
        updateDisplay();
        updateHiddenField();
    }
    
    function updateDisplay() {
        selectedTags.innerHTML = '';
        currentTags.forEach(tag => {
            const tagElement = document.createElement('span');
            tagElement.className = 'selected-tag';
            tagElement.innerHTML = `
                ${tag}
                <button type="button" class="remove-tag" data-tag="${tag}">×</button>
            `;
            
            tagElement.querySelector('.remove-tag').addEventListener('click', function() {
                removeTag(this.dataset.tag);
            });
            
            selectedTags.appendChild(tagElement);
        });
    }
    
    function updateHiddenField() {
        tagsHidden.value = currentTags.join(', ');
    }
    
    function showAutocomplete(matches, query) {
        if (matches.length === 0) {
            // Show option to create new tag
            autocompleteDiv.innerHTML = `
                <div class="autocomplete-item create-tag" data-tag="${query}">
                    <strong>Create tag:</strong> "${query}"
                </div>
            `;
        } else {
            autocompleteDiv.innerHTML = matches.map(tag => `
                <div class="autocomplete-item" data-tag="${tag.tag}">
                    <strong>${tag.tag}</strong>
                    <span class="tag-description">${tag.description}</span>
                    ${tag.story_count ? `<span class="tag-count">${tag.story_count} stories</span>` : ''}
                </div>
            `).join('');
        }
        
        // Add option to create new tag if query doesn't match exactly
        if (!matches.some(tag => tag.tag === query) && query.length > 0) {
            autocompleteDiv.innerHTML += `
                <div class="autocomplete-item create-tag" data-tag="${query}">
                    <strong>Create tag:</strong> "${query}"
                </div>
            `;
        }
        
        // Add click handlers
        autocompleteDiv.querySelectorAll('.autocomplete-item').forEach(item => {
            item.addEventListener('click', function() {
                const tag = this.dataset.tag;
                addTag(tag);
                tagInput.value = '';
                hideAutocomplete();
            });
        });
        
        autocompleteDiv.style.display = 'block';
    }
    
    function hideAutocomplete() {
        autocompleteDiv.style.display = 'none';
    }
    
    // Hide autocomplete when clicking outside
    document.addEventListener('click', function(e) {
        if (!tagInput.contains(e.target) && !autocompleteDiv.contains(e.target)) {
            hideAutocomplete();
        }
    });
    
    // URL-based tag suggestions
    const urlField = document.getElementById('url');
    const titleField = document.getElementById('title');
    
    function updateSuggestions() {
        const url = urlField.value;
        const title = titleField.value;
        
        if (url || title) {
            // Simple domain-based suggestions
            const domain = url ? new URL(url).hostname : '';
            
            if (domain.includes('github.com')) {
                suggestTag('programming');
            } else if (domain.includes('youtube.com') || domain.includes('vimeo.com')) {
                suggestTag('video');
            } else if (domain.includes('medium.com') || domain.includes('blog')) {
                suggestTag('article');
            }
        }
    }
    
    function suggestTag(tag) {
        if (!currentTags.includes(tag) && currentTags.length < 5) {
            // Create a temporary suggestion button
            const suggestion = document.createElement('button');
            suggestion.type = 'button';
            suggestion.className = 'tag-suggestion auto-suggested';
            suggestion.textContent = tag;
            suggestion.dataset.tag = tag;
            suggestion.onclick = () => addTag(tag);
            
            const suggestedDiv = document.querySelector('.suggested-tags');
            if (suggestedDiv) {
                suggestedDiv.appendChild(suggestion);
            }
        }
    }
    
    urlField.addEventListener('blur', updateSuggestions);
    titleField.addEventListener('blur', updateSuggestions);
});
</script>