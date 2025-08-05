<?php
session_start();
$data_dir = 'data/';
$config_file = 'config.json';
if (!file_exists($config_file)) {
    copy('config.json.example', $config_file);
}
if (!file_exists($data_dir)) {
    mkdir($data_dir, 0777, true);
}
if (!file_exists('banned_users.json')) {
    file_put_contents('banned_users.json', json_encode([]));
}
if (!file_exists('ng_words.txt')) {
    file_put_contents('ng_words.txt', '');
}
$board_config = [
    'board_name' => '掲示板',
    'admin_name' => '名無し',
    'admin_sns_link' => '',
    'admin_website_link' => '',
];
if (file_exists($config_file)) {
    $loaded_config = json_decode(file_get_contents($config_file), true);
    if (is_array($loaded_config)) {
        $board_config = array_merge($board_config, $loaded_config);
    }
}
if (!($board_config['is_public'] ?? true)) {
    session_start();
    if (!isset($_SESSION['public_access_granted']) || !$_SESSION['public_access_granted']) {
        if (isset($_POST['public_password'])) {
            if (password_verify($_POST['public_password'], $board_config['public_access_password_hash'] ?? '')) {
                $_SESSION['public_access_granted'] = true;
            } else {
                $public_login_error = 'パスワードが違います。';
            }
        }
        if (!isset($_SESSION['public_access_granted']) || !$_SESSION['public_access_granted']) {
            echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><title>アクセス制限</title><link rel="stylesheet" href="style.css"></head><body>';
            echo '<div class="board-title"><h1>アクセス制限</h1></div>';
            if (isset($public_login_error)) {
                echo '<p style="color:red; text-align:center;">' . $public_login_error . '</p>';
            }
            echo '<form method="post" style="text-align:center;"><input type="password" name="public_password" placeholder="パスワード"> <button type="submit">認証</button></form>';
            echo '</body></html>';
            exit;
        }
    }
}
$pinned_thread_id = $board_config['pinned_thread_id'] ?? null;
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['title']) && !empty($_POST['first_post_content'])) {
    $title = htmlspecialchars($_POST['title'], ENT_QUOTES, 'UTF-8');
    $first_post_content = $_POST['first_post_content'];
    $filename = time() . '.dat';
    $name = !empty($_POST['name']) ? htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8') : '名無しさん';
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
        $name = $board_config['admin_name'];
    }
    $mail = '';
    $date = date('Y-m-d');
    $remote_addr = $_SERVER['REMOTE_ADDR'];
    $id = substr(base64_encode(hash('sha256', $remote_addr . $date, true)), 0, 8);
    $date_id = date('Y/m/d(D) H:i:s') . ' ID:' . $id;
    $first_post_line = $name . '<>' . $mail . '<>' . $date_id . '<>' . $first_post_content;
    file_put_contents($data_dir . $filename, $title . "
" . $first_post_line . "
");
    header('Location: index.php');
    exit;
}
$threads = [];
$pinned_thread = null;
$files = glob($data_dir . '*.dat');
if ($pinned_thread_id && in_array($data_dir . $pinned_thread_id, $files)) {
    $pinned_file = $data_dir . $pinned_thread_id;
    $lines = file($pinned_file, FILE_IGNORE_NEW_LINES);
    if (count($lines) > 0) {
        $pinned_thread = [
            'id' => basename($pinned_file),
            'title' => $lines[0],
            'count' => count($lines) - 1,
            'is_pinned' => true
        ];
        $files = array_diff($files, [$pinned_file]);
    }
}
array_multisort(array_map('filemtime', $files), SORT_DESC, $files);
foreach ($files as $file) {
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    if (count($lines) > 0) {
        $thread_id = basename($file);
        $thread_title = $lines[0];
        $post_count = count($lines) - 1;
        $threads[] = [
            'id' => $thread_id,
            'title' => $thread_title,
            'count' => $post_count
        ];
    }
}
if (!empty($search_query)) {
    $filtered_threads = [];
    foreach ($threads as $thread) {
        if (mb_stripos($thread['title'], $search_query, 0, 'UTF-8') !== false) {
            $filtered_threads[] = $thread;
        }
    }
    $threads = $filtered_threads;
}

