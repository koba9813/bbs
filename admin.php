<?php
session_start();
$data_dir = 'data/';
$ng_words_file = 'ng_words.txt';
$banned_users_file = 'banned_users.json';
$config_file = 'config.json';
if (!file_exists('admin_config.php')) {
    copy('admin_config.php.example', 'admin_config.php');
    $default_admin_hash = password_hash('admin', PASSWORD_DEFAULT);
    file_put_contents('admin_config.php', "<?php define('ADMIN_PASSWORD_HASH', '" . $default_admin_hash . "'); ?>");
}
require_once 'admin_config.php';

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
generate_pwa_files($pwa_config);

function generate_pwa_files($pwa_config) {
    // Generate manifest.json
    $manifest_content = [
        'name' => $pwa_config['name'],
        'short_name' => $pwa_config['short_name'],
        'start_url' => $pwa_config['start_url'],
        'display' => $pwa_config['display'],
        'background_color' => $pwa_config['background_color'],
        'theme_color' => $pwa_config['theme_color'],
        'icons' => [
            [
                'src' => $pwa_config['icon_path'],
                'sizes' => '512x512',
                'type' => 'image/png'
            ]
        ]
    ];
    file_put_contents('manifest.json', json_encode($manifest_content, JSON_PRETTY_PRINT));

    // Generate sw.js
    $sw_content = <<<EOT
const CACHE_NAME = 'bbs-cache-v1';
const urlsToCache = [
    '/',
    '/index.php',
    '/style.css',
    '/thread.php',
    '{$pwa_config['icon_path']}'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('Opened cache');
                return cache.addAll(urlsToCache);
            })
    );
});

self.addEventListener('fetch', event => {
    event.respondWith(
        caches.match(event.request)
            .then(response => {
                if (response) {
                    return response;
                }
                return fetch(event.request);
            })
    );
});

self.addEventListener('activate', event => {
    const cacheWhitelist = [CACHE_NAME];
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheWhitelist.indexOf(cacheName) === -1) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});
EOT;
    file_put_contents('sw.js', $sw_content);
}
$ng_words_file = 'ng_words.txt';
$banned_users_file = 'banned_users.json';
$config_file = 'config.json';
$board_config = [
    'board_name' => '掲示板',
    'admin_name' => '名無しさん',
    'admin_contact_link' => 'github.com/koba9813',
    'custom_index_html' => '',
    'custom_index_css' => '',
    'custom_thread_html' => '',
    'custom_thread_css' => '',
];
if (file_exists($config_file)) {
    $loaded_config = json_decode(file_get_contents($config_file), true);
    if (is_array($loaded_config)) {
        $board_config = array_merge($board_config, $loaded_config);
    }
} else {
    file_put_contents($config_file, json_encode($board_config, JSON_PRETTY_PRINT));
}
if (isset($_POST['password'])) {
    if (password_verify($_POST['password'], ADMIN_PASSWORD_HASH)) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $login_error = 'パスワードが違います。';
    }
}
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    session_destroy();
    header('Location: admin.php');
    exit;
}
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><title>管理者ログイン</title><link rel="stylesheet" href="style.css"></head><body>';
    echo '<div class="board-title"><h1>管理者ログイン</h1></div>';
    if (isset($login_error)) {
        echo '<p style="color:red; text-align:center;">' . $login_error . '</p>';
    }
    echo '<form method="post" style="text-align:center;"><input type="password" name="password" placeholder="パスワード"> <button type="submit">ログイン</button></form>';
    echo '</body></html>';
    exit;
}
if (isset($_POST['update_board_info'])) {
    $board_config['board_name'] = htmlspecialchars($_POST['board_name']);
    $board_config['admin_name'] = htmlspecialchars($_POST['admin_name']);
    $board_config['admin_contact_link'] = $_POST['admin_contact_link'];
    if (!empty($board_config['admin_contact_link']) && !preg_match('/^https?:\/\//i', $board_config['admin_contact_link'])) {
        $board_config['admin_contact_link'] = 'https://' . $board_config['admin_contact_link'];
    }
    $board_config['custom_index_html'] = $_POST['custom_index_html'];
    $board_config['custom_index_css'] = $_POST['custom_index_css'];
    $board_config['custom_thread_html'] = $_POST['custom_thread_html'];
    $board_config['custom_thread_css'] = $_POST['custom_thread_css'];
    file_put_contents($config_file, json_encode($board_config, JSON_PRETTY_PRINT));
    header('Location: admin.php?status=board_info_updated');
    exit;
}
if (isset($_POST['update_public_settings'])) {
    $board_config['is_public'] = (bool)$_POST['is_public'];
    if (!empty($_POST['public_access_password'])) {
        $board_config['public_access_password_hash'] = password_hash($_POST['public_access_password'], PASSWORD_DEFAULT);
    } else {
        $board_config['public_access_password_hash'] = null;
    }
    file_put_contents($config_file, json_encode($board_config, JSON_PRETTY_PRINT));
    header('Location: admin.php?status=public_settings_updated');
    exit;
}
if (isset($_POST['update_admin_password'])) {
    if (!empty($_POST['new_admin_password'])) {
        $new_admin_password_hash = password_hash($_POST['new_admin_password'], PASSWORD_DEFAULT);
        $admin_config_content = "<?php define('ADMIN_PASSWORD_HASH', '" . $new_admin_password_hash . "'); ?>";
        file_put_contents('admin_config.php', $admin_config_content);
        header('Location: admin.php?status=admin_password_updated');
        exit;
    }
}
if (isset($_POST['update_pinned_thread'])) {
    $board_config['pinned_thread_id'] = htmlspecialchars($_POST['pinned_thread_id']);
    file_put_contents($config_file, json_encode($board_config, JSON_PRETTY_PRINT));
    header('Location: admin.php?status=pinned_thread_updated');
    exit;
}

