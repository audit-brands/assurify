<?php $this->layout('layout', ['title' => $title]) ?>

<div class="tags-page">
    <h1>Tags</h1>
    
    <p>You can <a href="/filter">filter out stories by tag</a>, even if you don't have an account.</p>
    
    <?php if (!empty($popular_tags)): ?>
        <h2>Most Popular</h2>
        <div class="popular-tags">
            <?php foreach ($popular_tags as $tag): ?>
                <a href="/t/<?= $this->e($tag['tag']) ?>" class="tag popular-tag">
                    <?= $this->e($tag['tag']) ?>
                    <?php if (!empty($tag['story_count'])): ?>
                        <span class="count">(<?= $tag['story_count'] ?>)</span>
                    <?php endif ?>
                </a>
            <?php endforeach ?>
        </div>
    <?php endif ?>
    
    <?php if (!empty($categorized_tags)): ?>
        <?php foreach ($categorized_tags as $categoryName => $categoryTags): ?>
            <h2 id="<?= strtolower(str_replace(' ', '_', $categoryName)) ?>"><?= $this->e($categoryName) ?></h2>
            <ol class="category_tags">
                <?php foreach ($categoryTags as $tag): ?>
                    <li id="<?= $this->e($tag['tag']) ?>">
                        <a href="/t/<?= $this->e($tag['tag']) ?>" class="tag"><?= $this->e($tag['tag']) ?></a>
                        <span><?= $this->e($tag['description'] ?? 'No description available') ?></span>
                        <span class="byline">
                            <?php if (!empty($tag['story_count'])): ?>
                                | <?= $tag['story_count'] ?> stories
                            <?php endif ?>
                        </span>
                    </li>
                <?php endforeach ?>
            </ol>
        <?php endforeach ?>
    <?php endif ?>
</div>

<style>
/* Lobste.rs-style tag page styling */
.tags-page {
    max-width: 800px;
    margin: 0 auto;
    padding: 1em;
}

.category_tags {
    list-style: none;
    padding: 0;
    margin: 0 0 2em 0;
}

.category_tags li {
    margin: 0.2em 0;
    padding: 0;
}

.category_tags .tag {
    font-weight: bold;
    margin-right: 0.5em;
}

.category_tags .byline {
    color: var(--color-fg-contrast-4-5, #999);
    font-size: 9.5pt;
}

.popular-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5em;
    margin: 1em 0;
}

.popular-tag {
    display: inline-block;
    padding: 0.3em 0.6em;
    background: #f5f5f5;
    border: 1px solid #ddd;
    border-radius: 3px;
    text-decoration: none;
    font-size: 0.9em;
}

.popular-tag:hover {
    background: #e5e5e5;
}

.popular-tag .count {
    color: #666;
    font-size: 0.8em;
}
</style>