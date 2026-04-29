<?php
// ============================================================
// post.php — 發表新討論
// 功能: 接收表單 POST，將新討論寫入 news 資料表
// 修改重點:
//   1. 加入 session_start()，從 session 取得登入會員的 member_id
//      取代原本從 $_POST['author'] 讀取純文字作者名稱
//   2. 未登入者直接擋回，無法繞過前端表單直接 POST
//   3. SQL 改為寫入 member_id，不再寫入 author 文字欄位
// ============================================================

header('Content-Type: text/html; charset=utf-8');
session_start();         // 啟動 session，才能讀取登入狀態
require 'db_config.php';

// ── 只允許 POST 請求 ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request method.');
}

// ── 登入驗證 ─────────────────────────────────────────────────
// 從 session 取得會員資料；login.php 登入成功後會寫入 $_SESSION['member']
// 若尚未登入則 $member 為 null，直接擋回
$member = $_SESSION['member'] ?? null;

if (!$member) {
    // 未登入者不應能發文，導回登入頁
    header('Location: login.php');
    exit;
}

// 從 session 取得 member_id，不從 POST 取得，防止偽造身份
$memberId = (int) $member['id'];

// ── 讀取並清理表單輸入 ───────────────────────────────────────
// author 欄位已從表單移除，這裡不再讀取 $_POST['author']
$title   = isset($_POST['title'])   ? trim($_POST['title'])   : '';
$content = isset($_POST['content']) ? trim($_POST['content']) : '';

// ── 驗證必填欄位 ─────────────────────────────────────────────
if (empty($title) || empty($content)) {
    die('所有欄位都必須填寫。<br><a href="index.php">返回</a>');
}

// ── 限制輸入長度，對應資料表欄位設計 ────────────────────────
$title   = substr($title,   0, 200);   // 對應 news.title VARCHAR(200)
$content = substr($content, 0, 10000); // 對應 news.content TEXT

// ── 寫入資料庫 ───────────────────────────────────────────────
// 改為寫入 member_id，不再寫入 author 文字
try {
    $stmt = $pdo->prepare('
        INSERT INTO news (title, content, member_id)
        VALUES (?, ?, ?)
    ');
    $stmt->execute([$title, $content, $memberId]);

    // 發表成功，導回首頁
    header('Location: index.php');
    exit;
} catch (PDOException $e) {
    die('發表討論失敗: ' . $e->getMessage() . '<br><a href="index.php">返回</a>');
}