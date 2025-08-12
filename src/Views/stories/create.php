<?php

$this->layout('layout', ['title' => $title]) ?>

<div class="story-form">
    <h1>Submit a Story</h1>
    
    <form action="/stories" method="post">
        <div class="form-group">
            <label for="title">Title:</label>
            <input type="text" name="title" id="title" required maxlength="150">
        </div>

        <div class="form-group">
            <label for="url">URL:</label>
            <input type="url" name="url" id="url" placeholder="https://example.com">
            <small>Leave blank to submit a text post</small>
        </div>

        <div class="form-group">
            <label for="description">Text:</label>
            <textarea name="description" id="description" rows="10" placeholder="Optional description or text content"></textarea>
            <small>Supports Markdown formatting</small>
        </div>

        <div class="form-group">
            <label for="tags">Tags:</label>
            <input type="text" name="tags" id="tags" placeholder="programming, web, javascript">
            <small>Comma-separated tags</small>
        </div>

        <div class="form-actions">
            <button type="submit">Submit Story</button>
            <a href="/" class="cancel">Cancel</a>
        </div>
    </form>
</div>