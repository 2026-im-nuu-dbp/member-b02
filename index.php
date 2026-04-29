<?php
// ============================================================
// index.php — 討論區首頁
// 功能: 顯示所有討論主題列表，以及發表新討論的表單
// 修改重點:
//   1. 加入 session_start() 讀取登入狀態
//   2. SQL 改為 JOIN members 取得暱稱(nickname)與大頭貼(avatar)
//      取代原本直接存在 news.author 的純文字作者名稱
//   3. 發表表單移除「作者」輸入欄，改由 session 自動帶入
//   4. 未登入者只能瀏覽，不顯示發表表單
// ============================================================

header('Content-Type: text/html; charset=utf-8');
session_start();         // 啟動 session，用來讀取登入會員資訊
require 'db_config.php';

// 從 session 取得目前登入的會員資料
// $_SESSION['member'] 由 login.php 登入成功後寫入
$member = $_SESSION['member'] ?? null;
// $member 為 null 表示尚未登入

// ── 讀取所有討論主題 ──────────────────────────────────────────
// 改用 JOIN members 取得暱稱與大頭貼，不再使用 news.author 欄位
try {
    $stmt = $pdo->query('
        SELECT
            n.id,
            n.title,
            n.created_at,
            m.nickname,          -- 顯示暱稱取代舊的 author 文字
            m.avatar,            -- 大頭貼路徑，用於顯示小圖示
            COUNT(r.id) AS reply_count
        FROM news n
        JOIN  members m ON n.member_id = m.id   -- 關聯到會員資料表
        LEFT JOIN replies r ON n.id = r.news_id  -- LEFT JOIN 保留沒有回應的討論
        GROUP BY n.id, n.title, n.created_at, m.nickname, m.avatar
        ORDER BY n.created_at DESC               -- 最新討論排在最上面
    ');
    $news = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = '讀取討論失敗: ' . $e->getMessage();
    $news  = [];
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <title>討論區</title>
    <style>
        body {
            font-family: system-ui, -apple-system, Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .container { max-width: 900px; margin: 0 auto; }

        /* 頁首列：標題 + 登入/登出按鈕 */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; margin: 0; }

        /* 登入者資訊區塊 */
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: #333;
        }
        .user-info img {
            width: 36px;
            height: 36px;
            border-radius: 50%;   /* 圓形大頭貼 */
            object-fit: cover;
            border: 2px solid #007bff;
        }
        .user-info a {
            color: #dc3545;
            text-decoration: none;
            font-size: 13px;
        }
        .user-info a:hover { text-decoration: underline; }

        .form-box {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }

        input[type="text"], textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
            box-sizing: border-box;
        }
        textarea { resize: vertical; min-height: 100px; }

        button {
            background: #007bff;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover { background: #0056b3; }

        /* 未登入提示框 */
        .login-notice {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 12px 16px;
            border-radius: 6px;
            margin: 20px 0 30px;
        }
        .login-notice a { color: #007bff; }

        .news-list {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .news-item {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }
        .news-item:last-child { border-bottom: none; }
        .news-item:hover { background: #f9f9f9; }

        .news-title { font-size: 18px; font-weight: bold; margin-bottom: 8px; }
        .news-title a { color: #007bff; text-decoration: none; }
        .news-title a:hover { text-decoration: underline; }

        .news-meta {
            font-size: 14px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        /* 大頭貼縮圖顯示在作者名稱旁 */
        .news-meta img {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            object-fit: cover;
            vertical-align: middle;
        }

        .reply-count {
            display: inline-block;
            background: #28a745;
            color: #fff;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            margin-left: 10px;
        }
        .empty { text-align: center; color: #999; padding: 40px; font-style: italic; }
        .error {
            color: #d32f2f;
            padding: 10px;
            background: #ffebee;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="container">

    <!-- 頁首：標題 + 登入狀態 -->
    <div class="top-bar">
        <h1>📋 討論區</h1>
        <div class="user-info">
            <?php if ($member): ?>
                <!-- 已登入：顯示大頭貼 + 暱稱 + 登出連結 -->
                <img
                    src="<?= escape($member['avatar'] ?? 'uploads/default_avatar.png') ?>"
                    alt="大頭貼"
                    title="<?= escape($member['nickname']) ?>"
                >
                <?= escape($member['nickname']) ?>
                <a href="profile.php">個人資料</a>
                <a href="logout.php">登出</a>

                <?php if ($member['is_admin']): ?>
                    <!-- 管理員額外顯示管理介面連結 -->
                    <a href="admin.php" style="color:#6f42c1;">管理介面</a>
                <?php endif; ?>
            <?php else: ?>
                <!-- 未登入：顯示登入/註冊連結 -->
                <a href="login.php">登入</a> |
                <a href="register.php">註冊</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="error"><?= escape($error) ?></div>
    <?php endif; ?>

    <!-- ── 發表新討論表單 ── -->
    <?php if ($member): ?>
        <!-- 已登入才顯示發表表單 -->
        <div class="form-box">
            <h2>發表新討論</h2>
            <form action="post.php" method="post">

                <!--
                    作者欄位已移除：
                    原本是 <input name="author"> 讓使用者手動輸入。
                    現在改由 post.php 從 $_SESSION['member']['id'] 取得，
                    不需要也不應該讓前端傳入，以防止偽造作者身份。
                -->

                <div class="form-group">
                    <label for="title">標題：</label>
                    <input type="text" id="title" name="title" maxlength="200" required>
                </div>

                <div class="form-group">
                    <label for="content">內容：</label>
                    <textarea id="content" name="content" required></textarea>
                </div>

                <button type="submit">發表討論</button>
            </form>
        </div>
    <?php else: ?>
        <!-- 未登入：顯示提示，引導到登入頁 -->
        <div class="login-notice">
            請先 <a href="login.php">登入</a> 才能發表討論。
        </div>
    <?php endif; ?>

    <!-- ── 討論列表 ── -->
    <h2>討論列表</h2>

    <?php if (empty($news)): ?>
        <div class="news-list">
            <p class="empty">目前沒有討論。</p>
        </div>
    <?php else: ?>
        <div class="news-list">
            <?php foreach ($news as $item): ?>
                <div class="news-item">
                    <div class="news-title">
                        <a href="show_news.php?id=<?= $item['id'] ?>">
                            <?= escape($item['title']) ?>
                        </a>
                        <?php if ($item['reply_count'] > 0): ?>
                            <span class="reply-count">
                                <?= $item['reply_count'] ?> 則回應
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="news-meta">
                        <!-- 大頭貼縮圖：若會員未上傳則顯示預設圖 -->
                        <img
                            src="<?= escape($item['avatar'] ?? 'uploads/default_avatar.png') ?>"
                            alt="大頭貼"
                        >
                        <!-- 顯示暱稱，取代舊的 $item['author'] -->
                        由 <strong><?= escape($item['nickname']) ?></strong> 發表於
                        <?= escape($item['created_at']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
</body>
</html>