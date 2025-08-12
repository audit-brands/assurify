<?php

$this->layout('layout', ['title' => $title]) ?>

<div class="auth-form">
    <h1>You're Invited!</h1>
    
    <div class="invitation-info">
        <p><strong><?=$this->e($inviter->username)?></strong> has invited you to join Lobsters.</p>
        <?php if (!empty($invitation->memo)) : ?>
            <blockquote>
                <?=$this->e($invitation->memo)?>
            </blockquote>
        <?php endif ?>
    </div>
    
    <?php if ($error) : ?>
        <div class="error-message">
            <?=$this->e($error)?>
        </div>
    <?php endif ?>
    
    <form action="/auth/signup" method="post">
        <input type="hidden" name="invitation_code" value="<?=$this->e($invitation->code)?>">
        
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" name="username" id="username" required maxlength="50" pattern="[a-zA-Z0-9_-]{3,50}">
            <small>3-50 characters, letters, numbers, underscores, and dashes only</small>
        </div>

        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" name="email" id="email" required value="<?=$this->e($invitation->email)?>" readonly>
            <small>This email was invited by <?=$this->e($inviter->username)?></small>
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
            <button type="submit">Create Account</button>
        </div>
    </form>

    <p>
        Already have an account? <a href="/auth/login">Login</a>
    </p>
</div>