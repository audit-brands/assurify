<?php $this->layout('layout', ['title' => $title]) ?>

<style>
/* Consistent button styling to match logout button */
.submit-button {
    padding: 0.5em 1em;
    background: #ff4444;
    color: white;
    border: none;
    border-radius: 3px;
    cursor: pointer;
    font-size: 14px;
    white-space: nowrap;
    text-decoration: none;
    display: inline-block;
}

.submit-button:hover {
    background: #cc3333;
    text-decoration: none;
}
</style>

<div class="settings-page">
    <h1>Account Settings</h1>

    <?php if ($success) : ?>
        <div class="success-message">
            <?=$this->e($success)?>
        </div>
    <?php endif ?>

    <?php if ($error) : ?>
        <div class="error-message">
            <?=$this->e($error)?>
        </div>
    <?php endif ?>

    <form method="post" action="/settings" class="settings-form">
        <div class="settings-section">
            <h2>Account Settings</h2>
            
            <div class="form-group">
                <label for="username" class="required">Username:</label>
                <input type="text" name="username" id="username" value="<?=$this->e($settings['username'])?>" required maxlength="50" pattern="[A-Za-z0-9_\-]+">
                <small>Format: letters, numbers, underscore, and dash only. Username changes are logged.</small>
            </div>
            
            <div class="form-group">
                <label for="email" class="required">Email Address:</label>
                <input type="email" name="email" id="email" value="<?=$this->e($settings['email'])?>" required inputmode="email">
                <small>Used for account recovery and notifications (if enabled).</small>
            </div>
        </div>

        <div class="settings-section">
            <h2>Profile Information</h2>
            
            <div class="form-group">
                <label for="about">About:</label>
                <textarea name="about" id="about" rows="4" placeholder="Tell us about yourself..."><?=$this->e($settings['about'])?></textarea>
                <small>You can use plain text or basic HTML.</small>
            </div>

            <div class="form-group">
                <label for="homepage">Homepage:</label>
                <input type="url" name="homepage" id="homepage" value="<?=$this->e($settings['homepage'])?>" placeholder="https://example.com">
            </div>

            <div class="form-group">
                <label for="linkedin_username">LinkedIn Profile:</label>
                <input type="text" name="linkedin_username" id="linkedin_username" value="<?=$this->e($settings['linkedin_username'] ?? '')?>" placeholder="profilename">
                <small>Include your LinkedIn profile name (e.g. linkedin.com/in/profilename)</small>
            </div>

            <div class="form-group">
                <label for="github_username">GitHub Username:</label>
                <input type="text" name="github_username" id="github_username" value="<?=$this->e($settings['github_username'])?>" placeholder="username">
            </div>

            <div class="form-group">
                <label for="bluesky_username">Bluesky Handle:</label>
                <input type="text" name="bluesky_username" id="bluesky_username" value="<?=$this->e($settings['bluesky_username'] ?? '')?>" placeholder="username.bsky.social">
                <small>Your full Bluesky handle (e.g., username.bsky.social)</small>
            </div>

            <div class="form-group">
                <label for="twitter_username">Twitter Username:</label>
                <input type="text" name="twitter_username" id="twitter_username" value="<?=$this->e($settings['twitter_username'])?>" placeholder="username">
            </div>

            <div class="form-group">
                <label for="mastodon_username">Mastodon Handle:</label>
                <input type="text" name="mastodon_username" id="mastodon_username" value="<?=$this->e($settings['mastodon_username'] ?? '')?>" placeholder="@username@instance.social">
                <small>Include your full Mastodon handle with the instance (e.g., @username@mastodon.social)</small>
            </div>
        </div>

        <div class="settings-section">
            <h2>Privacy</h2>
            
            <div class="form-group checkbox">
                <label>
                    <input type="checkbox" name="show_email" <?= $settings['show_email'] ? 'checked' : '' ?>>
                    Show email address on profile
                </label>
            </div>

            <div class="form-group">
                <label for="allow_messages_from">Who can send you private messages:</label>
                <select name="allow_messages_from" id="allow_messages_from">
                    <option value="anyone" <?= ($settings['allow_messages_from'] ?? 'members') === 'anyone' ? 'selected' : '' ?>>Anyone</option>
                    <option value="members" <?= ($settings['allow_messages_from'] ?? 'members') === 'members' ? 'selected' : '' ?>>Members only</option>
                    <option value="followed_users" <?= ($settings['allow_messages_from'] ?? 'members') === 'followed_users' ? 'selected' : '' ?>>Users I follow only</option>
                    <option value="none" <?= ($settings['allow_messages_from'] ?? 'members') === 'none' ? 'selected' : '' ?>>No one</option>
                </select>
                <small>Control who can send you direct messages. This setting helps you manage unsolicited messages.</small>
            </div>
        </div>

        <div class="settings-section">
            <h2>Display Preferences</h2>
            
            <div class="form-group checkbox">
                <label>
                    <input type="checkbox" name="show_avatars" <?= $settings['show_avatars'] ? 'checked' : '' ?>>
                    Show user avatars
                </label>
            </div>

            <div class="form-group checkbox">
                <label>
                    <input type="checkbox" name="show_story_previews" <?= $settings['show_story_previews'] ? 'checked' : '' ?>>
                    Show story content previews
                </label>
            </div>

            <div class="form-group checkbox">
                <label>
                    <input type="checkbox" name="show_read_ribbons" <?= $settings['show_read_ribbons'] ? 'checked' : '' ?>>
                    Show read ribbons to track which stories you've seen
                </label>
            </div>

            <div class="form-group checkbox">
                <label>
                    <input type="checkbox" name="hide_dragons" <?= $settings['hide_dragons'] ? 'checked' : '' ?>>
                    Hide heavily flagged stories
                </label>
            </div>
        </div>

        <!-- Tag Preferences Section -->
        <div class="settings-section">
            <h3>Tag Preferences</h3>
            <p class="section-description">Control which tags you want to see or hide in your story feed.</p>

            <div class="form-group">
                <label for="filtered_tags">Filtered Tags (Hide)</label>
                <div class="tag-input-container">
                    <input type="text" 
                           id="filtered_tags" 
                           name="filtered_tags" 
                           value="<?= $this->e(implode(', ', $tag_preferences['filtered_tags'])) ?>"
                           placeholder="Enter tags to hide from your feed (comma-separated)"
                           class="tag-input">
                    <div class="selected-tags" id="filtered-tags-display"></div>
                </div>
                <small>Stories with these tags will be hidden from your feed. You can still access them through direct links or tag pages.</small>
            </div>

            <div class="form-group">
                <label for="favorite_tags">Favorite Tags (Prioritize)</label>
                <div class="tag-input-container">
                    <input type="text" 
                           id="favorite_tags" 
                           name="favorite_tags" 
                           value="<?= $this->e(implode(', ', $tag_preferences['favorite_tags'])) ?>"
                           placeholder="Enter tags to prioritize in your feed (comma-separated)"
                           class="tag-input">
                    <div class="selected-tags" id="favorite-tags-display"></div>
                </div>
                <small>Stories with these tags will appear higher in your feed.</small>
            </div>

            <?php if (!empty($all_tags)): ?>
                <details class="tag-suggestions">
                    <summary>Available Tags</summary>
                    <div class="available-tags-grid">
                        <?php foreach (array_slice($all_tags, 0, 30) as $tag): ?>
                            <button type="button" 
                                    class="available-tag" 
                                    data-tag="<?= $this->e($tag['tag']) ?>"
                                    title="<?= $this->e($tag['description']) ?>">
                                <?= $this->e($tag['tag']) ?>
                                <?php if ($tag['story_count'] > 0): ?>
                                    <span class="tag-count">(<?= $tag['story_count'] ?>)</span>
                                <?php endif ?>
                            </button>
                        <?php endforeach ?>
                    </div>
                </details>
            <?php endif ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="submit-button">Save Settings</button>
            <a href="/u/<?=$this->e($user->username)?>" class="cancel-link">Cancel</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tag preferences functionality
    const filteredTagsInput = document.getElementById('filtered_tags');
    const favoriteTagsInput = document.getElementById('favorite_tags');
    const availableTags = document.querySelectorAll('.available-tag');
    
    // Handle available tag clicks
    availableTags.forEach(button => {
        button.addEventListener('click', function() {
            const tag = this.dataset.tag;
            const isFiltered = filteredTagsInput.value.includes(tag);
            const isFavorite = favoriteTagsInput.value.includes(tag);
            
            if (!isFiltered && !isFavorite) {
                // Ask user which list to add to
                const choice = confirm('Add "' + tag + '" to your filtered tags (Hide)?\n\nClick OK to filter (hide), or Cancel to add as favorite (prioritize).');
                
                if (choice) {
                    addTagToInput(filteredTagsInput, tag);
                } else {
                    addTagToInput(favoriteTagsInput, tag);
                }
            } else if (isFiltered) {
                removeTagFromInput(filteredTagsInput, tag);
            } else if (isFavorite) {
                removeTagFromInput(favoriteTagsInput, tag);
            }
            
            updateTagDisplay();
        });
    });
    
    // Handle tag input changes
    filteredTagsInput.addEventListener('input', updateTagDisplay);
    favoriteTagsInput.addEventListener('input', updateTagDisplay);
    
    function addTagToInput(input, tag) {
        const currentTags = input.value.split(',').map(t => t.trim()).filter(t => t !== '');
        if (!currentTags.includes(tag)) {
            currentTags.push(tag);
            input.value = currentTags.join(', ');
        }
    }
    
    function removeTagFromInput(input, tag) {
        const currentTags = input.value.split(',').map(t => t.trim()).filter(t => t !== '');
        const newTags = currentTags.filter(t => t !== tag);
        input.value = newTags.join(', ');
    }
    
    function updateTagDisplay() {
        const filteredTags = filteredTagsInput.value.split(',').map(t => t.trim()).filter(t => t !== '');
        const favoriteTags = favoriteTagsInput.value.split(',').map(t => t.trim()).filter(t => t !== '');
        
        // Update available tag buttons to show current state
        availableTags.forEach(button => {
            const tag = button.dataset.tag;
            button.classList.remove('filtered', 'favorite');
            
            if (filteredTags.includes(tag)) {
                button.classList.add('filtered');
                button.title = button.title.split(' (')[0] + ' (Currently filtered - click to remove)';
            } else if (favoriteTags.includes(tag)) {
                button.classList.add('favorite');
                button.title = button.title.split(' (')[0] + ' (Currently favorite - click to remove)';
            } else {
                button.title = button.dataset.tag + (button.querySelector('.tag-count') ? button.querySelector('.tag-count').textContent : '') + ' (Click to add)';
            }
        });
    }
    
    // Initial display update
    updateTagDisplay();
});
</script>