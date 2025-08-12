<?php

$this->layout('layout', ['title' => $title]) ?>

<div class="auth-form">
    <h1>Invalid Invitation</h1>
    
    <div class="error-message">
        <p>This invitation link is invalid or has already been used.</p>
    </div>
    
    <p>
        If you believe this is an error, please contact the person who sent you the invitation 
        to request a new one.
    </p>
    
    <p>
        <a href="/auth/login">Login</a> if you already have an account, or 
        <a href="/auth/signup">sign up</a> if you have a valid invitation code.
    </p>
</div>