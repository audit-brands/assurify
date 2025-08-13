<?php $this->layout('layout', ['title' => $title]) ?>

<div class="story-form">
    <h1>Recent Comments</h1>
    
    <?php if (empty($comments)) : ?>
        <p>No comments yet.</p>
    <?php else : ?>
        <div class="comments">
            <?php foreach ($comments as $comment) : ?>
                <div class="comment">
                    <div class="comment-meta">
                        <a href="/u/<?=$this->e($comment['username'])?>"><?=$this->e($comment['username'])?></a>
                        <?=$comment['time_ago']?>
                        on <a href="/s/<?=$comment['story_short_id']?>/<?=$comment['story_slug']?>"><?=$this->e($comment['story_title'])?></a>
                    </div>
                    <div class="comment-content">
                        <?=$comment['content']?>
                    </div>
                    <div class="comment-score">
                        <?=$comment['score']?> points
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>