<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?=$this->e($title ?? 'Lobsters')?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" type="text/css" href="/assets/application.css">
    <link rel="shortcut icon" href="/favicon.ico">
</head>
<body>
    <div id="wrapper">
        <header id="header">
            <div class="header-content">
                <a href="/" class="logo">
                    <img src="/assets/logo.png" alt="Lobsters" width="50" height="50">
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
            <p>&copy; <?=date('Y')?> Lobsters Community</p>
        </footer>
    </div>

    <script src="/assets/application.js"></script>
</body>
</html>