<?php $this->layout('layout', ['title' => $title]) ?>

<div class="messages-page">
    <div class="messages-header">
        <h1>Compose Message</h1>
        <div class="messages-nav">
            <a href="/messages" class="nav-link">Inbox</a>
            <a href="/messages/sent" class="nav-link">Sent</a>
            <a href="/messages/compose" class="nav-link active">Compose</a>
            <a href="/messages/search" class="nav-link">Search</a>
        </div>
    </div>

    <?php if (isset($error)) : ?>
        <div class="error-message">
            <p><?= $this->e($error) ?></p>
        </div>
    <?php endif ?>

    <div class="compose-form-container">
        <form method="POST" action="/messages/send" class="compose-form">
            <div class="form-group">
                <label for="recipient_username">To:</label>
                <input type="text" 
                       id="recipient_username" 
                       name="recipient_username" 
                       value="<?= $this->e($recipient_username ?? '') ?>"
                       placeholder="Enter username"
                       class="form-input recipient-input"
                       required>
                <div class="recipient-suggestions" id="recipient-suggestions"></div>
                <?php if (isset($recipient)) : ?>
                    <div class="recipient-info">
                        <span class="recipient-status">User found: <strong><?= $this->e($recipient->username) ?></strong></span>
                    </div>
                <?php endif ?>
            </div>

            <div class="form-group">
                <label for="subject">Subject:</label>
                <input type="text" 
                       id="subject" 
                       name="subject" 
                       value="<?= $this->e($subject ?? '') ?>"
                       placeholder="Enter message subject"
                       class="form-input"
                       maxlength="100"
                       required>
                <div class="form-hint">Maximum 100 characters</div>
            </div>

            <div class="form-group">
                <label for="body">Message:</label>
                <textarea id="body" 
                          name="body" 
                          placeholder="Write your message here..."
                          class="form-textarea"
                          rows="12"
                          required><?= $this->e($body ?? '') ?></textarea>
                <div class="form-hint">
                    Markdown formatting supported. Messages are limited to 65,535 characters.
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Send Message</button>
                <a href="/messages" class="btn btn-secondary">Cancel</a>
            </div>
        </form>

        <div class="compose-help">
            <h3>Message Guidelines</h3>
            <ul>
                <li>Be respectful and courteous in your messages</li>
                <li>Use clear and descriptive subject lines</li>
                <li>You can use <a href="#" class="markdown-help-link">Markdown formatting</a> in your message body</li>
                <li>Messages are private between you and the recipient</li>
                <li>Users can opt out of receiving private messages in their settings</li>
            </ul>
        </div>
    </div>
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
            </div>
        </div>
    </div>
</div>

<style>
.messages-page {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.messages-header {
    margin-bottom: 30px;
    border-bottom: 1px solid var(--color-fg-contrast-4-5);
    padding-bottom: 20px;
}

.messages-header h1 {
    margin: 0 0 15px 0;
    font-size: 2em;
    color: var(--color-fg);
}

.messages-nav {
    display: flex;
    gap: 20px;
}

.messages-nav .nav-link {
    padding: 8px 16px;
    text-decoration: none;
    color: var(--color-fg-contrast-10);
    border-radius: 4px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.messages-nav .nav-link:hover {
    background-color: rgba(255, 255, 255, 0.05);
    color: var(--color-fg);
}

.messages-nav .nav-link.active {
    background-color: var(--color-accent);
    color: white;
}

.messages-nav .compose-link {
    background-color: var(--color-accent);
    color: white;
}

.messages-nav .compose-link:hover {
    background-color: var(--color-accent-hover);
}

.compose-form-container {
    max-width: 700px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 30px;
    align-items: start;
}

.compose-form {
    background-color: var(--color-bg);
    border: 1px solid var(--color-fg-contrast-4-5);
    border-radius: 6px;
    padding: 24px;
}

.form-group {
    margin-bottom: 20px;
    position: relative;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    color: var(--color-fg);
}

.form-input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--color-fg-contrast-4-5);
    border-radius: 4px;
    font-size: 1em;
    background-color: var(--color-bg);
    color: var(--color-fg);
    transition: border-color 0.2s ease;
}

.form-input:focus {
    outline: none;
    border-color: var(--color-accent);
    box-shadow: 0 0 0 2px rgba(var(--color-accent-rgb), 0.2);
}

