<?php $this->layout('layout', ['title' => $title]) ?>

<div class="tags-page">
    <h1>Tags</h1>
    
    <p>Browse stories by topic tags.</p>
    
    <?php if (empty($tags)) : ?>
        <p>No tags available yet.</p>
    <?php else : ?>
        <div class="tags-list">
            <?php foreach ($tags as $tag) : ?>
                <div class="tag-item">
                    <h3><a href="/t/<?=$tag['tag']?>" class="tag-link"><?=$this->e($tag['tag'])?></a></h3>
                    <?php if ($tag['description']) : ?>
                        <p class="tag-description"><?=$this->e($tag['description'])?></p>
                    <?php endif ?>
                    <div class="tag-stats">
                        <?=$tag['story_count'] ?? 0?> stories
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>