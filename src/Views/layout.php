<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?=$this->e($title ?? 'Assurify')?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Advanced community platform for secure discussions, content sharing, and professional networking">
    <meta name="theme-color" content="#ff4444">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Assurify">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">
    
    <!-- Favicons and App Icons -->
    <link rel="shortcut icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/icons/icon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/icons/icon-32x32.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/icons/icon-192x192.png">
    <link rel="apple-touch-icon" href="/assets/icons/icon-192x192.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/assets/icons/icon-152x152.png">
    <link rel="apple-touch-icon" sizes="144x144" href="/assets/icons/icon-144x144.png">
    <link rel="apple-touch-icon" sizes="120x120" href="/assets/icons/icon-120x120.png">
    <link rel="apple-touch-icon" sizes="114x114" href="/assets/icons/icon-114x114.png">
    <link rel="apple-touch-icon" sizes="76x76" href="/assets/icons/icon-76x76.png">
    <link rel="apple-touch-icon" sizes="72x72" href="/assets/icons/icon-72x72.png">
    <link rel="apple-touch-icon" sizes="60x60" href="/assets/icons/icon-60x60.png">
    <link rel="apple-touch-icon" sizes="57x57" href="/assets/icons/icon-57x57.png">
    
    <!-- Microsoft Tiles -->
    <meta name="msapplication-TileImage" content="/assets/icons/icon-144x144.png">
    <meta name="msapplication-TileColor" content="#dc2626">
    <meta name="msapplication-config" content="/browserconfig.xml">
    
    <!-- CSS -->
    <link rel="stylesheet" type="text/css" href="/assets/application.css?v=<?=time()?>">
    <link rel="stylesheet" type="text/css" href="/assets/voting.css?v=<?=time()?>">
    
    <!-- RSS Feeds -->
    <link rel="alternate" type="application/rss+xml" title="Assurify - Stories" href="/feeds/stories.rss">
    <link rel="alternate" type="application/rss+xml" title="Assurify - Comments" href="/feeds/comments.rss">
</head>
<body>
    <div id="wrapper">
        <header id="nav">
            <div class="navholder">
                <nav class="links">
                    <a href="/" id="logo"><span class="logo-text">Assurify</span></a>
                    <a href="/active"<?= ($_SERVER['REQUEST_URI'] === '/active' || $_SERVER['REQUEST_URI'] === '/') ? ' class="current_page"' : '' ?>>Active</a>
                    <a href="/recent"<?= ($_SERVER['REQUEST_URI'] === '/recent') ? ' class="current_page"' : '' ?>>Recent</a>
                    <a href="/comments"<?= ($_SERVER['REQUEST_URI'] === '/comments') ? ' class="current_page"' : '' ?>>Comments</a>
                    <a href="/search"<?= ($_SERVER['REQUEST_URI'] === '/search') ? ' class="current_page"' : '' ?>>Search</a>
                </nav>
            </div>
        </header>
        <header id="subnav">
            <?php if (isset($_SESSION['user_id'])) : ?>
                <a href="/u/<?=$_SESSION['username']?>"><?=$this->e($_SESSION['username'])?></a>
                <a href="/messages" id="messages-link">Messages</a>
                <a href="/settings">Settings</a>
                <a href="/invitations">Invitations</a>
                <a href="/stories">Submit</a>
                <form action="/auth/logout" method="post" style="display: inline;">
                    <input type="submit" value="Logout" class="link-button">
                </form>
            <?php else : ?>
                <a href="/stories">Submit</a>
                <a href="/auth/login">Login</a>
            <?php endif ?>
        </header>

        <main id="main">
            <?=$this->section('content')?>
        </main>

        <footer>
            <nav>
                <a href="/about">About</a>
                <a href="/tags">Tags</a>
                <a href="/filter">Filter</a>
                <a href="/moderation-log">Moderation Log</a>
            </nav>
        </footer>
    </div>

    <script src="/assets/application.js"></script>
    <script src="/assets/performance.js"></script>
    
    <!-- Temporarily disable problematic scripts that are causing 401 errors -->
    <script>
        // Disable offline.js, pwa.js, and websocket.js temporarily to prevent interference
        console.log('PWA and WebSocket features temporarily disabled for testing');
    </script>
    <!--
    <script src="/assets/offline.js"></script>
    <script src="/assets/pwa.js"></script>
    <script src="/assets/websocket.js"></script>
    -->
    
    <!-- Message notifications -->
    <script>
        // Check for unread messages periodically
        <?php if (isset($_SESSION['user_id'])) : ?>
        function updateUnreadMessageCount() {
            fetch('/messages/unread-count')
                .then(response => response.json())
                .then(data => {
                    const messagesLink = document.getElementById('messages-link');
                    if (messagesLink) {
                        const existingBadge = messagesLink.querySelector('.unread-badge');
                        if (existingBadge) {
                            existingBadge.remove();
                        }
                        
                        if (data.count > 0) {
                            const badge = document.createElement('span');
                            badge.className = 'unread-badge';
                            badge.textContent = data.count;
                            messagesLink.appendChild(badge);
                        }
                    }
                })
                .catch(error => {
                    // Silently fail - not critical
                    console.log('Failed to fetch unread message count:', error);
                });
        }

        // Check immediately and then every 60 seconds
        updateUnreadMessageCount();
        setInterval(updateUnreadMessageCount, 60000);
        <?php endif ?>
    </script>
    
    <!-- PWA initialization -->
    <script>
        // Basic offline handling
        window.addEventListener('load', function() {
            // Update UI based on connection status
            function updateOfflineStatus() {
                document.body.classList.toggle('offline', !navigator.onLine);
            }
            
            window.addEventListener('online', updateOfflineStatus);
            window.addEventListener('offline', updateOfflineStatus);
            updateOfflineStatus();
        });
        
        // Temporarily disable offline form handling to prevent interference
        console.log('Offline form handling disabled for testing');
    </script>
</body>
</html>