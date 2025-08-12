<?php

$this->layout('layout', ['title' => $title]) ?>

<div class="tag-page">
    <div class="tag-header">
        <h1>Stories tagged "<?=$this->e($tag)?>"</h1>
        
        <?php if ($tag_info['description']) : ?>
            <p class="tag-description"><?=$this->e($tag_info['description'])?></p>
        <?php endif ?>
        
        <?php if ($tag_info['privileged']) : ?>
            <div class="tag-notice">
                <em>This is a privileged tag that can only be applied by moderators and high-karma users.</em>
            </div>
        <?php endif ?>
    </div>
    
    <div class="stories-list">
        <?php if (empty($stories)) : ?>
            <p>No stories found with this tag.</p>
        <?php else : ?>
            <?php foreach ($stories as $story) : ?>
                <div class="story-item">
                    <div class="story-voting">
                        <button class="vote-up" data-story-id="<?=$story['id']?>" data-vote="1">▲</button>
                        <div class="score"><?=$story['score']?></div>
                        <button class="vote-down" data-story-id="<?=$story['id']?>" data-vote="-1">▼</button>
                    </div>
                    
                    <div class="story-content">
                        <h3 class="story-title">
                            <?php if ($story['url']) : ?>
                                <a href="<?=$this->e($story['url'])?>" target="_blank" rel="noopener noreferrer">
                                    <?=$this->e($story['title'])?>
                                </a>
                                <span class="story-domain">(<?=$this->e($story['domain'])?>)</span>
                            <?php else : ?>
                                <a href="/s/<?=$this->e($story['short_id'])?>/<?=$this->e($story['slug'])?>">
                                    <?=$this->e($story['title'])?>
                                </a>
                            <?php endif ?>
                        </h3>
                        
                        <div class="story-meta">
                            by <a href="/u/<?=$this->e($story['username'])?>"><?=$this->e($story['username'])?></a>
                            <?=$story['created_at_formatted']?>
                            
                            <?php if ($story['tags']) : ?>
                                <div class="story-tags">
                                    <?php foreach ($story['tags'] as $tag_name) : ?>
                                        <a href="/t/<?=$this->e($tag_name)?>" class="tag <?=$tag_name === $tag ? 'current-tag' : ''?>"><?=$this->e($tag_name)?></a>
                                    <?php endforeach ?>
                                </div>
                            <?php endif ?>
                        </div>
                        
                        <div class="story-actions">
                            <a href="/s/<?=$this->e($story['short_id'])?>/<?=$this->e($story['slug'])?>">discuss</a>
                        </div>
                    </div>
                </div>
            <?php endforeach ?>
        <?php endif ?>
    </div>
    
    <p><a href="/tags">← View all tags</a></p>
</div>