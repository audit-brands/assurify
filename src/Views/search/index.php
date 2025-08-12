<?php $this->layout('layout', ['title' => $title]) ?>

<div class="search-page">
    <h1>Search</h1>
    
    <form action="/search" method="get" class="search-form">
        <div class="form-group">
            <input type="text" name="q" value="<?=$this->e($query)?>" placeholder="Search stories and comments..." class="search-input" autocomplete="off">
            <button type="submit">Search</button>
        </div>
        
        <div class="search-options">
            <label>
                <select name="what">
                    <option value="all" <?= $type === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="stories" <?= $type === 'stories' ? 'selected' : '' ?>>Stories</option>
                    <option value="comments" <?= $type === 'comments' ? 'selected' : '' ?>>Comments</option>
                </select>
            </label>
            
            <label>
                Sort by:
                <select name="order">
                    <option value="newest" <?= $order === 'newest' ? 'selected' : '' ?>>Newest</option>
                    <option value="relevance" <?= $order === 'relevance' ? 'selected' : '' ?>>Relevance</option>
                    <option value="score" <?= $order === 'score' ? 'selected' : '' ?>>Score</option>
                </select>
            </label>
        </div>
    </form>
    
    <?php if (!$has_searched && !empty($popular_searches)) : ?>
        <div class="popular-searches">
            <h3>Popular searches:</h3>
            <div class="search-tags">
                <?php foreach ($popular_searches as $search) : ?>
                    <a href="/search?q=<?= urlencode($search) ?>" class="search-tag"><?= $this->e($search) ?></a>
                <?php endforeach ?>
            </div>
        </div>
    <?php endif ?>
    
    <?php if ($has_searched) : ?>
        <div class="search-results">
            <div class="search-meta">
                <h2>
                    <?php if ($total > 0) : ?>
                        <?= number_format($total) ?> result<?= $total !== 1 ? 's' : '' ?> for "<?=$this->e($query)?>"
                    <?php else : ?>
                        No results for "<?=$this->e($query)?>"
                    <?php endif ?>
                </h2>
            </div>
            
            <?php if (empty($results)) : ?>
                <div class="no-results">
                    <p>No results found for your search.</p>
                    <p>Try:</p>
                    <ul>
                        <li>Using different keywords</li>
                        <li>Checking your spelling</li>
                        <li>Using more general terms</li>
                        <li>Searching for stories or comments specifically</li>
                    </ul>
                </div>
            <?php else : ?>
                <div class="results-list">
                    <?php foreach ($results as $result) : ?>
                        <div class="search-result">
                            <?php if ($result['type'] === 'story') : ?>
                                <div class="story-result">
                                    <h3>
                                        <a href="/s/<?=$result['short_id']?>"><?=$this->e($result['title'])?></a>
                                        <?php if ($result['domain']) : ?>
                                            <span class="domain">(<?=$this->e($result['domain'])?>)</span>
                                        <?php endif ?>
                                    </h3>
                                    <div class="result-details">
                                        <span class="score"><?=$result['score']?> points</span>
                                        by <a href="/u/<?=$this->e($result['user']['username'])?>"><?=$this->e($result['user']['username'])?></a>
                                        <span class="time"><?=$result['time_ago']?></span>
                                        <?php if ($result['comment_count'] > 0) : ?>
                                            | <span class="comments"><?=$result['comment_count']?> comment<?= $result['comment_count'] !== 1 ? 's' : '' ?></span>
                                        <?php endif ?>
                                    </div>
                                    <?php if (!empty($result['tags'])) : ?>
                                        <div class="result-tags">
                                            <?php foreach ($result['tags'] as $tag) : ?>
                                                <a href="/t/<?=$this->e($tag)?>" class="tag"><?=$this->e($tag)?></a>
                                            <?php endforeach ?>
                                        </div>
                                    <?php endif ?>
                                    <?php if ($result['description']) : ?>
                                        <div class="result-excerpt">
                                            <?= $this->e(substr(strip_tags($result['description']), 0, 200)) ?><?= strlen($result['description']) > 200 ? '...' : '' ?>
                                        </div>
                                    <?php endif ?>
                                </div>
                            <?php elseif ($result['type'] === 'comment') : ?>
                                <div class="comment-result">
                                    <h3>
                                        <a href="/s/<?=$result['story']['short_id']?>#comment-<?=$result['short_id']?>">
                                            Comment on: <?=$this->e($result['story']['title'])?>
                                        </a>
                                    </h3>
                                    <div class="result-details">
                                        <span class="score"><?=$result['score']?> points</span>
                                        by <a href="/u/<?=$this->e($result['user']['username'])?>"><?=$this->e($result['user']['username'])?></a>
                                        <span class="time"><?=$result['time_ago']?></span>
                                    </div>
                                    <div class="result-excerpt">
                                        <?= $this->e(substr(strip_tags($result['comment']), 0, 200)) ?><?= strlen($result['comment']) > 200 ? '...' : '' ?>
                                    </div>
                                </div>
                            <?php endif ?>
                        </div>
                    <?php endforeach ?>
                </div>
                
                <?php if ($total > $per_page) : ?>
                    <div class="pagination">
                        <?php
                        $totalPages = min(ceil($total / $per_page), 20); // Max 20 pages
                        $currentPage = $page;
                        $baseUrl = "/search?" . http_build_query(['q' => $query, 'what' => $type, 'order' => $order]);
                        ?>
                        
                        <?php if ($currentPage > 1) : ?>
                            <a href="<?= $baseUrl ?>&page=<?= $currentPage - 1 ?>" class="pagination-link">← Previous</a>
                        <?php endif ?>
                        
                        <?php for ($i = max(1, $currentPage - 3); $i <= min($totalPages, $currentPage + 3); $i++) : ?>
                            <?php if ($i === $currentPage) : ?>
                                <span class="pagination-current"><?= $i ?></span>
                            <?php else : ?>
                                <a href="<?= $baseUrl ?>&page=<?= $i ?>" class="pagination-link"><?= $i ?></a>
                            <?php endif ?>
                        <?php endfor ?>
                        
                        <?php if ($currentPage < $totalPages) : ?>
                            <a href="<?= $baseUrl ?>&page=<?= $currentPage + 1 ?>" class="pagination-link">Next →</a>
                        <?php endif ?>
                    </div>
                <?php endif ?>
            <?php endif ?>
        </div>
    <?php endif ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit form when options change
    const form = document.querySelector('.search-form');
    const selects = form.querySelectorAll('select');
    
    selects.forEach(select => {
        select.addEventListener('change', function() {
            if (form.querySelector('input[name="q"]').value.trim()) {
                form.submit();
            }
        });
    });
});
</script>