if (isset($_POST['update_pwa_settings'])) {
    $pwa_config['name'] = htmlspecialchars($_POST['pwa_name']);
    $pwa_config['short_name'] = htmlspecialchars($_POST['pwa_short_name']);
    $pwa_config['start_url'] = htmlspecialchars($_POST['pwa_start_url']);
    $pwa_config['display'] = htmlspecialchars($_POST['pwa_display']);
    $pwa_config['background_color'] = htmlspecialchars($_POST['pwa_background_color']);
    $pwa_config['theme_color'] = htmlspecialchars($_POST['pwa_theme_color']);
    $pwa_config['icon_path'] = htmlspecialchars($_POST['pwa_icon_path']);

    file_put_contents($pwa_config_file, json_encode($pwa_config, JSON_PRETTY_PRINT));
    generate_pwa_files($pwa_config);
    header('Location: admin.php?status=pwa_settings_updated');
    exit;
}

if (isset($_POST['update_ga_settings'])) {
    $board_config['google_analytics_id'] = htmlspecialchars($_POST['google_analytics_id']);
    file_put_contents($config_file, json_encode($board_config, JSON_PRETTY_PRINT));
    header('Location: admin.php?status=ga_settings_updated');
    exit;
}

if (isset($_POST['update_ad_settings'])) {
    $board_config['footer_ad_code'] = $_POST['footer_ad_code'];
    $board_config['sidebar_ad_code'] = $_POST['sidebar_ad_code'];
    file_put_contents($config_file, json_encode($board_config, JSON_PRETTY_PRINT));
    header('Location: admin.php?status=ad_settings_updated');
    exit;
}
$banned_users = [];
if (file_exists($banned_users_file)) {
    $banned_users_content = file_get_contents($banned_users_file);
    if (!empty($banned_users_content)) {
        $banned_users = json_decode($banned_users_content, true);
        if (!is_array($banned_users)) $banned_users = [];
    }
}
if (!file_exists($banned_users_file) || empty($banned_users)) {
    $old_banned_ids_file = 'banned_ids.txt';
    $old_ban_log_file = 'ban_log.txt';
    if (file_exists($old_banned_ids_file)) {
        $manual_ids = file($old_banned_ids_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($manual_ids as $id) {
            if (!isset($banned_users[$id])) {
                $banned_users[$id] = ['type' => 'manual', 'ban_time' => time()];
            }
        }
        unlink($old_banned_ids_file);
    }
    if (file_exists($old_ban_log_file)) {
        $ban_log_content = file_get_contents($old_ban_log_file);
        if (!empty($ban_log_content)) {
            $old_ban_log = json_decode($ban_log_content, true);
            if (is_array($old_ban_log)) {
                foreach ($old_ban_log as $id => $info) {
                    if (!isset($banned_users[$id])) {
                        $banned_users[$id] = [
                            'type' => 'auto',
                            'ng_count' => $info['ng_count'] ?? 0,
                            'ban_time' => $info['ban_time'] ?? time(),
                            'ban_until' => $info['ban_until'] ?? 0
                        ];
                    }
                }
            }
        }
        unlink($old_ban_log_file);
    }
    file_put_contents($banned_users_file, json_encode($banned_users, JSON_PRETTY_PRINT));
}
foreach ($banned_users as $id => $info) {
    if (isset($info['ban_until']) && time() > $info['ban_until']) {
        unset($banned_users[$id]);
    }
}
file_put_contents($banned_users_file, json_encode($banned_users, JSON_PRETTY_PRINT));
$ng_words = file_exists($ng_words_file) ? file($ng_words_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
if (isset($_POST['add_ng_word']) && !empty($_POST['ng_word'])) {
    $ng_words[] = $_POST['ng_word'];
    file_put_contents($ng_words_file, implode("
", $ng_words) . "
");
    header('Location: admin.php'); exit;
}
if (isset($_GET['delete_ng_word'])) {
    $word_to_delete = $_GET['delete_ng_word'];
    $ng_words = array_filter($ng_words, function($w) use ($word_to_delete) { return $w !== $word_to_delete; });
    file_put_contents($ng_words_file, implode("
", $ng_words) . "
");
    header('Location: admin.php'); exit;
}
if (isset($_POST['add_ban_id']) && !empty($_POST['ban_id'])) {
    $id_to_ban = $_POST['ban_id'];
    if (!isset($banned_users[$id_to_ban])) {
        $banned_users[$id_to_ban] = ['type' => 'manual', 'ban_time' => time()];
        file_put_contents($banned_users_file, json_encode($banned_users, JSON_PRETTY_PRINT));
    }
    header('Location: admin.php'); exit;
}
if (isset($_GET['action']) && $_GET['action'] === 'unban' && isset($_GET['user_id'])) {
    $user_id_to_unban = $_GET['user_id'];
    if (isset($banned_users[$user_id_to_unban])) {
        unset($banned_users[$user_id_to_unban]);
        file_put_contents($banned_users_file, json_encode($banned_users, JSON_PRETTY_PRINT));
    }
    header('Location: admin.php'); exit;
}
if (isset($_GET['action']) && $_GET['action'] === 'delete_post' && isset($_GET['thread']) && isset($_GET['post_index'])) {
    $thread_file = $data_dir . basename($_GET['thread']);
    $post_index = (int)$_GET['post_index'];
    if (file_exists($thread_file)) {
        $lines = file($thread_file, FILE_IGNORE_NEW_LINES);
        if (isset($lines[$post_index])) {
            $lines[$post_index] = '削除されました';
            file_put_contents($thread_file, implode("
", $lines) . "
");
        }
    }
    header('Location: admin.php'); exit;
}
if (isset($_GET['action']) && $_GET['action'] === 'delete_thread' && isset($_GET['thread'])) {
    $thread_file = $data_dir . basename($_GET['thread']);
    if (file_exists($thread_file)) {
        unlink($thread_file);
    }
    header('Location: admin.php'); exit;
}
$threads = [];
$files = glob($data_dir . '*.dat');
array_multisort(array_map('filemtime', $files), SORT_DESC, $files);
foreach ($files as $file) {
    $posts = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $thread_title = array_shift($posts);
    $threads[] = [
        'id' => basename($file),
        'title' => $thread_title,
        'posts' => $posts
    ];
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>管理ページ</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-container { max-width: 1200px; margin: 0 auto; }
        .admin-section { background: #fff; border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; }
        .admin-section h2 { margin-top: 0; border-bottom: 1px solid #ccc; padding-bottom: 5px; }
        .post-body { background: #f9f9f9; padding: 5px; border: 1px solid #eee; margin: 5px 0; }
        .ng-list li, .ban-list li { display: flex; justify-content: space-between; align-items: center; padding: 5px; border-bottom: 1px dotted #ccc; }
        .delete-btn { color: #fff; background-color: #dc3545; border: none; padding: 3px 8px; text-decoration: none; border-radius: 3px; font-size: 12px; }
        .unban-btn { color: #fff; background-color: #28a745; border: none; padding: 3px 8px; text-decoration: none; border-radius: 3px; font-size: 12px; margin-left: 5px; }
    </style>
</head>
<body>
<div class="admin-container">
    <div class="board-title"><h1>管理ページ</h1></div>
    <p style="text-align:right;"><a href="index.php">掲示板トップへ</a> | <a href="admin.php?action=logout">ログアウト</a></p>
    <div style="display: flex; gap: 20px;">
        <div class="admin-section" style="flex: 1;">
            <h2>NGワード管理</h2>
            <form method="post">
                <input type="text" name="ng_word" placeholder="NGワードを追加">
                <button type="submit" name="add_ng_word">追加</button>
            </form>
            <button onclick="document.getElementById('ngWordListContainer').style.display = 'block'; this.style.display = 'none';" style="margin-top: 10px;">NGワードを表示</button>
            <div id="ngWordListContainer" style="display: none;">
                <ul class="ng-list">
                    <?php foreach ($ng_words as $word): ?>
                    <li><?php echo htmlspecialchars($word); ?> <a href="?delete_ng_word=<?php echo urlencode($word); ?>" class="delete-btn">削除</a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <div class="admin-section" style="flex: 1;">
            <h2>BANユーザー管理</h2>
            <form method="post">
                <input type="text" name="ban_id" placeholder="BANするIDを追加">
                <button type="submit" name="add_ban_id">手動BAN</button>
            </form>
            <ul class="ban-list">
                <?php if (empty($banned_users)): ?>
                    <li>現在、BANされているユーザーはいません。</li>
                <?php else: ?>
                    <?php foreach ($banned_users as $id => $info): ?>
                        <li>
                            ID: <?php echo htmlspecialchars($id); ?>
                            (タイプ: <?php echo ($info['type'] === 'manual') ? '手動BAN' : '自動BAN'; ?>)
                            <?php if (isset($info['ng_count'])): ?>
                                (NG回数: <?php echo $info['ng_count']; ?>回)
                            <?php endif; ?>
                            <?php if (isset($info['ban_until']) && $info['ban_until'] > time()): ?>
                                - 期限: <?php echo date('Y/m/d H:i:s', $info['ban_until']); ?>
                            <?php endif; ?>
                            <a href="?action=unban&user_id=<?php echo urlencode($id); ?>" class="unban-btn">BAN解除</a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    
        <div class="admin-section">
        <h2>スレッド・レス監視</h2>
        <?php foreach ($threads as $thread): ?>
        <div style="margin-bottom: 20px;">
            <h3>
                <?php echo htmlspecialchars($thread['title']); ?>
                <a href="?action=delete_thread&thread=<?php echo urlencode($thread['id']); ?>" onclick="return confirm('本当にこのスレッドを削除しますか？');" class="delete-btn" style="font-size:14px;">スレッドごと削除</a>
            </h3>
            <?php foreach ($thread['posts'] as $index => $post): ?>
                <?php
                $parts = explode('<>', $post, 4);
                if (count($parts) < 4) continue;
                list($name, $mail, $date_id, $comment) = $parts;
                preg_match('/ID:([a-zA-Z0-9\+\/=]+)/', $date_id, $matches);
                $user_id = $matches[1] ?? '';
                ?>
                <div class="post-body">
                    <strong><?php echo $index + 1; ?>: <?php
                        if ($name === $board_config['admin_name']) {
                            echo '<span style="color: red;">' . htmlspecialchars($name) . '</span>';
                        } else {
                            echo htmlspecialchars($name);
                        }
                        ?></strong> [<?php echo htmlspecialchars($date_id); ?>]
                    <div style="margin-left: 20px;"><?php echo nl2br(htmlspecialchars($comment)); ?></div>
                    <div style="text-align:right; font-size:12px;">
                        <a href="?action=delete_post&thread=<?php echo urlencode($thread['id']); ?>&post_index=<?php echo $index + 1; ?>" class="delete-btn">レス削除</a>
                        <?php if ($user_id && !isset($banned_users[$user_id])): ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="ban_id" value="<?php echo htmlspecialchars($user_id); ?>">
                            <button type="submit" name="add_ban_id" class="delete-btn">このIDをBAN</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="admin-section">
        <h2>掲示板情報編集</h2>
        <?php if (isset($_GET['status']) && $_GET['status'] === 'board_info_updated'): ?>
            <p style="color: green;">掲示板情報が更新されました。</p>
        <?php endif; ?>
        <form method="post">
            <p>
                <label for="board_name">掲示板の名前:</label><br>
                <input type="text" id="board_name" name="board_name" value="<?php echo htmlspecialchars($board_config['board_name']); ?>" style="width: 100%; padding: 8px;">
            </p>
            <p>
                <label for="admin_name">管理者の名前:</label><br>
                <input type="text" id="admin_name" name="admin_name" value="<?php echo htmlspecialchars($board_config['admin_name']); ?>" style="width: 100%; padding: 8px;">
            </p>
            <p>
                <label for="admin_contact_link">管理者の連絡先リンク (SNS/Webサイトなど):</label><br>
                <input type="url" id="admin_contact_link" name="admin_contact_link" value="<?php echo htmlspecialchars($board_config['admin_contact_link']); ?>" style="width: 100%; padding: 8px;">
            </p>
            <button type="submit" name="update_board_info">掲示板情報を更新</button>
        </form>
    </div>
    <div class="admin-section">
        <h2>公開設定</h2>
        <?php if (isset($_GET['status']) && $_GET['status'] === 'public_settings_updated'): ?>
            <p style="color: green;">公開設定が更新されました。</p>
        <?php endif; ?>
        <form method="post">
            <p>
                <label for="is_public">掲示板の公開状態:</label><br>
                <select id="is_public" name="is_public" style="width: 100%; padding: 8px;">
                    <option value="1" <?php if (($board_config['is_public'] ?? true) == true) echo 'selected'; ?>>公開</option>
                    <option value="0" <?php if (($board_config['is_public'] ?? true) == false) echo 'selected'; ?>>非公開</option>
                </select>
            </p>
            <p>
                <label for="public_access_password">非公開時のアクセスパスワード (空欄でパスワードなし):</label><br>
                <input type="password" id="public_access_password" name="public_access_password" placeholder="新しいパスワードを入力" style="width: 100%; padding: 8px;">
                <small>非公開設定時にこのパスワードが設定されている場合、閲覧にパスワードが必要になります。</small>
            </p>
            <button type="submit" name="update_public_settings">公開設定を更新</button>
        </form>
    </div>
    <div class="admin-section">
        <h2>管理者パスワード変更</h2>
        <?php if (isset($_GET['status']) && $_GET['status'] === 'admin_password_updated'): ?>
            <p style="color: green;">管理者パスワードが更新されました。</p>
        <?php endif; ?>
        <form method="post">
            <p>
                <label for="new_admin_password">新しい管理者パスワード:</label><br>
                <input type="password" id="new_admin_password" name="new_admin_password" placeholder="新しいパスワードを入力" required style="width: 100%; padding: 8px;">
            </p>
            <button type="submit" name="update_admin_password">パスワードを更新</button>
        </form>
    </div>
    <div class="admin-section">
        <h2>ピン留めスレッド管理</h2>
        <?php if (isset($_GET['status']) && $_GET['status'] === 'pinned_thread_updated'): ?>
            <p style="color: green;">ピン留めスレッドが更新されました。</p>
        <?php endif; ?>
        <form method="post">
            <p>
                <label for="pinned_thread_id">ピン留めするスレッドを選択:</label><br>
                <select id="pinned_thread_id" name="pinned_thread_id" style="width: 100%; padding: 8px;">
                    <option value="">-- ピン留めしない --</option>
                    <?php foreach ($threads as $thread): ?>
                        <option value="<?php echo htmlspecialchars($thread['id']); ?>"
                            <?php if (($board_config['pinned_thread_id'] ?? '') === $thread['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($thread['title']); ?> (<?php echo htmlspecialchars($thread['id']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>「-- ピン留めしない --」を選択するとピン留めが解除されます。</small>
            </p>
            <button type="submit" name="update_pinned_thread">ピン留めスレッドを更新</button>
        </form>
    </div>
    <div class="admin-section">
        <h2>PWA設定</h2>
        <?php if (isset($_GET['status']) && $_GET['status'] === 'pwa_settings_updated'): ?>
            <p style="color: green;">PWA設定が更新されました。</p>
        <?php endif; ?>
        <form method="post">
            <p>
                <label for="pwa_name">アプリ名:</label><br>
                <input type="text" id="pwa_name" name="pwa_name" value="<?php echo htmlspecialchars($pwa_config['name']); ?>" style="width: 100%; padding: 8px;">
            </p>
            <p>
                <label for="pwa_short_name">短いアプリ名:</label><br>
                <input type="text" id="pwa_short_name" name="pwa_short_name" value="<?php echo htmlspecialchars($pwa_config['short_name']); ?>" style="width: 100%; padding: 8px;">
            </p>
            <p>
                <label for="pwa_start_url">開始URL:</label><br>
                <input type="text" id="pwa_start_url" name="pwa_start_url" value="<?php echo htmlspecialchars($pwa_config['start_url']); ?>" style="width: 100%; padding: 8px;">
            </p>
            <p>
                <label for="pwa_display">表示モード:</label><br>
                <select id="pwa_display" name="pwa_display" style="width: 100%; padding: 8px;">
                    <option value="standalone" <?php if ($pwa_config['display'] === 'standalone') echo 'selected'; ?>>standalone</option>
                    <option value="fullscreen" <?php if ($pwa_config['display'] === 'fullscreen') echo 'selected'; ?>>fullscreen</option>
                    <option value="minimal-ui" <?php if ($pwa_config['display'] === 'minimal-ui') echo 'selected'; ?>>minimal-ui</option>
                    <option value="browser" <?php if ($pwa_config['display'] === 'browser') echo 'selected'; ?>>browser</option>
                </select>
            </p>
            <p>
                <label for="pwa_background_color">背景色:</label><br>
                <input type="color" id="pwa_background_color" name="pwa_background_color" value="<?php echo htmlspecialchars($pwa_config['background_color']); ?>" style="width: 100%; padding: 8px;">
            </p>
            <p>
                <label for="pwa_theme_color">テーマカラー:</label><br>
                <input type="color" id="pwa_theme_color" name="pwa_theme_color" value="<?php echo htmlspecialchars($pwa_config['theme_color']); ?>" style="width: 100%; padding: 8px;">
            </p>
            <p>
                <label for="pwa_icon_path">アイコンパス (images/からの相対パス):</label><br>
                <input type="text" id="pwa_icon_path" name="pwa_icon_path" value="<?php echo htmlspecialchars($pwa_config['icon_path']); ?>" style="width: 100%; padding: 8px;">
                <small>例: images/icon-512x512.png (512x512pxのPNG画像をimagesフォルダに配置してください)</small>
            </p>
            <button type="submit" name="update_pwa_settings">PWA設定を更新</button>
        </form>
    </div>
    <div class="admin-section">
        <h2>Googleアナリティクス設定</h2>
        <?php if (isset($_GET['status']) && $_GET['status'] === 'ga_settings_updated'): ?>
            <p style="color: green;">Googleアナリティクス設定が更新されました。</p>
        <?php endif; ?>
        <form method="post">
            <p>
                <label for="google_analytics_id">Googleアナリティクス測定ID (例: G-XXXXXXXXXX または UA-XXXXXXXXX-Y):</label><br>
                <input type="text" id="google_analytics_id" name="google_analytics_id" value="<?php echo htmlspecialchars($board_config['google_analytics_id'] ?? ''); ?>" style="width: 100%; padding: 8px;">
                <small>空欄にするとGoogleアナリティクスは無効になります。</small>
            </p>
            <button type="submit" name="update_ga_settings">Googleアナリティクス設定を更新</button>
        </form>
    </div>
    <div class="admin-section">
        <h2>広告設定</h2>
        <?php if (isset($_GET['status']) && $_GET['status'] === 'ad_settings_updated'): ?>
            <p style="color: green;">広告設定が更新されました。</p>
        <?php endif; ?>
        <form method="post">
            <p>
                <label for="footer_ad_code">フッター広告コード:</label><br>
                <textarea id="footer_ad_code" name="footer_ad_code" rows="8" style="width: 100%; padding: 8px;"><?php echo htmlspecialchars($board_config['footer_ad_code'] ?? ''); ?></textarea>
                <small>Googleアドセンス、楽天アフィリエイトなどの広告コードを貼り付けてください。</small>
            </p>
            <p>
                <label for="sidebar_ad_code">サイドバー広告コード:</label><br>
                <textarea id="sidebar_ad_code" name="sidebar_ad_code" rows="8" style="width: 100%; padding: 8px;"><?php echo htmlspecialchars($board_config['sidebar_ad_code'] ?? ''); ?></textarea>
                <small>Googleアドセンス、楽天アフィリエイトなどの広告コードを貼り付けてください。</small>
            </p>
            <button type="submit" name="update_ad_settings">広告設定を更新</button>
        </form>
    </div>

    <div class="admin-section">
        <h2>カスタムHTML/CSS編集 (スレッド一覧ページ)</h2>
        <form method="post">
            <p>
                <label for="custom_index_html">カスタムHTML:</label><br>
                <textarea id="custom_index_html" name="custom_index_html" rows="10" style="width: 100%; padding: 8px;"><?php echo htmlspecialchars($board_config['custom_index_html']); ?></textarea>
            </p>
            <p>
                <label for="custom_index_css">カスタムCSS:</label><br>
                <textarea id="custom_index_css" name="custom_index_css" rows="10" style="width: 100%; padding: 8px;"><?php echo htmlspecialchars($board_config['custom_index_css']); ?></textarea>
            </p>
            <button type="submit" name="update_board_info">カスタムHTML/CSSを更新</button>
        </form>
    </div>
    <div class="admin-section">
        <h2>カスタムHTML/CSS編集 (スレッド表示ページ)</h2>
        <form method="post">
            <p>
                <label for="custom_thread_html">カスタムHTML:</label><br>
                <textarea id="custom_thread_html" name="custom_thread_html" rows="10" style="width: 100%; padding: 8px;"><?php echo htmlspecialchars($board_config['custom_thread_html']); ?></textarea>
            </p>
            <p>
                <label for="custom_thread_css">カスタムCSS:</label><br>
                <textarea id="custom_thread_css" name="custom_thread_css" rows="10" style="width: 100%; padding: 8px;"><?php echo htmlspecialchars($board_config['custom_thread_css']); ?></textarea>
            </p>
            <button type="submit" name="update_board_info">カスタムHTML/CSSを更新</button>
        </form>
    </div>
    <footer>
        <p>© 2025 <a href="https://github.com/koba_9813">Koba_9813</a> All rights reserved.</p>
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
    </footer>
</body>
</html>
