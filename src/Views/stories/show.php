<?php $this->layout('layout', ['title' => $title]) ?>

<div class="story-detail">
    <?php if ($story) : ?>
        <div class="story-header">
            <h1><a href="<?=$story['url']?>"><?=$this->e($story['title'])?></a></h1>
            <div class="story-info">
                <span class="story-score"><?=$story['score']?> points</span>
                by <a href="/u/<?=$story['username']?>"><?=$story['username']?></a>
                <?=$story['time_ago']?>
            </div>
            <?php if (!empty($story['tags'])) : ?>
                <div class="story-tags">
                    <?php foreach ($story['tags'] as $tag) : ?>
                        <a href="/t/<?=$tag?>" class="tag"><?=$tag?></a>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>

        <?php if ($story['description']) : ?>
            <div class="story-description">
                <?=$story['description']?>
            </div>
        <?php endif ?>

        <div class="story-actions">
            <a href="#" class="vote-up">▲</a>
            <a href="#" class="vote-down">▼</a>
        </div>
    <?php else : ?>
        <p>Story not found.</p>
    <?php endif ?>

    <div class="comments-section">
        <h3>Comments (<?=count($comments)?>)</h3>
        
        <?php if (empty($comments)) : ?>
            <p>No comments yet. Be the first to comment!</p>
        <?php else : ?>
            <div class="comments">
                <?php foreach ($comments as $comment) : ?>
                    <div class="comment">
                        <div class="comment-info">
                            <a href="/u/<?=$comment['username']?>"><?=$comment['username']?></a>
                            <?=$comment['time_ago']?>
                            <span class="comment-score"><?=$comment['score']?> points</span>
                        </div>
                        <div class="comment-content">
                            <?=$comment['content']?>
                        </div>
                        <div class="comment-actions">
                            <a href="#" class="vote-up">▲</a>
                            <a href="#" class="vote-down">▼</a>
                            <a href="#">reply</a>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endif ?>

        <form class="comment-form" action="/comments" method="post">
            <div class="form-group">
                <label for="comment">Add a comment:</label>
                <textarea name="comment" id="comment" rows="5" required></textarea>
            </div>
            <button type="submit">Post Comment</button>
        </form>
    </div>
</div>