.form-textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--color-fg-contrast-4-5);
    border-radius: 4px;
    font-size: 1em;
    font-family: inherit;
    background-color: var(--color-bg);
    color: var(--color-fg);
    resize: vertical;
    min-height: 200px;
    transition: border-color 0.2s ease;
}

.form-textarea:focus {
    outline: none;
    border-color: var(--color-accent);
    box-shadow: 0 0 0 2px rgba(var(--color-accent-rgb), 0.2);
}

.form-hint {
    font-size: 0.85em;
    color: var(--color-fg-contrast-7-5);
    margin-top: 4px;
}

.recipient-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background-color: var(--color-bg);
    border: 1px solid var(--color-fg-contrast-4-5);
    border-top: none;
    border-radius: 0 0 4px 4px;
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
}

.suggestion-item {
    padding: 10px 12px;
    cursor: pointer;
    border-bottom: 1px solid var(--color-border);
    transition: background-color 0.2s ease;
}

.suggestion-item:last-child {
    border-bottom: none;
}

.suggestion-item:hover {
    background-color: rgba(255, 255, 255, 0.05);
}

.recipient-info {
    margin-top: 8px;
}

.recipient-status {
    font-size: 0.9em;
    color: var(--color-accent);
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 30px;
}

.btn {
    padding: 10px 20px;
    border-radius: 4px;
    font-size: 1em;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    display: inline-block;
    text-align: center;
    transition: all 0.2s ease;
}

.btn-primary {
    background-color: var(--color-accent);
    color: white;
}

.btn-primary:hover {
    background-color: var(--color-accent-hover);
}

.btn-secondary {
    background-color: transparent;
    color: var(--color-fg-contrast-10);
    border: 1px solid var(--color-fg-contrast-4-5);
}

.btn-secondary:hover {
    background-color: rgba(255, 255, 255, 0.05);
    color: var(--color-fg);
}

.compose-help {
    background-color: rgba(255, 255, 255, 0.02);
    border: 1px solid var(--color-fg-contrast-4-5);
    border-radius: 6px;
    padding: 20px;
    font-size: 0.9em;
}

.compose-help h3 {
    margin: 0 0 15px 0;
    font-size: 1.1em;
    color: var(--color-fg);
}

.compose-help ul {
    margin: 0;
    padding-left: 20px;
}

.compose-help li {
    margin-bottom: 8px;
    color: var(--color-fg-contrast-10);
    line-height: 1.5;
}

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
    border-bottom: 1px solid var(--color-border);
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
    border-bottom: 1px solid var(--color-border);
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

@media (max-width: 768px) {
    .compose-form-container {
        grid-template-columns: 1fr;
        gap: 20px;
        margin: 0 15px;
    }
    
    .compose-form {
        padding: 20px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        text-align: center;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Recipient username autocomplete
    const recipientInput = document.getElementById('recipient_username');
    const suggestionsDiv = document.getElementById('recipient-suggestions');
    let debounceTimer;

    recipientInput.addEventListener('input', function() {
        const query = this.value.trim();
        
        clearTimeout(debounceTimer);
        
        if (query.length < 2) {
            suggestionsDiv.style.display = 'none';
            return;
        }

        debounceTimer = setTimeout(() => {
            fetchUserSuggestions(query);
        }, 300);
    });

    function fetchUserSuggestions(query) {
        // TODO: Implement user search endpoint
        // For now, just hide suggestions
        suggestionsDiv.style.display = 'none';
    }

    // Hide suggestions when clicking outside
    document.addEventListener('click', function(event) {
        if (!recipientInput.contains(event.target) && !suggestionsDiv.contains(event.target)) {
            suggestionsDiv.style.display = 'none';
        }
    });

    // Markdown help modal
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

    // Character count for textarea
    const bodyTextarea = document.getElementById('body');
    const maxLength = 65535;
    
    function updateCharCount() {
        const currentLength = bodyTextarea.value.length;
        const remaining = maxLength - currentLength;
        
        let hint = bodyTextarea.parentElement.querySelector('.form-hint');
        if (remaining < 1000) {
            hint.innerHTML = `Markdown formatting supported. ${remaining} characters remaining.`;
            if (remaining < 100) {
                hint.style.color = 'var(--color-error)';
            } else {
                hint.style.color = 'var(--color-warning)';
            }
        } else {
            hint.innerHTML = 'Markdown formatting supported. Messages are limited to 65,535 characters.';
            hint.style.color = 'var(--color-fg-contrast-7-5)';
        }
    }

    bodyTextarea.addEventListener('input', updateCharCount);
    updateCharCount(); // Initial count
});
</script>