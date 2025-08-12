<?php $this->layout('layout', ['title' => $title]) ?>

<div class="stories">
    <h1>Newest Stories</h1>
    
    <?php if (empty($stories)) : ?>
        <p>No stories yet.</p>
    <?php else : ?>
        <?php foreach ($stories as $story) : ?>
            <div class="story">
                <div class="story-title">
                    <h2><a href="<?=$story['url']?>"><?=$this->e($story['title'])?></a></h2>
                    <div class="story-domain">
                        <a href="/s/<?=$story['id']?>/<?=$story['slug']?>">(<?=$story['domain']?>)</a>
                    </div>
                </div>
                <div class="story-details">
                    <span class="story-score"><?=$story['score']?> points</span>
                    by <a href="/u/<?=$story['username']?>"><?=$story['username']?></a>
                    <?=$story['time_ago']?>
                    | <a href="/s/<?=$story['id']?>/<?=$story['slug']?>"><?=$story['comments_count']?> comments</a>
                </div>
                <?php if (!empty($story['tags'])) : ?>
                    <div class="story-tags">
                        <?php foreach ($story['tags'] as $tag) : ?>
                            <a href="/t/<?=$tag?>" class="tag"><?=$tag?></a>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
            </div>
        <?php endforeach ?>
    <?php endif ?>
</div>