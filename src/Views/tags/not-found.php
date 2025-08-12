<?php

$this->layout('layout', ['title' => $title]) ?>

<div class="error-page">
    <h1>Tag Not Found</h1>
    <p>The tag "<?=$this->e($tag)?>" could not be found. It may not exist or may have been removed.</p>
    <p><a href="/tags">← View all tags</a> | <a href="/">Home</a></p>
</div>