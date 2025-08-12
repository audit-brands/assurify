<?php

$this->layout('layout', ['title' => $title]) ?>

<div class="auth-form">
    <h1>Sign Up</h1>
    
    <?php if ($error) : ?>
        <div class="error-message">
            <?=$this->e($error)?>
        </div>
    <?php endif ?>
    
    <p><strong>Note:</strong> Registration requires an invitation code. If you don't have one, you'll need to ask an existing member to invite you.</p>
    
    <form action="/auth/signup" method="post">
        <div class="form-group">
            <label for="invitation_code">Invitation Code:</label>
            <input type="text" name="invitation_code" id="invitation_code" required placeholder="Enter your invitation code">
        </div>

        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" name="username" id="username" required maxlength="50" pattern="[a-zA-Z0-9_-]{3,50}">
            <small>3-50 characters, letters, numbers, underscores, and dashes only</small>
        </div>

        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" name="email" id="email" required>
        </div>

        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" name="password" id="password" required minlength="8">
            <small>At least 8 characters</small>
        </div>

        <div class="form-group">
            <label for="password_confirm">Confirm Password:</label>
            <input type="password" name="password_confirm" id="password_confirm" required>
        </div>

        <div class="form-group">
            <label for="about">About (optional):</label>
            <textarea name="about" id="about" rows="4" placeholder="Tell us a bit about yourself..."></textarea>
        </div>

        <div class="form-actions">
            <button type="submit">Sign Up</button>
        </div>
    </form>

    <p>
        Already have an account? <a href="/auth/login">Login</a>
    </p>
</div>