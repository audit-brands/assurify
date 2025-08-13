<?php $this->layout('layout', ['title' => $title]) ?>

<div class="user-not-found">
    <h1>User Not Found</h1>
    <p>The user "<?=$this->e($username)?>" does not exist.</p>
    <p><a href="/">← Back to homepage</a></p>
</div>