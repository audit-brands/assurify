<?php $this->layout('layout', ['title' => $title]) ?>

<div class="stories">
    <h1><?=$this->e($section_header ?? 'Stories')?></h1>
    
    <?php if ($success) : ?>
        <div class="success-message">
            <?=$this->e($success)?>
        </div>
    <?php endif ?>
    
    <?php if (empty($stories)) : ?>
        <p>No stories yet. <a href="/stories">Submit the first one!</a></p>
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
                    <div class="story-title">
                        <h2>
                            <a href="<?=$story['url']?>"><?=$this->e($story['title'])?></a>
                            <?php if (!empty($story['tags'])) : ?>
                                <span class="story-tags">
                                    <?php foreach ($story['tags'] as $tag) : ?>
                                        <a href="/t/<?=$tag?>" class="tag"><?=$tag?></a>
                                    <?php endforeach ?>
                                </span>
                            <?php endif ?>
                        </h2>
                        <div class="story-domain">
                            <a href="/s/<?=$story['short_id']?>/<?=$story['slug']?>">(<?=$story['domain']?>)</a>
                        </div>
                    </div>
                    <div class="story-details">
                        <span class="story-points"><?=$story['score']?> points</span>
                        by <a href="/u/<?=$story['username']?>"><?=$story['username']?></a>
                        <?=$story['time_ago']?>
                        | <a href="/s/<?=$story['short_id']?>/<?=$story['slug']?>"><?=$story['comments_count']?> comments</a>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    <?php endif ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle guest upvoter clicks - redirect to login
    document.querySelectorAll('.upvoter-guest').forEach(element => {
        element.addEventListener('click', function() {
            window.location.href = '/auth/login';
        });
        // Make it look clickable
        element.style.cursor = 'pointer';
    });

    // Handle story voting (upvote only - Lobsters style) for authenticated users
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
                    
                    // Update the points display in story details
                    const pointsElement = this.parentElement.parentElement.querySelector('.story-points');
                    if (pointsElement) {
                        pointsElement.textContent = data.score + ' points';
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