if ($pinned_thread) {
    array_unshift($threads, $pinned_thread);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($board_config['board_name']); ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="manifest" href="manifest.json">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php
    $pwa_config_file = 'pwa_config.json';
    $pwa_config = [
        'name' => '掲示板',
        'short_name' => '掲示板',
        'start_url' => '.',
        'display' => 'standalone',
        'background_color' => '#ffffff',
        'theme_color' => '#2196F3',
        'icon_path' => 'images/icon-512x512.png'
    ];
    if (file_exists($pwa_config_file)) {
        $loaded_pwa_config = json_decode(file_get_contents($pwa_config_file), true);
        if (is_array($loaded_pwa_config)) {
            $pwa_config = array_merge($pwa_config, $loaded_pwa_config);
        }
    }
    ?>
    <meta name="theme-color" content="<?php echo htmlspecialchars($pwa_config['theme_color']); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($pwa_config['icon_path']); ?>">
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(registration => {
                        console.log('ServiceWorker registration successful with scope: ', registration.scope);
                    })
                    .catch(err => {
                        console.log('ServiceWorker registration failed: ', err);
                    });
            });
        }
    </script>
    <?php if (!empty($board_config['google_analytics_id'])): ?>
    <!-- Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo htmlspecialchars($board_config['google_analytics_id']); ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', '<?php echo htmlspecialchars($board_config['google_analytics_id']); ?>');
    </script>
    <?php endif; ?>
    <?php if (!empty($board_config['custom_index_css'])): ?>
    <style>
        <?php echo $board_config['custom_index_css']; ?>
    </style>
    <?php endif; ?>
</head>
<body>
<div class="container">
    <div class="board-title">
        <h1><?php echo htmlspecialchars($board_config['board_name']); ?></h1>
        <?php if (!empty($board_config['custom_index_html'])): ?>
        <div class="custom-html">
            <?php echo $board_config['custom_index_html']; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="main-content-grid">
        <div class="thread-list">
            <h2>スレッド一覧</h2>
            <ul>
                <?php if (empty($threads)): ?>
                    <li>まだスレッドがありません。</li>
                <?php else: ?>
                    <?php foreach ($threads as $thread): ?>
                        <li>
                            <a href="thread.php?id=<?php echo urlencode($thread['id']); ?>">
                                <?php echo htmlspecialchars($thread['title'], ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                            <?php if (isset($thread['is_pinned']) && $thread['is_pinned']): ?>
                                <span style="color: red; font-weight: bold;">[PINNED]</span>
                            <?php endif; ?>
                            (<?php echo $thread['count']; ?>)
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
        <div class="side-content">
            <div class="form-container">
                <h2>新規スレッド作成</h2>
                <form action="index.php" method="post">
                    <input type="text" name="title" placeholder="スレッドのタイトル" required>
                    <input type="text" name="name" placeholder="<?php echo isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] ? htmlspecialchars($board_config['admin_name']) : '名前 (省略時: 名無しさん)'; ?>">
                    <textarea name="first_post_content" rows="5" placeholder="最初の投稿内容" required></textarea>
                    <button type="submit">スレッド作成</button>
                </form>
            </div>
            <div class="search-form" style="margin-top: 15px;">
                <form action="index.php" method="get">
                    <input type="text" name="q" value="<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>" placeholder="スレッド検索">
                    <button type="submit">検索</button>
                </form>
            </div>
            <?php if (!empty($board_config['sidebar_ad_code'])): ?>
            <div class="sidebar-ad" style="margin-top: 15px; text-align: center;">
                <?php echo $board_config['sidebar_ad_code']; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!empty($board_config['footer_ad_code'])): ?>
    <div class="footer-ad" style="text-align:center; margin-top: 20px;">
        <?php echo $board_config['footer_ad_code']; ?>
    </div>
    <?php endif; ?>
    <footer>
        <p>Powered by 
            <?php
            $powered_by_link = htmlspecialchars($board_config['admin_contact_link'] ?? '');
            ?>
            <?php if (!empty($powered_by_link)): ?>
                <a href="<?php echo $powered_by_link; ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($board_config['admin_name']); ?></a>
            <?php else: ?>
                <?php echo htmlspecialchars($board_config['admin_name']); ?>
            <?php endif; ?>
        </p>
        <p>© 2025 <a href="https://github.com/koba_9813">Koba_9813</a> All rights reserved.</p>
        <p>Manaita BBS System</p>
    </footer>
</div>
</body>
</html>
