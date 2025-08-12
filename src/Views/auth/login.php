<?php

$this->layout('layout', ['title' => $title]) ?>

<div class="auth-form">
    <h1>Login</h1>
    
    <?php if ($error) : ?>
        <div class="error-message">
            <?=$this->e($error)?>
        </div>
    <?php endif ?>
    
    <form action="/auth/login" method="post">
        <div class="form-group">
            <label for="username">Username or Email:</label>
            <input type="text" name="username" id="username" required>
        </div>

        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" name="password" id="password" required>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" name="remember_me" value="1">
                Remember me
            </label>
        </div>

        <div class="form-actions">
            <button type="submit">Login</button>
        </div>
    </form>

    <p>
        Don't have an account? <a href="/auth/signup">Sign up</a><br>
        <a href="/auth/forgot-password">Forgot your password?</a>
    </p>
</div>