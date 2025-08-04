<?php
// ユーザーIDを生成する関数
function generate_user_id() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $date = date('Y-m-d');
    return substr(base64_encode(hash('sha256', $ip . $date, true)), 0, 8);
}

$data_dir = 'data/';
$ng_words_file = 'ng_words.txt';
$banned_users_file = 'banned_users.json';
$config_file = 'config.json';

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

// 公開設定のチェック
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

$thread_id = isset($_GET['id']) ? basename($_GET['id']) : '';
$file_path = $data_dir . $thread_id;

if (empty($thread_id) || !file_exists($file_path)) {
    header("HTTP/1.0 404 Not Found");
    echo "スレッドが見つかりません。";
    exit;
}

$ng_words = file_exists($ng_words_file) ? file($ng_words_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$banned_users = file_exists($banned_users_file) ? json_decode(file_get_contents($banned_users_file), true) : [];
if (!is_array($banned_users)) $banned_users = [];

foreach ($banned_users as $id => $info) {
    if (isset($info['type']) && $info['type'] === 'auto' && isset($info['ban_until']) && time() > $info['ban_until']) {
        unset($banned_users[$id]);
    }
}
file_put_contents($banned_users_file, json_encode($banned_users, JSON_PRETTY_PRINT));

$posts = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$thread_title = array_shift($posts);
$is_dat_ochi = count($posts) >= 1000;
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$is_dat_ochi && !empty($_POST['comment'])) {
    $user_id = generate_user_id();
    $comment = $_POST['comment'];
    $is_banned = false;

    if (isset($banned_users[$user_id])) {
        $ban_info = $banned_users[$user_id];
        if ($ban_info['type'] === 'manual' || ($ban_info['type'] === 'auto' && time() < $ban_info['ban_until'])) {
            $is_banned = true;
            $error_message = 'あなたはこの掲示板への投稿を禁止されています。';
        }
    }

    if (!$is_banned) {
        $ng_word_found = false;
        foreach ($ng_words as $word) {
            if (stripos($comment, $word) !== false) {
                $ng_word_found = true;
                break;
            }
        }

        if ($ng_word_found) {
            if (!isset($banned_users[$user_id])) {
                $banned_users[$user_id] = ['type' => 'auto', 'ng_count' => 0];
            }
            $banned_users[$user_id]['ng_count']++;
            if ($banned_users[$user_id]['ng_count'] >= 3) {
                $banned_users[$user_id]['ban_until'] = time() + (7 * 24 * 60 * 60); // 1週間
                $error_message = '不適切な単語を複数回使用したため、1週間投稿が禁止されました。';
            } else {
                $error_message = '不適切な単語が含まれています。ご注意ください。 (警告: ' . $banned_users[$user_id]['ng_count'] . '/3)';
            }
            file_put_contents($banned_users_file, json_encode($banned_users, JSON_PRETTY_PRINT));
        } else {
            $name = !empty($_POST['name']) ? $_POST['name'] : '名無しさん';
            $date = date('Y/m/d(D) H:i:s');
            $post_data = $name . "<>" . "" . "<>" . $date . " ID:" . $user_id . "<>" . $comment . "\n";
            file_put_contents($file_path, $post_data, FILE_APPEND);
            header('Location: thread.php?id=' . urlencode($thread_id));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($thread_title, ENT_QUOTES, 'UTF-8'); ?></title>
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
    <?php if (!empty($board_config['custom_thread_css'])): ?>
    <style>
        <?php echo $board_config['custom_thread_css']; ?>
    </style>
    <?php endif; ?>
</head>
<body>

<div class="container">
    <div class="thread-header">
        <h1><?php echo htmlspecialchars($thread_title, ENT_QUOTES, 'UTF-8'); ?></h1>
        <a href="index.php"><?php echo htmlspecialchars($board_config['board_name']); ?>に戻る</a>
        <?php if (!empty($board_config['custom_thread_html'])): ?>
        <div class="custom-html">
            <?php echo $board_config['custom_thread_html']; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($error_message)): ?>
    <p style="color:red; text-align:center; font-weight:bold;"><?php echo $error_message; ?></p>
    <?php endif; ?>

    <div class="post-list">
        <?php foreach ($posts as $index => $post):
            if ($post === '削除されました') {
                echo '<div class="post"><div class="post-info"><span class="post-number">' . ($index + 1) . ':</span> 削除されました</div></div>';
                continue;
            }
            $parts = explode('<>', $post, 4);
            if (count($parts) < 4) continue;
            list($name, $mail, $date_id, $comment) = $parts;

            // 悪意のあるHTMLを防ぐためにエスケープ
            $name_escaped = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            $comment_escaped = htmlspecialchars($comment, ENT_QUOTES, 'UTF-8');

            // アンカーリンクを生成 (エスケープされた >> を探す)
            $comment_escaped = preg_replace('/(&gt;&gt;(\d+))/', '<a href="#post$2">&gt;&gt;$2</a>', $comment_escaped);

            // 改行を<br>に変換
            $comment_processed = nl2br($comment_escaped);
            ?>
            <div class="post" id="post<?php echo $index + 1; ?>">
                <div class="post-info">
                    <span class="post-number"><?php echo $index + 1; ?>:</span>
                    名前: <span class="post-name"><?php echo $name_escaped; ?></span>
                    [<?php echo htmlspecialchars($date_id); ?>]
                </div>
                <div class="post-body">
                    <?php echo $comment_processed; ?>
                </div>
            </div>
        <?php endforeach;
        if (empty($posts)): ?>
            <p>まだ投稿がありません。</p>
        <?php endif; ?>
    </div>

    <?php if ($is_dat_ochi):
    ?>
    <div class="dat-ochi-info">
        <p>このスレッドは1000を超えました。もう書けないので、新しいスレッドを立ててください。</p>
    </div>
    <?php else:
    ?>
    <div class="form-container">
        <h2>投稿する</h2>
        <form action="thread.php?id=<?php echo urlencode($thread_id); ?>" method="post">
            <input type="text" name="name" size="30" placeholder="名前 (省略時: 名無しさん)">
            <textarea name="comment" rows="5" cols="70" placeholder="レスを入力" required></textarea>
            <button type="submit">書き込む</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if (!empty($board_config['footer_ad_code'])): ?>
    <div class="footer-ad" style="text-align:center; margin-top: 20px;">
        <?php echo $board_config['footer_ad_code']; ?>
    </div>
    <?php endif; ?>

    

    <footer>
            <p>Powered by 
            <?php
            $powered_by_link = '';
            if (!empty($board_config['admin_sns_link'])) {
                $powered_by_link = htmlspecialchars($board_config['admin_sns_link']);
            } elseif (!empty($board_config['admin_website_link'])) {
                $powered_by_link = htmlspecialchars($board_config['admin_website_link']);
            }
            ?>
            <?php if (!empty($powered_by_link)): ?>
                <a href="<?php echo $powered_by_link; ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($board_config['admin_name']); ?></a>
            <?php else: ?>
                <?php echo htmlspecialchars($board_config['admin_name']); ?>
            <?php endif; ?>
        </p>
        <p>© 2025 <a href="https://github.com/koba_9813">Koba_9813</a> All rights reserved.</p>
        <p>Manaita BBS System Ver1.1.0</p>
    </footer>
</div>

</body>
</html>
