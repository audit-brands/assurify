<?php $this->layout('layout', ['title' => $title]) ?>

<div class="story-form">
    <h1>Send Invitation</h1>
    
    <?php if ($error) : ?>
        <div class="error-message">
            <?=$this->e($error)?>
        </div>
    <?php endif ?>
    
    <div class="invitation-info">
        <p>You can send <strong><?=$stats['remaining_this_week']?></strong> more invitations this week.</p>
        <p>Invitations help us maintain a high-quality community by ensuring new members are vouched for by existing ones.</p>
    </div>
    
    <form action="/invitations" method="post">
        <div class="form-group">
            <label for="email">Email Address:</label>
            <input type="email" name="email" id="email" required>
            <small>The person you're inviting must use this exact email address to sign up.</small>
        </div>
        
        <div class="form-group">
            <label for="memo">Personal Message (optional):</label>
            <textarea name="memo" id="memo" rows="4" maxlength="500" placeholder="Add a personal note to your invitation..."></textarea>
            <small>This will be included in the invitation email.</small>
        </div>
        
        <div class="form-actions">
            <button type="submit">Send Invitation</button>
            <a href="/invitations" class="cancel">Cancel</a>
        </div>
    </form>
    
    <div class="invitation-guidelines">
        <h3>Invitation Guidelines</h3>
        <ul>
            <li>Only invite people you know who would contribute positively to the community</li>
            <li>Make sure they're interested in technology and programming topics</li>
            <li>You're responsible for the behavior of people you invite</li>
            <li>Invitations expire after 30 days if not used</li>
        </ul>
    </div>
</div>