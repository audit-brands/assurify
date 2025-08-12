<?php

$this->layout('layout', ['title' => $title]) ?>

<div class="comment-page">
    <div class="comment-context">
        <p>Comment on: <a href="/s/<?=$story->short_id?>/<?=$this->e($story->title)?>"><?=$this->e($story->title)?></a></p>
    </div>
    
    <div class="comment-display">
        <?=$this->insert('comments/_comment', ['comment' => $comment])?>
    </div>
    
    <div class="navigation">
        <a href="/s/<?=$story->short_id?>/<?=$this->e($story->title)?>#comment-<?=$comment['short_id']?>">← Back to story</a>
    </div>
</div>