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
                <div class="voters">
                    <?php if (isset($_SESSION['user_id'])) : ?>
                        <button class="upvoter" data-story-id="<?=$story['id']?>" data-vote="1" title="Upvote"></button>
                    <?php else : ?>
                        <span class="upvoter-guest" title="Login to vote"></span>
                    <?php endif ?>
                    <span class="score"><?=$story['score']?></span>
                </div>
                
                <div class="story-content">
                    <div class="title-line">
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
                        
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $story['user_id'] && !($story['is_deleted'] ?? false)) : ?>
                            | <a href="#" class="user-delete-link" data-story-id="<?=$story['short_id']?>" 
                               onclick="return confirm('Are you sure you want to delete this story? This action cannot be undone.')">delete</a>
                        <?php endif ?>
                        
                        <?php if ($is_moderator ?? false) : ?>
                            | <a href="/mod/stories/<?=$story['short_id']?>/edit" class="mod-link">mod edit</a>
                            <?php if ($story['is_deleted'] ?? false) : ?>
                                | <span class="mod-status deleted">deleted</span>
                            <?php endif ?>
                        <?php endif ?>
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
        element.addEventListener('click', function(e) {
            console.log('Guest upvoter clicked - redirecting to login');
            e.preventDefault();
            e.stopPropagation();
            window.location.href = '/auth/login';
        });
        // Make it look clickable
        element.style.cursor = 'pointer';
    });

    // Handle story voting (upvote only - Lobsters style) for authenticated users
    document.querySelectorAll('.upvoter[data-story-id]').forEach(button => {
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
                    const scoreElement = this.parentElement.querySelector('.score');
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

    // Handle user story deletion
    document.querySelectorAll('.user-delete-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const storyId = this.dataset.storyId;
            
            // Confirm deletion
            if (!confirm('Are you sure you want to delete this story? This action cannot be undone.')) {
                return;
            }
            
            // Send DELETE request
            fetch(`/stories/${storyId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Refresh the page to show updated list
                    window.location.reload();
                } else {
                    alert('Error deleting story: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting story. Please try again.');
            });
        });
    });
});
</script>