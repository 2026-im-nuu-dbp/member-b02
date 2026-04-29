<?php
// ============================================================
// show_news.php — 討論內容頁
// 功能: 顯示單一討論的完整內容與所有回應，以及發表回應的表單
// 修改重點:
//   1. 加入 session_start() 讀取登入狀態
//   2. 讀取 news 時 JOIN members 取得發文者的暱稱與大頭貼
//   3. 讀取 replies 時 JOIN members 取得回應者的暱稱、大頭貼、喜歡顏色
//      喜歡顏色(fav_color)用於回應留言欄的背景色 (選項功能)
//   4. 回應表單移除「作者」輸入欄，改由 session 自動帶入
//   5. 未登入者只能瀏覽，不顯示回應表單
// ============================================================

header('Content-Type: text/html; charset=utf-8');
session_start();         // 啟動 session，讀取目前登入的會員資訊
require 'db_config.php';

// 從 session 取得登入會員；null 表示尚未登入
$member = $_SESSION['member'] ?? null;

// 從 GET 參數取得討論 ID，並強制轉為整數防止 SQL Injection
$newsId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($newsId <= 0) {
    die('無效的討論 ID。<br><a href="index.php">返回首頁</a>');
}

// ── 讀取討論主題 ──────────────────────────────────────────────
// 改為 JOIN members，取得發文者的 nickname 與 avatar
// 不再使用 news.author 欄位
try {
    $stmt = $pdo->prepare('
        SELECT
            n.id,
            n.title,
            n.content,
            n.created_at,
            m.nickname,     -- 發文者暱稱
            m.avatar        -- 發文者大頭貼路徑
        FROM news n
        JOIN members m ON n.member_id = m.id
        WHERE n.id = ?
    ');
    $stmt->execute([$newsId]);
    $news = $stmt->fetch();

    if (!$news) {
        die('找不到此討論。<br><a href="index.php">返回首頁</a>');
    }
} catch (PDOException $e) {
    die('讀取討論失敗: ' . $e->getMessage());
}

// ── 讀取回應列表 ──────────────────────────────────────────────
// 同樣 JOIN members，額外取得 fav_color 用於回應背景色
try {
    $stmt = $pdo->prepare('
        SELECT
            r.id,
            r.content,
            r.created_at,
            m.nickname,     -- 回應者暱稱
            m.avatar,       -- 回應者大頭貼
            m.fav_color     -- 回應者喜歡顏色，作為留言背景色 (選項功能)
        FROM replies r
        JOIN members m ON r.member_id = m.id
        WHERE r.news_id = ?
        ORDER BY r.created_at ASC   -- 依時間正序，舊的在上
    ');
    $stmt->execute([$newsId]);
    $replies = $stmt->fetchAll();
} catch (PDOException $e) {
    $error   = '讀取回應失敗: ' . $e->getMessage();
    $replies = [];
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <title><?= escape($news['title']) ?> - 討論區</title>
    <style>
        body {
            font-family: system-ui, -apple-system, Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .container { max-width: 900px; margin: 0 auto; }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #007bff;
            text-decoration: none;
        }
        .back-link:hover { text-decoration: underline; }

        /* 討論內容區塊 */
        .news-content {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .news-title { font-size: 24px; font-weight: bold; margin-bottom: 10px; color: #333; }

        /* 發文者資訊列：大頭貼 + 暱稱 + 時間 */
        .news-meta {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .news-meta img {
            width: 32px;
            height: 32px;
            border-radius: 50%;   /* 圓形大頭貼 */
            object-fit: cover;
            border: 2px solid #007bff;
        }
        .news-body { line-height: 1.8; color: #333; white-space: pre-wrap; }

        /* 回應區塊 */
        .reply-section {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /*
         * .reply-item 的背景色由 PHP inline style 帶入 fav_color，
         * 這裡只設定預設樣式；若 fav_color 為 #ffffff 則與預設相同。
         */
        .reply-item {
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #007bff;
            border-radius: 4px;
        }

        /* 回應者資訊列：大頭貼 + 暱稱 + 時間 */
        .reply-author {
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .reply-author img {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
        }
        .reply-time { font-size: 12px; color: #999; font-weight: normal; }
        .reply-content { margin-top: 10px; line-height: 1.6; color: #333; white-space: pre-wrap; }

        /* 回應表單 */
        .form-box {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }

        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: inherit;
            box-sizing: border-box;
            resize: vertical;
            min-height: 100px;
        }
        button {
            background: #28a745;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover { background: #218838; }

        /* 未登入提示框 */
        .login-notice {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 12px 16px;
            border-radius: 6px;
        }
        .login-notice a { color: #007bff; }

        .empty { text-align: center; color: #999; padding: 20px; font-style: italic; }
        h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="container">

    <a href="index.php" class="back-link">← 返回討論列表</a>

    <!-- ── 討論主題內容 ── -->
    <div class="news-content">
        <div class="news-title"><?= escape($news['title']) ?></div>

        <!-- 發文者資訊：大頭貼 + 暱稱 + 時間，取代舊的純文字 author -->
        <div class="news-meta">
            <img
                src="<?= escape($news['avatar'] ?? 'uploads/default_avatar.png') ?>"
                alt="大頭貼"
                title="<?= escape($news['nickname']) ?>"
            >
            由 <strong><?= escape($news['nickname']) ?></strong> 發表於
            <?= escape($news['created_at']) ?>
        </div>

        <div class="news-body"><?= escape($news['content']) ?></div>
    </div>

    <!-- ── 回應列表 ── -->
    <div class="reply-section">
        <h2>回應 (<?= count($replies) ?>)</h2>

        <?php if (empty($replies)): ?>
            <p class="empty">目前沒有回應。</p>
        <?php else: ?>
            <?php foreach ($replies as $reply): ?>
                <!--
                    背景色使用回應者的 fav_color (選項功能)。
                    fav_color 儲存為 HEX 字串，例如 #ffe0b2。
                    若不想實作此功能，移除 style 屬性即可。
                -->
                <div class="reply-item" style="background-color: <?= escape($reply['fav_color']) ?>;">
                    <!-- 回應者：大頭貼 + 暱稱 + 時間，取代舊的純文字 author -->
                    <div class="reply-author">
                        <img
                            src="<?= escape($reply['avatar'] ?? 'uploads/default_avatar.png') ?>"
                            alt="大頭貼"
                            title="<?= escape($reply['nickname']) ?>"
                        >
                        <?= escape($reply['nickname']) ?>
                        <span class="reply-time">
                            - <?= escape($reply['created_at']) ?>
                        </span>
                    </div>
                    <div class="reply-content">
                        <?= escape($reply['content']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ── 發表回應表單 ── -->
    <?php if ($member): ?>
        <!-- 已登入才顯示回應表單 -->
        <div class="form-box">
            <h2>發表回應</h2>
            <form action="post_reply.php" method="post">

                <!-- 傳遞討論 ID 給 post_reply.php -->
                <input type="hidden" name="news_id" value="<?= $newsId ?>">

                <!--
                    作者欄位已移除：
                    原本是 <input name="author"> 讓使用者手動輸入。
                    現在改由 post_reply.php 從 $_SESSION['member']['id'] 取得，
                    不需要也不應該讓前端傳入，以防止偽造作者身份。
                -->

                <div class="form-group">
                    <label for="content">回應內容：</label>
                    <textarea id="content" name="content" required></textarea>
                </div>

                <button type="submit">送出回應</button>
            </form>
        </div>
    <?php else: ?>
        <!-- 未登入：顯示提示，引導到登入頁 -->
        <div class="login-notice">
            請先 <a href="login.php">登入</a> 才能發表回應。
        </div>
    <?php endif; ?>

</div>
</body>
</html>