<?php
// ============================================================
// login.php — 會員登入
// 功能: 顯示登入表單，驗證帳號密碼後建立 session
// 流程:
//   GET  → 顯示登入表單（若帶 ?registered=1 顯示註冊成功提示）
//   POST → 查詢帳號 → password_verify 比對密碼 →
//          寫入 $_SESSION['member'] → 導回首頁
// ============================================================

header('Content-Type: text/html; charset=utf-8');
session_start();
require 'db_config.php';

// 已登入者不需要再登入，直接導回首頁
if (isset($_SESSION['member'])) {
    header('Location: index.php');
    exit;
}

$error = '';

// ── 處理 POST 表單提交 ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = '請填寫帳號與密碼。';
    } else {

        // ── 查詢帳號是否存在 ─────────────────────────────────
        // 只用 username 查詢，密碼比對交給 password_verify()
        // 不在 SQL 中比對密碼，因為 bcrypt hash 每次不同
        try {
            $stmt = $pdo->prepare('
                SELECT id, username, password, nickname, avatar, fav_color, is_admin
                FROM members
                WHERE username = ?
                LIMIT 1
            ');
            $stmt->execute([$username]);
            $row = $stmt->fetch();
        } catch (PDOException $e) {
            die('登入查詢失敗: ' . $e->getMessage());
        }

        // ── 驗證密碼 ─────────────────────────────────────────
        // password_verify() 將輸入的明文密碼與資料庫中的 bcrypt hash 比對
        // 故意不區分「帳號不存在」或「密碼錯誤」，統一顯示同一錯誤訊息，
        // 避免讓攻擊者透過錯誤訊息探測帳號是否存在
        if (!$row || !password_verify($password, $row['password'])) {
            $error = '帳號或密碼錯誤。';
        } else {

            // ── 登入成功：寫入 session ────────────────────────
            // 將常用的會員資訊存入 session，讓其他頁面直接讀取
            // 不存密碼 hash，session 中不應有敏感資料
            $_SESSION['member'] = [
                'id'       => $row['id'],
                'username' => $row['username'],
                'nickname' => $row['nickname'],
                'avatar'   => $row['avatar'],
                'fav_color'=> $row['fav_color'],
                'is_admin' => (bool) $row['is_admin'],
            ];

            // 導回討論區首頁
            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <title>登入 - 討論區</title>
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
            width: 100%; max-width: 400px;
        }
        h1 { margin-top: 0; color: #333; font-size: 22px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; font-size: 14px; }
        input[type="text"],
        input[type="password"] {
            width: 100%; padding: 9px 10px;
            border: 1px solid #ddd; border-radius: 4px;
            font-family: inherit; font-size: 14px;
            box-sizing: border-box;
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
            margin-bottom: 18px; font-size: 14px;
        }
        .success {
            background: #e8f5e9; color: #2e7d32;
            padding: 10px 14px; border-radius: 4px;
            margin-bottom: 18px; font-size: 14px;
        }
        .footer-link { text-align: center; margin-top: 16px; font-size: 14px; }
        .footer-link a { color: #007bff; }
    </style>
</head>
<body>
<div class="box">
    <h1>🔑 會員登入</h1>

    <!-- 從 register.php 跳轉過來時顯示成功訊息 -->
    <?php if (isset($_GET['registered'])): ?>
        <div class="success">註冊成功！請使用新帳號登入。</div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error"><?= escape($error) ?></div>
    <?php endif; ?>

    <form method="post">

        <div class="form-group">
            <label for="username">帳號</label>
            <input type="text" id="username" name="username"
                   maxlength="50" required
                   value="<?= escape($_POST['username'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="password">密碼</label>
            <input type="password" id="password" name="password" required>
        </div>

        <button type="submit">登入</button>
    </form>

    <div class="footer-link">
        還沒有帳號？<a href="register.php">立即註冊</a>
    </div>
</div>
</body>
</html>
