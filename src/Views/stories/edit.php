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
            <small>You can use <a href="#" class="markdown-help-link">Markdown formatting</a> in your text content</small>
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

<div id="markdown-help-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Markdown Formatting Help</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="markdown-examples">
                <div class="example">
                    <strong>Bold text:</strong>
                    <code>**bold text**</code>
                </div>
                <div class="example">
                    <strong>Italic text:</strong>
                    <code>*italic text*</code>
                </div>
                <div class="example">
                    <strong>Links:</strong>
                    <code>[link text](https://example.com)</code>
                </div>
                <div class="example">
                    <strong>Lists:</strong>
                    <code>- Item 1<br>- Item 2</code>
                </div>
                <div class="example">
                    <strong>Code:</strong>
                    <code>`inline code`</code>
                </div>
                <div class="example">
                    <strong>Code blocks:</strong>
                    <code>```<br>code block<br>```</code>
                </div>
                <div class="example">
                    <strong>Headings:</strong>
                    <code># Heading 1<br>## Heading 2</code>
                </div>
                <div class="example">
                    <strong>Quotes:</strong>
                    <code>&gt; This is a quote</code>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Markdown help modal functionality
    const markdownHelpLink = document.querySelector('.markdown-help-link');
    const markdownModal = document.getElementById('markdown-help-modal');
    const modalClose = document.querySelector('.modal-close');

    if (markdownHelpLink) {
        markdownHelpLink.addEventListener('click', function(e) {
            e.preventDefault();
            markdownModal.style.display = 'flex';
        });
    }

    if (modalClose) {
        modalClose.addEventListener('click', function() {
            markdownModal.style.display = 'none';
        });
    }

    // Close modal when clicking outside
    markdownModal.addEventListener('click', function(e) {
        if (e.target === markdownModal) {
            markdownModal.style.display = 'none';
        }
    });
});
</script>

<style>
.markdown-help-link {
    color: var(--color-fg-link);
    text-decoration: none;
}

.markdown-help-link:hover {
    text-decoration: underline;
}

.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background-color: var(--color-bg);
    border: 1px solid var(--color-fg-contrast-4-5);
    border-radius: 6px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid var(--color-fg-contrast-4-5);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: var(--color-fg);
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5em;
    cursor: pointer;
    color: var(--color-fg-contrast-10);
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover {
    color: var(--color-fg);
}

.modal-body {
    padding: 20px;
}

.markdown-examples .example {
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--color-fg-contrast-4-5);
}

.markdown-examples .example:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.markdown-examples strong {
    color: var(--color-fg);
}

.markdown-examples code {
    background-color: rgba(255, 255, 255, 0.05);
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    font-size: 0.9em;
    color: var(--color-fg);
    display: block;
    margin-top: 5px;
    white-space: pre-line;
}
</style>