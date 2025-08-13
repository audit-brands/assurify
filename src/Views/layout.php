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
    <meta name="msapplication-TileColor" content="#ff6600">
    <meta name="msapplication-config" content="/browserconfig.xml">
    
    <!-- CSS -->
    <link rel="stylesheet" type="text/css" href="/assets/application.css">
    
    <!-- RSS Feeds -->
    <link rel="alternate" type="application/rss+xml" title="Assurify - Stories" href="/feeds/stories.rss">
    <link rel="alternate" type="application/rss+xml" title="Assurify - Comments" href="/feeds/comments.rss">
</head>
<body>
    <div id="wrapper">
        <header id="header">
            <div class="header-content">
                <a href="/" class="logo">
                    <span class="logo-text">Assurify</span>
                </a>
                <nav id="navigation">
                    <ul>
                        <li><a href="/">Home</a></li>
                        <li><a href="/newest">Newest</a></li>
                        <li><a href="/recent">Recent</a></li>
                        <li><a href="/top">Top</a></li>
                        <li><a href="/tags">Tags</a></li>
                        <li><a href="/stories">Submit</a></li>
                    </ul>
                </nav>
                <div id="user-nav">
                    <?php if (isset($_SESSION['user_id'])) : ?>
                        <span>Welcome, <a href="/u/<?=$_SESSION['username']?>"><?=$this->e($_SESSION['username'])?></a></span>
                        <a href="/invitations">Invitations</a> |
                        <form action="/auth/logout" method="post" style="display: inline;">
                            <button type="submit" class="link-button">Logout</button>
                        </form>
                    <?php else : ?>
                        <a href="/auth/login">Login</a> |
                        <a href="/auth/signup">Sign up</a>
                    <?php endif ?>
                </div>
            </div>
        </header>

        <main id="main">
            <?=$this->section('content')?>
        </main>

        <footer id="footer">
            <p>&copy; <?=date('Y')?> Assurify Platform</p>
        </footer>
    </div>

    <script src="/assets/application.js"></script>
    <script src="/assets/performance.js"></script>
    <script src="/assets/offline.js"></script>
    <script src="/assets/pwa.js"></script>
    <script src="/assets/websocket.js"></script>
    
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
        
        // Handle form submissions when offline
        document.addEventListener('submit', function(event) {
            if (!navigator.onLine) {
                event.preventDefault();
                alert('You are currently offline. This action will be performed when you reconnect.');
                
                // Store offline action for later sync
                const formData = new FormData(event.target);
                const offlineAction = {
                    url: event.target.action,
                    method: event.target.method,
                    data: Object.fromEntries(formData),
                    timestamp: Date.now()
                };
                
                // Store in localStorage for sync later
                const offlineActions = JSON.parse(localStorage.getItem('offlineActions') || '[]');
                offlineActions.push(offlineAction);
                localStorage.setItem('offlineActions', JSON.stringify(offlineActions));
                
                // Trigger background sync event
                window.dispatchEvent(new CustomEvent('offline-action', {
                    detail: { tag: 'background-sync-stories' }
                }));
            }
        });
    </script>
</body>
</html>