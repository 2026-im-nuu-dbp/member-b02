<?php
// ============================================================
// register.php — 會員註冊
// 功能: 顯示註冊表單，並處理新會員的建立
// 欄位: 帳號、密碼、暱稱、喜歡顏色、大頭貼(圖片上傳)
// 流程:
//   GET  → 顯示空白註冊表單
//   POST → 驗證輸入 → 上傳大頭貼 → 寫入 members 資料表 → 導向登入頁
// ============================================================

header('Content-Type: text/html; charset=utf-8');
session_start();
require 'db_config.php';

// 已登入者不需要註冊，直接導回首頁
if (isset($_SESSION['member'])) {
    header('Location: index.php');
    exit;
}

$error = '';  // 儲存錯誤訊息，顯示在表單上方

// ── 處理 POST 表單提交 ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 讀取並清理文字輸入
    $username  = trim($_POST['username']  ?? '');
    $password  = trim($_POST['password']  ?? '');
    $nickname  = trim($_POST['nickname']  ?? '');
    $favColor  = trim($_POST['fav_color'] ?? '#ffffff');

    // ── 基本欄位驗證 ─────────────────────────────────────────
    if (empty($username) || empty($password) || empty($nickname)) {
        $error = '帳號、密碼、暱稱為必填欄位。';

    } elseif (strlen($username) > 50) {
        $error = '帳號最長 50 字元。';

    } elseif (strlen($password) < 6) {
        $error = '密碼至少需要 6 個字元。';

    } elseif (!preg_match('/^#[0-9a-fA-F]{6}$/', $favColor)) {
        // 確保顏色為合法的 HEX 格式，防止 XSS 或亂填
        $error = '顏色格式不正確，請使用色票選擇器。';

    } else {

        // ── 檢查帳號是否已被使用 ─────────────────────────────
        $stmt = $pdo->prepare('SELECT id FROM members WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = '此帳號已被使用，請選擇其他帳號。';
        } else {

            // ── 處理大頭貼上傳 ───────────────────────────────
            // 預設使用系統預設大頭貼，若使用者有上傳則覆蓋
            $avatarPath = 'uploads/default_avatar.png';

            if (!empty($_FILES['avatar']['name'])) {
                $file     = $_FILES['avatar'];
                $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                if (!in_array($ext, $allowed)) {
                    $error = '大頭貼只允許 jpg、png、gif、webp 格式。';
                } elseif ($file['size'] > 2 * 1024 * 1024) {
                    // 限制檔案大小 2MB，避免伺服器儲存空間被佔滿
                    $error = '大頭貼檔案不能超過 2MB。';
                } else {
                    // 用 uniqid 產生唯一檔名，避免不同使用者的檔名衝突
                    $filename   = 'avatar_' . uniqid() . '.' . $ext;
                    $uploadDir  = 'uploads/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true); // 若目錄不存在則建立
                    }
                    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                        $avatarPath = $uploadDir . $filename;
                    } else {
                        $error = '大頭貼上傳失敗，請再試一次。';
                    }
                }
            }

            // ── 寫入資料庫 ───────────────────────────────────
            if (empty($error)) {
                // password_hash 使用 bcrypt 演算法，不儲存明文密碼
                $hash = password_hash($password, PASSWORD_BCRYPT);

                try {
                    $stmt = $pdo->prepare('
                        INSERT INTO members (username, password, nickname, fav_color, avatar)
                        VALUES (?, ?, ?, ?, ?)
                    ');
                    $stmt->execute([
                        substr($username, 0, 50),
                        $hash,
                        substr($nickname, 0, 100),
                        $favColor,
                        $avatarPath,
                    ]);

                    // 註冊成功，導向登入頁並帶提示參數
                    header('Location: login.php?registered=1');
                    exit;
                } catch (PDOException $e) {
                    $error = '註冊失敗: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <title>會員註冊 - 討論區</title>
    <style>
        body {
            font-family: system-ui, -apple-system, Arial, sans-serif;
            margin: 0; padding: 40px 20px;
            background: #f5f5f5;
            display: flex; justify-content: center;
        }
        .box {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
            width: 100%; max-width: 460px;
        }
        h1 { margin-top: 0; color: #333; font-size: 22px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; font-size: 14px; }
        input[type="text"],
        input[type="password"],
        input[type="color"],
        input[type="file"] {
            width: 100%; padding: 9px 10px;
            border: 1px solid #ddd; border-radius: 4px;
            font-family: inherit; font-size: 14px;
            box-sizing: border-box;
        }
        input[type="color"] {
            height: 40px; padding: 2px 4px; cursor: pointer;
        }
        .hint { font-size: 12px; color: #888; margin-top: 4px; }
        button {
            width: 100%; padding: 11px;
            background: #007bff; color: #fff;
            border: none; border-radius: 4px;
            font-size: 16px; cursor: pointer; margin-top: 6px;
        }
        button:hover { background: #0056b3; }
        .error {
            background: #ffebee; color: #c62828;
            padding: 10px 14px; border-radius: 4px;
            margin-bottom: 18px; font-size: 14px;
        }
        .footer-link { text-align: center; margin-top: 16px; font-size: 14px; }
        .footer-link a { color: #007bff; }
    </style>
</head>
<body>
<div class="box">
    <h1>📝 會員註冊</h1>

    <?php if ($error): ?>
        <div class="error"><?= escape($error) ?></div>
    <?php endif; ?>

    <!--
        enctype="multipart/form-data" 是上傳檔案必須設定的屬性，
        沒有它 $_FILES 會是空的
    -->
    <form method="post" enctype="multipart/form-data">

        <div class="form-group">
            <label for="username">帳號</label>
            <input type="text" id="username" name="username"
                   maxlength="50" required
                   value="<?= escape($_POST['username'] ?? '') ?>">
            <p class="hint">最長 50 字元，不可重複</p>
        </div>

        <div class="form-group">
            <label for="password">密碼</label>
            <input type="password" id="password" name="password"
                   minlength="6" required>
            <p class="hint">至少 6 個字元</p>
        </div>

        <div class="form-group">
            <label for="nickname">暱稱</label>
            <input type="text" id="nickname" name="nickname"
                   maxlength="100" required
                   value="<?= escape($_POST['nickname'] ?? '') ?>">
            <p class="hint">顯示在討論區的名稱</p>
        </div>

        <div class="form-group">
            <label for="fav_color">喜歡的顏色</label>
            <!--
                type="color" 會顯示色票選擇器，
                值為 HEX 格式如 #ff5733，存入 members.fav_color
            -->
            <input type="color" id="fav_color" name="fav_color"
                   value="<?= escape($_POST['fav_color'] ?? '#ffffff') ?>">
            <p class="hint">將作為你回應留言的背景色</p>
        </div>

        <div class="form-group">
            <label for="avatar">大頭貼（選填）</label>
            <input type="file" id="avatar" name="avatar"
                   accept="image/jpeg,image/png,image/gif,image/webp">
            <p class="hint">jpg / png / gif / webp，最大 2MB；不上傳則使用預設圖</p>
        </div>

        <button type="submit">註冊</button>
    </form>

    <div class="footer-link">
        已有帳號？<a href="login.php">立即登入</a>
    </div>
</div>
</body>
</html>
