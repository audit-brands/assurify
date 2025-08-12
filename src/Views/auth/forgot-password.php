<?php

$this->layout('layout', ['title' => $title]) ?>

<div class="auth-form">
    <h1>Forgot Password</h1>
    
    <?php if ($error) : ?>
        <div class="error-message">
            <?=$this->e($error)?>
        </div>
    <?php endif ?>
    
    <?php if ($message) : ?>
        <div class="success-message">
            <?=$this->e($message)?>
        </div>
    <?php endif ?>
    
    <p>Enter your email address and we'll send you instructions to reset your password.</p>
    
    <form action="/auth/forgot-password" method="post">
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" name="email" id="email" required>
        </div>

        <div class="form-actions">
            <button type="submit">Send Reset Instructions</button>
        </div>
    </form>

    <p>
        Remember your password? <a href="/auth/login">Login</a>
    </p>
</div>