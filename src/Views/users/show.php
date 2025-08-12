<?php $this->layout('layout', ['title' => $title]) ?>

<div class="user-profile">
    <?php if ($user) : ?>
        <div class="user-header">
            <h1><?=$this->e($user['username'])?></h1>
            <div class="user-stats">
                <span class="karma">Karma: <?=$user['karma']?></span>
                <span class="member-since">Member since: <?=$user['created_at']?></span>
            </div>
        </div>

        <?php if ($user['about']) : ?>
            <div class="user-about">
                <h3>About</h3>
                <p><?=$this->e($user['about'])?></p>
            </div>
        <?php endif ?>

        <div class="user-activity">
            <h3>Recent Stories</h3>
            <?php if (empty($stories)) : ?>
                <p>No stories submitted yet.</p>
            <?php else : ?>
                <div class="stories">
                    <?php foreach ($stories as $story) : ?>
                        <div class="story">
                            <h4><a href="/s/<?=$story['id']?>/<?=$story['slug']?>"><?=$this->e($story['title'])?></a></h4>
                            <div class="story-details">
                                <?=$story['score']?> points | <?=$story['comments_count']?> comments | <?=$story['time_ago']?>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>

            <h3>Recent Comments</h3>
            <?php if (empty($comments)) : ?>
                <p>No comments posted yet.</p>
            <?php else : ?>
                <div class="comments">
                    <?php foreach ($comments as $comment) : ?>
                        <div class="comment">
                            <div class="comment-story">
                                On: <a href="/s/<?=$comment['story_id']?>/<?=$comment['story_slug']?>"><?=$this->e($comment['story_title'])?></a>
                            </div>
                            <div class="comment-content">
                                <?=$comment['content']?>
                            </div>
                            <div class="comment-details">
                                <?=$comment['score']?> points | <?=$comment['time_ago']?>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>
    <?php else : ?>
        <h1>User not found</h1>
        <p>The requested user does not exist.</p>
    <?php endif ?>
</div>