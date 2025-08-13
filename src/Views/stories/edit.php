<?php

$this->layout('layout', ['title' => $title]) ?>

<div class="story-form">
    <h1>Edit Story</h1>
    
    <?php if ($error) : ?>
        <div class="error-message">
            <?=$this->e($error)?>
        </div>
    <?php endif ?>
    
    <form action="/s/<?=$this->e($story['short_id'])?>/update" method="post">
        <div class="form-group">
            <label for="title">Title:</label>
            <input type="text" name="title" id="title" required maxlength="150" value="<?=$this->e($story['title'])?>">
        </div>

        <?php if ($story['url']) : ?>
        <div class="form-group">
            <label for="url">URL:</label>
            <input type="url" name="url" id="url" readonly value="<?=$this->e($story['url'])?>">
            <small>URL cannot be changed after submission</small>
        </div>
        <?php endif ?>

        <div class="form-group">
            <label for="description">Text:</label>
            <textarea name="description" id="description" rows="10" placeholder="Optional description or text content"><?=$this->e($story['description'])?></textarea>
            <small>Supports Markdown formatting</small>
        </div>

        <div class="form-group">
            <label for="tags">Tags:</label>
            <input type="text" name="tags" id="tags" placeholder="auditing, risk, jobs" value="<?=$this->e(implode(', ', $story['tags']))?>">
            <small>Comma-separated tags (max 5)</small>
        </div>
        
        <div class="form-group">
            <label>
                <input type="checkbox" name="user_is_author" value="1" <?=$story['user_is_author'] ? 'checked' : ''?>>
                I am the author of this content
            </label>
        </div>

        <div class="form-actions">
            <button type="submit">Update Story</button>
            <a href="/s/<?=$this->e($story['short_id'])?>" class="cancel">Cancel</a>
        </div>
    </form>
</div>