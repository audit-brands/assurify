<?php $this->layout('layout', ['title' => $title]) ?>

<div class="users-page">
    <h1>Users</h1>
    
    <?php if (empty($users)) : ?>
        <p>No users found.</p>
    <?php else : ?>
        <div class="users-list">
            <?php foreach ($users as $user) : ?>
                <div class="user-item">
                    <h3><a href="/u/<?=$user['username']?>"><?=$this->e($user['username'])?></a></h3>
                    <div class="user-stats">
                        <span class="karma">Karma: <?=$user['karma']?></span>
                        <span class="member-since">Member since: <?=$user['created_at']?></span>
                    </div>
                    <?php if ($user['about']) : ?>
                        <div class="user-about">
                            <?=substr($this->e($user['about']), 0, 100)?>
                            <?=strlen($user['about']) > 100 ? '...' : ''?>
                        </div>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>