<?php $this->layout('layout', ['title' => $title]) ?>

<div class="tag-page">
    <div class="stories">
        <h1>Stories tagged as <?=$this->e($tag)?></h1>
        
        <?php if ($tag_info['description']) : ?>
            <p class="tag-description"><?=$this->e($tag_info['description'])?></p>
        <?php endif ?>
        
        <?php if ($tag_info['privileged']) : ?>
            <p><em>Use when <?=$this->e($tag_info['description'])?></em></p>
        <?php endif ?>

        <?php if (!empty($related_tags)) : ?>
            <p class="related-tags-text">
                Most often also tagged with 
                <?php 
                $tagLinks = [];
                foreach (array_slice($related_tags, 0, 8) as $relatedTag) {
                    $tagLinks[] = '<a href="/t/' . $this->e($relatedTag['tag']) . '">' . $this->e($relatedTag['tag']) . '</a>';
                }
                echo implode(' ', $tagLinks);
                ?>
            </p>
        <?php endif ?>
    
    <!-- Stories List -->
    <div class="stories-list">
        <?php if (empty($stories)) : ?>
            <div class="no-stories">
                <p>No stories found with this tag<?= $current_timeframe !== 'all' ? ' in the selected time period' : '' ?>.</p>
                <?php if ($current_timeframe !== 'all') : ?>
                    <p><a href="/t/<?=$tag?>">View all time</a></p>
                <?php endif ?>
            </div>
        <?php else : ?>
            <?php foreach ($stories as $story) : ?>
                <div class="story">
                    <div class="story-voting">
                        <?php if (isset($_SESSION['user_id'])) : ?>
                            <button class="upvoter" data-story-id="<?=$story['id']?>" data-vote="1" title="Upvote"></button>
                            <span class="vote-score"><?=$story['score']?></span>
                        <?php else : ?>
                            <span class="upvoter-guest" title="Login to vote"></span>
                            <span class="vote-score-guest"><?=$story['score']?></span>
                        <?php endif ?>
                    </div>
                    
                    <div class="story-content">
                        <h3 class="story-title">
                            <?php if ($story['url']) : ?>
                                <a href="<?=$this->e($story['url'])?>" target="_blank" rel="noopener noreferrer">
                                    <?=$this->e($story['title'])?>
                                </a>
                                <?php if ($story['tags']) : ?>
                                    <span class="story-tags">
                                        <?php foreach ($story['tags'] as $tag_name) : ?>
                                            <a href="/t/<?=$this->e($tag_name)?>" class="tag <?=$tag_name === $tag ? 'current-tag' : ''?>"><?=$this->e($tag_name)?></a>
                                        <?php endforeach ?>
                                    </span>
                                <?php endif ?>
                                <span class="story-domain">(<?=$this->e($story['domain'])?>)</span>
                            <?php else : ?>
                                <a href="/s/<?=$this->e($story['short_id'])?>/<?=$this->e($story['slug'])?>">
                                    <?=$this->e($story['title'])?>
                                </a>
                                <?php if ($story['tags']) : ?>
                                    <span class="story-tags">
                                        <?php foreach ($story['tags'] as $tag_name) : ?>
                                            <a href="/t/<?=$this->e($tag_name)?>" class="tag <?=$tag_name === $tag ? 'current-tag' : ''?>"><?=$this->e($tag_name)?></a>
                                        <?php endforeach ?>
                                    </span>
                                <?php endif ?>
                            <?php endif ?>
                        </h3>
                        <div class="story-domain">
                            <a href="/s/<?=$this->e($story['short_id'])?>/<?=$this->e($story['slug'])?>">(<?=$this->e($story['domain'])?>)</a>
                        </div>
                    </div>
                    <div class="story-details">
                        <span class="story-points"><?=$story['score']?> points</span>
                        by <a href="/u/<?=$this->e($story['username'])?>"><?=$this->e($story['username'])?></a>
                        <?=$story['time_ago']?>
                        | <a href="/s/<?=$this->e($story['short_id'])?>/<?=$this->e($story['slug'])?>"><?=$story['comments_count']?> comments</a>
                    </div>
                </div>
            </div>
            <?php endforeach ?>
        <?php endif ?>
    </div>
</div>

<script>
// Handle story voting (upvote only - Lobsters style)
document.addEventListener('DOMContentLoaded', function() {
    // Handle guest upvoter clicks - redirect to login
    document.querySelectorAll('.upvoter-guest').forEach(element => {
        element.addEventListener('click', function() {
            window.location.href = '/auth/login';
        });
    });

    // Handle authenticated user voting
    document.querySelectorAll('.upvoter').forEach(button => {
        button.addEventListener('click', function() {
            const storyId = this.dataset.storyId;
            const vote = parseInt(this.dataset.vote);
            
            fetch(`/stories/${storyId}/vote`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ vote: vote })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the score display
                    const scoreElement = this.parentElement.querySelector('.vote-score');
                    if (scoreElement) {
                        scoreElement.textContent = data.score;
                    }
                    
                    // Update button state (add/remove upvoted class)
                    const votingDiv = this.parentElement;
                    if (data.voted) {
                        votingDiv.classList.add('upvoted');
                    } else {
                        votingDiv.classList.remove('upvoted');
                    }
                } else {
                    alert(data.error || 'Failed to vote');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to vote');
            });
        });
    });
});
</script>