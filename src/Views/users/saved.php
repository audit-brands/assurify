<?php $this->layout('layout', ['title' => $title]) ?>

<div class="user-profile">
    <div class="user-header">
        <div class="user-basic-info">
            <h1 class="username">
                <?=$this->e($user['username'])?>'s Saved Stories
            </h1>
        </div>
    </div>

    <div class="user-activity">
        <nav class="activity-tabs">
            <a href="/u/<?=$this->e($user['username'])?>?tab=stories" class="tab">
                Stories
            </a>
            <a href="/u/<?=$this->e($user['username'])?>?tab=comments" class="tab">
                Comments
            </a>
            <a href="/u/<?=$this->e($user['username'])?>/saved" class="tab active">
                Saved Stories
            </a>
        </nav>

        <div class="activity-content">
            <div class="stories-section">
                <?php if (empty($stories)) : ?>
                    <p class="no-content">No saved stories yet.</p>
                <?php else : ?>
                    <div class="stories-list">
                        <?php foreach ($stories as $story) : ?>
                            <div class="story-item">
                                <div class="story-score"><?=$story['score']?></div>
                                <div class="story-content">
                                    <h3 class="story-title">
                                        <?php if ($story['url']) : ?>
                                            <a href="<?=$this->e($story['url'])?>" target="_blank" rel="noopener">
                                                <?=$this->e($story['title'])?>
                                            </a>
                                            <span class="story-domain">(<?=$this->e($story['domain'])?>)</span>
                                        <?php else : ?>
                                            <a href="/s/<?=$story['short_id']?>/<?=$story['slug']?>">
                                                <?=$this->e($story['title'])?>
                                            </a>
                                        <?php endif ?>
                                    </h3>
                                    <div class="story-meta">
                                        <a href="/s/<?=$story['short_id']?>/<?=$story['slug']?>"><?=$story['comment_count']?> comments</a>
                                        | <?=$story['time_ago']?>
                                        <?php if (isset($story['saved_at'])) : ?>
                                            | saved <?=$story['saved_at']?>
                                        <?php endif ?>
                                    </div>
                                    <?php if (!empty($story['tags'])) : ?>
                                        <div class="story-tags">
                                            <?php foreach ($story['tags'] as $tag) : ?>
                                                <a href="/t/<?=$this->e($tag)?>" class="tag"><?=$this->e($tag)?></a>
                                            <?php endforeach ?>
                                        </div>
                                    <?php endif ?>
                                    <?php if ($story['description']) : ?>
                                        <div class="story-excerpt">
                                            <?= $this->e(substr(strip_tags($story['description']), 0, 200)) ?><?= strlen($story['description']) > 200 ? '...' : '' ?>
                                        </div>
                                    <?php endif ?>
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>