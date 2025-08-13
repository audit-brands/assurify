<?php $this->layout('layout', ['title' => $title]) ?>

<div class="invitations-tree">
    <h1>Invitation Tree</h1>
    
    <p>This page shows who invited whom, creating a visual tree of how members joined the community.</p>
    
    <?php if (empty($users)) : ?>
        <div class="empty-state">
            <p>No invitation data available yet.</p>
            <p><a href="/invitations">Send an invitation</a> to start building the community tree.</p>
        </div>
    <?php else : ?>
        <div class="tree-container">
            <div class="tree-explanation">
                <p>Each user is shown with the people they've invited. This helps track how the community grows through personal connections.</p>
            </div>
            
            <div class="invitation-tree">
                <?php foreach ($users as $user) : ?>
                    <?php if ($user->invitations && count($user->invitations) > 0) : ?>
                        <div class="tree-node">
                            <div class="inviter">
                                <strong><a href="/u/<?=$this->e($user->username)?>"><?=$this->e($user->username)?></a></strong>
                                <span class="karma">(<?=$user->karma?> karma)</span>
                            </div>
                            <div class="invitees">
                                <?php foreach ($user->invitations as $invitation) : ?>
                                    <?php if ($invitation->used_at) : ?>
                                        <div class="invitee">
                                            → <a href="/u/<?=$this->e($invitation->new_user->username ?? 'unknown')?>"><?=$this->e($invitation->new_user->username ?? 'Unknown User')?></a>
                                            <span class="invite-date">(joined <?=$invitation->used_at->format('M Y')?>)</span>
                                        </div>
                                    <?php endif ?>
                                <?php endforeach ?>
                            </div>
                        </div>
                    <?php endif ?>
                <?php endforeach ?>
            </div>
        </div>
    <?php endif ?>
    
    <div class="tree-actions">
        <p><a href="/invitations">← Back to Invitations</a></p>
    </div>
</div>