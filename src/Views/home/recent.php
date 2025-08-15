<?php $this->layout('layout', ['title' => $title]) ?>

<div class="stories">
    <h1>Recent Stories</h1>
    
    <?php if (empty($stories)) : ?>
        <p>No recent stories yet.</p>
    <?php else : ?>
        <?php foreach ($stories as $story) : ?>
            <div class="story">
                <div class="story-content">
                    <div class="title-line">
                        <?php if (isset($_SESSION['user_id'])) : ?>
                            <button class="upvoter" data-story-id="<?=$story['id']?>" data-vote="1" title="Upvote">[<?=$story['score']?>]</button>
                        <?php else : ?>
                            <span class="upvoter-guest" title="Login to vote">[<?=$story['score']?>]</span>
                        <?php endif ?>
                        <a href="<?=$story['url']?>" class="story-title"><?=$this->e($story['title'])?></a>
                        <?php if (!empty($story['tags'])) : ?>
                            <span class="story-tags">
                                <?php foreach ($story['tags'] as $tag) : ?>
                                    <a href="/t/<?=$tag?>" class="tag"><?=$tag?></a>
                                <?php endforeach ?>
                            </span>
                        <?php endif ?>
                        <span class="story-domain"><a href="/s/<?=$story['short_id']?>/<?=$story['slug']?>"><?=$story['domain']?></a></span>
                    </div>
                    <div class="byline">
                        by <a href="/u/<?=$story['username']?>"><?=$story['username']?></a>
                        <?=$story['time_ago']?>
                        | <a href="/s/<?=$story['short_id']?>/<?=$story['slug']?>"><?=$story['comments_count']?> comments</a>
                    </div>
                </div>
            </div>
        <?php endforeach ?>
    <?php endif ?>
    
    <?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
        <div class="pagination">
            <?php if ($pagination['has_prev']): ?>
                <a href="<?= $pagination['base_url'] ?>?page=<?= $pagination['current_page'] - 1 ?>">&lt;&lt; Page <?= $pagination['current_page'] - 1 ?></a>
            <?php endif ?>
            
            <?php if ($pagination['has_prev'] && $pagination['has_next']): ?>
                 | 
            <?php endif ?>
            
            <?php if ($pagination['has_next']): ?>
                <a href="<?= $pagination['base_url'] ?>?page=<?= $pagination['current_page'] + 1 ?>">Page <?= $pagination['current_page'] + 1 ?> &gt;&gt;</a>
            <?php endif ?>
        </div>
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