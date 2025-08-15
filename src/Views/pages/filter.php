<?php $this->layout('layout', ['title' => $title]) ?>

<div class="box wide">
    <p>To hide stories with certain <a href="/tags">tags</a> from across the site, check them below.
    If you don't have an account, this will save to a cookie in your browser.</p>
    
    <?php if (!$user): ?>
        <p>Since you are not logged in, your filters will be stored in a long-lasting
        browser cookie. To permanently store your tag filters and have them work
        across browsers, <a href="/auth/login">login</a> to your account.</p>
    <?php endif ?>
    
    <form method="post" action="/filter">
        <table class="data zebra" cellspacing="0">            
            <tr>
                <th style="width: 5%; text-align: center;">Hide</th>
                <th style="width: 15%; text-align: left;">Tag</th>
                <th style="width: 60%; text-align: left;">Description</th>
                <th style="width: 10%; text-align: right;">Stories</th>
                <th style="width: 10%; text-align: right; padding-right: 1em;">Filtering</th>
            </tr>
            
            <?php if (!empty($categorized_tags)): ?>
                <?php foreach ($categorized_tags as $categoryName => $categoryTags): ?>
                    <tr>
                        <th></th>
                        <th colspan="4"><?= $this->e($categoryName) ?></th>
                    </tr>
                    <?php foreach ($categoryTags as $tag): ?>
                        <tr id="<?= $this->e($tag['tag']) ?>">
                            <td style="text-align: center; padding: 0.5em;">
                                <input type="checkbox" name="tags[<?= $this->e($tag['tag']) ?>]" value="<?= $this->e($tag['tag']) ?>" <?= isset($filtered_tags[$tag['id']]) ? 'checked' : '' ?>>
                            </td>
                            <td style="text-align: left;"><a href="/t/<?= $this->e($tag['tag']) ?>" class="tag"><?= $this->e($tag['tag']) ?></a></td>
                            <td style="text-align: left;">
                                <label for="tags[<?= $this->e($tag['tag']) ?>]">
                                    <span><?= $this->e($tag['description'] ?? 'No description available') ?></span>
                                    <?php if (!empty($tag['inactive']) && $tag['inactive']): ?>
                                        | <em>inactive</em>
                                    <?php endif ?>
                                </label>
                            </td>
                            <td style="text-align: right;"><?= number_format($tag['story_count'] ?? 0) ?></td>
                            <td style="text-align: right; padding-right: 1em;"><?= number_format($tag['filter_count'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach ?>
                <?php endforeach ?>
            <?php endif ?>
        </table>
        <p>
            <input type="submit" value="Save Filters">
        </p>
    </form>
</div>

<style>
/* Lobste.rs-style filter page styling - Updated 2025-08-15 v1.0 */
/* Three-color scheme matching Lobste.rs hierarchy:
   1. Lightest (#eaeaea) - borders and separators
   2. Medium (#ddd) - category section headers 
   3. Darkest (#f5f5f5/#f8f8f8) - individual tag rows */
.box.wide {
    max-width: 1000px;
    margin: 0 auto;
    padding: 1em;
}

.data.zebra {
    width: 100%;
    border-collapse: collapse;
    margin: 1em 0;
}

.data.zebra th {
    background-color: #262626;
    border-bottom: 1px solid #555;
    border-top: 1px solid #555;
    padding: 0.25em 0.5em;
    font-weight: bold;
    text-align: left;
    color: #fff;
}


.data.zebra td {
    padding: 0.5em;
    vertical-align: middle;
    border-bottom: 1px solid #555;
}

/* Alternating row colors - even rows */
.data.zebra tbody tr:nth-child(even) td {
    background-color: #181818;
    border-bottom: 1px solid #555;
}

/* Alternating row colors - odd rows */
.data.zebra tbody tr:nth-child(odd) td {
    background-color: #1B1B1B;
    border-bottom: 1px solid #555;
}

.data.zebra th[colspan] {
    background-color: #262626;
    font-weight: bold;
    text-align: left;
    border-top: 1px solid #555;
    border-bottom: 1px solid #555;
    color: #fff;
}


.data.zebra input[type="checkbox"] {
    margin: 0;
}

/* Use our tag banner styling */
.data.zebra .tag {
    background-color: var(--color-tag-bg);
    border: 1px solid var(--color-tag-border);
    border-radius: 5px;
    color: var(--color-fg-contrast-10);
    font-size: 8pt;
    padding: 0px 0.4em 1px 0.4em;
    text-decoration: none;
    white-space: nowrap;
    font-weight: normal;
}

.data.zebra .tag:hover {
    background-color: var(--color-tag-bg);
}

.data.zebra label {
    cursor: pointer;
    display: block;
    width: 100%;
}

input[type="submit"], .link-button {
    padding: 0.5em 1em;
    background: #ff4444;
    color: white;
    border: none;
    border-radius: 3px;
    cursor: pointer;
    font-size: 14px;
    white-space: nowrap;
    text-decoration: none;
    display: inline-block;
}

input[type="submit"]:hover, .link-button:hover {
    background: #cc3333;
    text-decoration: none;
}
</style>