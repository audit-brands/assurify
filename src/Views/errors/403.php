<?php $this->layout('layout', ['title' => $title]) ?>

<div class="error-page">
    <div class="error-content">
        <h1 class="error-code">403</h1>
        <h2 class="error-title">Access Denied</h2>
        <p class="error-message">
            <?= $this->e($message ?? 'You do not have permission to view this page.') ?>
        </p>
        <div class="error-actions">
            <a href="/" class="btn btn-primary">Go Home</a>
            <a href="/auth/login" class="btn btn-secondary">Login</a>
        </div>
    </div>
</div>

<style>
.error-page {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 60vh;
    text-align: center;
}

.error-content {
    max-width: 500px;
    padding: 2rem;
}

.error-code {
    font-size: 4rem;
    font-weight: bold;
    color: #dc3545;
    margin-bottom: 0.5rem;
}

.error-title {
    font-size: 2rem;
    color: #333;
    margin-bottom: 1rem;
}

.error-message {
    font-size: 1.1rem;
    color: #666;
    margin-bottom: 2rem;
    line-height: 1.5;
}

.error-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.btn {
    padding: 0.75rem 1.5rem;
    text-decoration: none;
    border-radius: 0.25rem;
    font-weight: 500;
    transition: all 0.2s;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-primary:hover {
    background-color: #0056b3;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background-color: #545b62;
}
</style>