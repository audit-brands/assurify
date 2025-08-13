<?php $this->layout('layout', ['title' => $title]) ?>

<div class="tags-page">
    <div class="page-header">
        <h1>Tags</h1>
        <p>Browse stories by topic tags and discover trending topics.</p>
    </div>
    
    <!-- Search and Filter Controls -->
    <div class="tags-controls">
        <form class="tag-search-form" method="GET">
            <div class="search-group">
                <input type="text" name="q" placeholder="Search tags..." value="<?=$this->e($search_query)?>" class="tag-search-input">
                <button type="submit" class="search-btn">Search</button>
                <?php if (!empty($search_query)) : ?>
                    <a href="/tags" class="clear-search">Clear</a>
                <?php endif ?>
            </div>
        </form>
        
        <div class="sort-controls">
            <label for="sort">Sort by:</label>
            <select name="sort" id="sort" onchange="updateSort(this.value)">
                <option value="alphabetical" <?= $current_sort === 'alphabetical' ? 'selected' : '' ?>>A-Z</option>
                <option value="stories" <?= $current_sort === 'stories' ? 'selected' : '' ?>>Most Stories</option>
                <option value="recent" <?= $current_sort === 'recent' ? 'selected' : '' ?>>Recently Created</option>
            </select>
        </div>
    </div>

    <!-- Trending Tags Section -->
    <?php if (!empty($trending_tags) && empty($search_query)) : ?>
        <section class="trending-tags">
            <h2>Trending This Week</h2>
            <div class="trending-tags-grid">
                <?php foreach ($trending_tags as $tag) : ?>
                    <a href="/t/<?=$tag['tag']?>" class="trending-tag">
                        <span class="tag-name"><?=$this->e($tag['tag'])?></span>
                        <span class="trending-count"><?=$tag['recent_stories']?> stories</span>
                    </a>
                <?php endforeach ?>
            </div>
        </section>
    <?php endif ?>

    <!-- Tag Categories -->
    <?php if (!empty($tag_categories) && empty($search_query)) : ?>
        <section class="tag-categories">
            <h2>Browse by Category</h2>
            <div class="categories-grid">
                <?php foreach ($tag_categories as $categoryName => $categoryData) : ?>
                    <div class="category-card">
                        <h3><?=$this->e($categoryName)?></h3>
                        <p><?=$this->e($categoryData['description'])?></p>
                        <div class="category-tags">
                            <?php foreach ($categoryData['tags'] as $tagName) : ?>
                                <a href="/t/<?=$tagName?>" class="category-tag"><?=$tagName?></a>
                            <?php endforeach ?>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
        </section>
    <?php endif ?>

    <!-- All Tags List -->
    <section class="all-tags">
        <?php if (!empty($search_query)) : ?>
            <h2>Search Results for "<?=$this->e($search_query)?>"</h2>
        <?php else : ?>
            <h2>All Tags</h2>
        <?php endif ?>
        
        <?php if (empty($tags)) : ?>
            <?php if (!empty($search_query)) : ?>
                <p class="no-results">No tags found matching "<?=$this->e($search_query)?>".</p>
            <?php else : ?>
                <p class="no-tags">No tags available yet.</p>
            <?php endif ?>
        <?php else : ?>
            <div class="tags-grid">
                <?php foreach ($tags as $tag) : ?>
                    <div class="tag-card">
                        <div class="tag-header">
                            <h3><a href="/t/<?=$tag['tag']?>" class="tag-link"><?=$this->e($tag['tag'])?></a></h3>
                            <?php if ($tag['privileged']) : ?>
                                <span class="privileged-badge" title="Privileged tag">★</span>
                            <?php endif ?>
                            <?php if ($tag['is_media']) : ?>
                                <span class="media-badge" title="Media tag">📺</span>
                            <?php endif ?>
                        </div>
                        
                        <?php if ($tag['description']) : ?>
                            <p class="tag-description"><?=$this->e($tag['description'])?></p>
                        <?php endif ?>
                        
                        <div class="tag-stats">
                            <span class="story-count">
                                <strong><?=$tag['story_count'] ?? 0?></strong> stories
                            </span>
                            <?php if (($tag['hotness_mod'] ?? 0) != 0) : ?>
                                <span class="hotness-mod" title="Hotness modifier: <?=$tag['hotness_mod']?>">
                                    <?= $tag['hotness_mod'] > 0 ? '🔥' : '❄️' ?>
                                </span>
                            <?php endif ?>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </section>
</div>

<script>
function updateSort(sortValue) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', sortValue);
    window.location.href = url.toString();
}
</script>