<?php $this->layout('layout', ['title' => $title]) ?>

<div class="invitations-page">
    <h1>Invitations</h1>
    
    <?php if ($error) : ?>
        <div class="error-message">
            <?=$this->e($error)?>
        </div>
    <?php endif ?>
    
    <?php if ($success) : ?>
        <div class="success-message">
            <?=$this->e($success)?>
        </div>
    <?php endif ?>
    
    <div class="invitation-stats">
        <h2>Your Invitation Statistics</h2>
        <ul>
            <li><strong>Total invitations sent:</strong> <?=$stats['total']?></li>
            <li><strong>Used invitations:</strong> <?=$stats['used']?></li>
            <li><strong>Pending invitations:</strong> <?=$stats['pending']?></li>
            <li><strong>Remaining this week:</strong> <?=$stats['remaining_this_week']?></li>
        </ul>
        
        <?php if ($stats['can_invite']) : ?>
            <p><a href="/invitations/create" class="btn btn-primary">Send New Invitation</a></p>
        <?php else : ?>
            <p class="warning">You cannot send invitations at this time. This may be due to karma requirements or rate limits.</p>
        <?php endif ?>
    </div>
    
    <div class="invitation-history">
        <h2>Your Invitations</h2>
        
        <?php if ($invitations->isEmpty()) : ?>
            <p>You haven't sent any invitations yet.</p>
        <?php else : ?>
            <table class="invitations-table">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th>Sent</th>
                        <th>Status</th>
                        <th>Used</th>
                        <th>Memo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invitations as $invitation) : ?>
                        <tr>
                            <td><?=$this->e($invitation->email)?></td>
                            <td><?=$invitation->created_at->format('Y-m-d H:i')?></td>
                            <td>
                                <?php if ($invitation->used_at) : ?>
                                    <span class="status-used">Used</span>
                                <?php else : ?>
                                    <span class="status-pending">Pending</span>
                                <?php endif ?>
                            </td>
                            <td>
                                <?php if ($invitation->used_at) : ?>
                                    <?=$invitation->used_at->format('Y-m-d H:i')?>
                                <?php else : ?>
                                    -
                                <?php endif ?>
                            </td>
                            <td>
                                <?php if ($invitation->memo) : ?>
                                    <?=$this->e(substr($invitation->memo, 0, 50))?><?=strlen($invitation->memo) > 50 ? '...' : ''?>
                                <?php else : ?>
                                    -
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        <?php endif ?>
    </div>
    
    <div class="invitation-links">
        <p><a href="/invitations/tree">View Invitation Tree</a></p>
    </div>
</div>