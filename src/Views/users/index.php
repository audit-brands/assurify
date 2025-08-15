<?php $this->layout('layout', ['title' => $title]) ?>

<div class="users-page">
    <?php if ($sort_by === 'tree'): ?>
        <h1>Users (<?= number_format($total_users) ?>)</h1>
        
        <p>
            <a href="/users">invitation tree</a> | <a href="/users?by=karma">by karma</a>
        </p>
        
        <?php if (!empty($newest_users)): ?>
            <div class="newest-users">
                <h2>Newest users</h2>
                <ul>
                    <?php foreach ($newest_users as $user): ?>
                        <li>
                            <a href="/u/<?= $this->e($user['username']) ?>" 
                               <?= $user['is_new'] ? 'class="new-user"' : '' ?>>
                                <?= $this->e($user['username']) ?>
                            </a>
                            (<?= $user['karma'] ?>)
                            <?php if ($user['invited_by']): ?>
                                invited by <a href="/u/<?= $this->e($user['invited_by']) ?>"><?= $this->e($user['invited_by']) ?></a>
                            <?php endif ?>
                        </li>
                    <?php endforeach ?>
                </ul>
            </div>
        <?php endif ?>
        
        <?php if (!empty($user_tree)): ?>
            <div class="user-tree">
                <?php 
                function renderUserTree($tree, $depth = 0) {
                    if (empty($tree)) return;
                    
                    echo '<ul class="user-tree-level level-' . $depth . '">';
                    
                    foreach ($tree as $user) {
                        echo '<li>';
                        echo '<a href="/u/' . htmlspecialchars($user['username']) . '"';
                        if ($user['is_new']) {
                            echo ' class="new-user"';
                        }
                        echo '>' . htmlspecialchars($user['username']) . '</a>';
                        echo ' (' . $user['karma'] . ')';
                        
                        if (!empty($user['children'])) {
                            renderUserTree($user['children'], $depth + 1);
                        }
                        
                        echo '</li>';
                    }
                    
                    echo '</ul>';
                }
                
                renderUserTree($user_tree, 0);
                ?>
            </div>
        <?php endif ?>
        
    <?php else: ?>
        <h1>Users by karma (<?= count($users) ?>)</h1>
        
        <p>
            <a href="/users">invitation tree</a> | <a href="/users?by=karma">by karma</a>
        </p>
        
        <?php if (!empty($users)): ?>
            <div class="users-by-karma">
                <ul>
                    <?php foreach ($users as $user): ?>
                        <li>
                            <a href="/u/<?= $this->e($user['username']) ?>" 
                               <?= $user['is_new'] ? 'class="new-user"' : '' ?>>
                                <?= $this->e($user['username']) ?>
                            </a>
                            (<?= $user['karma'] ?>)
                            <?php if ($user['invited_by']): ?>
                                invited by <a href="/u/<?= $this->e($user['invited_by']) ?>"><?= $this->e($user['invited_by']) ?></a>
                            <?php endif ?>
                        </li>
                    <?php endforeach ?>
                </ul>
            </div>
        <?php endif ?>
    <?php endif ?>
</div>