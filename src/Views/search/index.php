<?php $this->layout('layout', ['title' => $title]) ?>

<div class="search-page">
    <h1>Search</h1>
    
    <form action="/search" method="get" class="search-form">
        <div class="form-group">
            <input type="text" name="q" value="<?=$this->e($query)?>" placeholder="Search stories and comments..." class="search-input">
            <button type="submit">Search</button>
        </div>
    </form>
    
    <?php if (!empty($query)) : ?>
        <div class="search-results">
            <h2>Search Results for "<?=$this->e($query)?>"</h2>
            
            <?php if (empty($results)) : ?>
                <p>No results found for your search.</p>
            <?php else : ?>
                <?php foreach ($results as $result) : ?>
                    <div class="search-result">
                        <?php if ($result['type'] === 'story') : ?>
                            <div class="story-result">
                                <h3><a href="/s/<?=$result['id']?>/<?=$result['slug']?>"><?=$this->e($result['title'])?></a></h3>
                                <div class="result-details">
                                    <?=$result['score']?> points by <a href="/u/<?=$result['username']?>"><?=$result['username']?></a>
                                    <?=$result['time_ago']?> | <?=$result['comments_count']?> comments
                                </div>
                                <?php if ($result['description']) : ?>
                                    <div class="result-excerpt">
                                        <?=substr(strip_tags($result['description']), 0, 200)?>...
                                    </div>
                                <?php endif ?>
                            </div>
                        <?php elseif ($result['type'] === 'comment') : ?>
                            <div class="comment-result">
                                <h3><a href="/s/<?=$result['story_id']?>/<?=$result['story_slug']?>#comment_<?=$result['id']?>">Comment on: <?=$this->e($result['story_title'])?></a></h3>
                                <div class="result-details">
                                    by <a href="/u/<?=$result['username']?>"><?=$result['username']?></a>
                                    <?=$result['time_ago']?>
                                </div>
                                <div class="result-excerpt">
                                    <?=substr(strip_tags($result['comment']), 0, 200)?>...
                                </div>
                            </div>
                        <?php endif ?>
                    </div>
                <?php endforeach ?>
            <?php endif ?>
        </div>
    <?php endif ?>
</div>