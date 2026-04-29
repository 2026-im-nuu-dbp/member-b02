<?php
// ============================================================
// profile.php — 會員個人資料編輯
// 功能: 讓已登入的會員修改自己的暱稱、喜歡顏色、大頭貼、密碼
// 流程:
//   GET  → 顯示目前資料的編輯表單
//   POST → 驗證輸入 → 處理新大頭貼(若有上傳) → 更新資料庫
//          → 更新 session → 顯示成功訊息
// ============================================================

header('Content-Type: text/html; charset=utf-8');
session_start();
require 'db_config.php';

// ── 登入檢查 ──────────────────────────────────────────────────
// 未登入者無法進入個人資料頁
$member = $_SESSION['member'] ?? null;
if (!$member) {
    header('Location: login.php');
    exit;
}

$error   = '';
$success = '';

// ── 從資料庫取得最新會員資料 ─────────────────────────────────
// 不直接用 session，避免 session 資料與資料庫不同步
try {
    $stmt = $pdo->prepare('
        SELECT id, username, nickname, fav_color, avatar
        FROM members WHERE id = ?
    ');
    $stmt->execute([$member['id']]);
    $row = $stmt->fetch();
} catch (PDOException $e) {
    die('讀取資料失敗: ' . $e->getMessage());
}

// ── 處理 POST 表單提交 ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nickname = trim($_POST['nickname']  ?? '');
    $favColor = trim($_POST['fav_color'] ?? '#ffffff');
    $newPass  = trim($_POST['password']  ?? '');
    $confirm  = trim($_POST['confirm']   ?? '');

    // ── 欄位驗證 ─────────────────────────────────────────────
    if (empty($nickname)) {
        $error = '暱稱不能為空。';

    } elseif (!preg_match('/^#[0-9a-fA-F]{6}$/', $favColor)) {
        $error = '顏色格式不正確。';

    } elseif (!empty($newPass) && strlen($newPass) < 6) {
        $error = '新密碼至少需要 6 個字元。';

    } elseif (!empty($newPass) && $newPass !== $confirm) {
        // 確認密碼需與新密碼相符
        $error = '兩次輸入的密碼不一致。';

    } else {

        // ── 處理大頭貼上傳 ───────────────────────────────────
        // 預設保留目前的大頭貼路徑
        $avatarPath = $row['avatar'];

        if (!empty($_FILES['avatar']['name'])) {
            $file    = $_FILES['avatar'];
            $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($ext, $allowed)) {
                $error = '大頭貼只允許 jpg、png、gif、webp 格式。';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $error = '大頭貼檔案不能超過 2MB。';
            } else {
                $filename  = 'avatar_' . uniqid() . '.' . $ext;
                $uploadDir = 'uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    // 上傳成功後，刪除舊大頭貼(若非預設圖)，節省伺服器空間
                    if ($row['avatar'] && $row['avatar'] !== 'uploads/default_avatar.png'
                        && file_exists($row['avatar'])) {
                        unlink($row['avatar']);
                    }
                    $avatarPath = $uploadDir . $filename;
                } else {
                    $error = '大頭貼上傳失敗，請再試一次。';
                }
            }
        }

        // ── 更新資料庫 ───────────────────────────────────────
        if (empty($error)) {
            try {
                if (!empty($newPass)) {
                    // 有填新密碼 → 一併更新密碼欄位
                    $hash = password_hash($newPass, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare('
                        UPDATE members
                        SET nickname = ?, fav_color = ?, avatar = ?, password = ?
                        WHERE id = ?
                    ');
                    $stmt->execute([
                        substr($nickname, 0, 100),
                        $favColor,
                        $avatarPath,
                        $hash,
                        $member['id'],
                    ]);
                } else {
                    // 密碼留空 → 不更新密碼
                    $stmt = $pdo->prepare('
                        UPDATE members
                        SET nickname = ?, fav_color = ?, avatar = ?
                        WHERE id = ?
                    ');
                    $stmt->execute([
                        substr($nickname, 0, 100),
                        $favColor,
                        $avatarPath,
                        $member['id'],
                    ]);
                }

                // ── 更新 session，讓頁首立即反映新資料 ──────
                // 不更新 session 的話，使用者要登出再登入才會看到新大頭貼
                $_SESSION['member']['nickname']  = substr($nickname, 0, 100);
                $_SESSION['member']['fav_color'] = $favColor;
                $_SESSION['member']['avatar']    = $avatarPath;

                // 更新本地變數供頁面顯示用
                $row['nickname']  = substr($nickname, 0, 100);
                $row['fav_color'] = $favColor;
                $row['avatar']    = $avatarPath;

                $success = '個人資料已更新成功！';

            } catch (PDOException $e) {
                $error = '更新失敗: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <title>個人資料 - 討論區</title>
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
            width: 100%; max-width: 480px;
        }
        h1 { margin-top: 0; color: #333; font-size: 22px; }

        /* 目前大頭貼預覽區 */
        .avatar-preview {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            padding: 16px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        .avatar-preview img {
            width: 72px; height: 72px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #007bff;
        }
        .avatar-preview .info { font-size: 15px; color: #333; }
        .avatar-preview .info strong { display: block; font-size: 17px; margin-bottom: 4px; }

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
        input[type="color"] { height: 40px; padding: 2px 4px; cursor: pointer; }
        .hint { font-size: 12px; color: #888; margin-top: 4px; }

        /* 分隔線區塊 */
        .section-title {
            font-size: 13px; font-weight: bold;
            color: #888; text-transform: uppercase;
            letter-spacing: 0.5px;
            border-top: 1px solid #eee;
            padding-top: 16px; margin: 20px 0 12px;
        }

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
            margin-bottom: 16px; font-size: 14px;
        }
        .success {
            background: #e8f5e9; color: #2e7d32;
            padding: 10px 14px; border-radius: 4px;
            margin-bottom: 16px; font-size: 14px;
        }
        .footer-link { text-align: center; margin-top: 16px; font-size: 14px; }
        .footer-link a { color: #007bff; }
    </style>
</head>
<body>
<div class="box">
    <h1>👤 個人資料</h1>

    <!-- 目前大頭貼 + 暱稱預覽 -->
    <div class="avatar-preview">
        <img
            src="<?= escape($row['avatar'] ?? 'uploads/default_avatar.png') ?>"
            alt="目前大頭貼"
            id="previewImg"
        >
        <div class="info">
            <strong><?= escape($row['nickname']) ?></strong>
            帳號：<?= escape($row['username']) ?>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="error"><?= escape($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="success"><?= escape($success) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">

        <!-- ── 基本資料 ── -->
        <div class="form-group">
            <label for="nickname">暱稱</label>
            <input type="text" id="nickname" name="nickname"
                   maxlength="100" required
                   value="<?= escape($row['nickname']) ?>">
        </div>

        <div class="form-group">
            <label for="fav_color">喜歡的顏色</label>
            <input type="color" id="fav_color" name="fav_color"
                   value="<?= escape($row['fav_color']) ?>">
            <p class="hint">顯示為你的回應留言背景色</p>
        </div>

        <div class="form-group">
            <label for="avatar">更換大頭貼</label>
            <input type="file" id="avatar" name="avatar"
                   accept="image/jpeg,image/png,image/gif,image/webp">
            <p class="hint">jpg / png / gif / webp，最大 2MB；不選擇則保留目前圖片</p>
        </div>

        <!-- ── 修改密碼（選填）── -->
        <div class="section-title">修改密碼（選填）</div>

        <div class="form-group">
            <label for="password">新密碼</label>
            <input type="password" id="password" name="password" minlength="6">
            <p class="hint">留空則不更改密碼</p>
        </div>

        <div class="form-group">
            <label for="confirm">確認新密碼</label>
            <input type="password" id="confirm" name="confirm" minlength="6">
        </div>

        <button type="submit">儲存變更</button>
    </form>

    <div class="footer-link">
        <a href="index.php">← 返回討論區</a>
    </div>
</div>

<script>
    // 選擇新大頭貼時，立即在頁面上預覽，不需等到上傳完成
    document.getElementById('avatar').addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('previewImg').src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
</script>
</body>
</html>