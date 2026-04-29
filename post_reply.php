<?php
// ============================================================
// post_reply.php — 發表回應
// 功能: 接收表單 POST，將新回應寫入 replies 資料表
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
    // 未登入者不應能回應，導回登入頁
    header('Location: login.php');
    exit;
}

// 從 session 取得 member_id，不從 POST 取得，防止偽造身份
$memberId = (int) $member['id'];

// ── 讀取並清理表單輸入 ───────────────────────────────────────
// author 欄位已從表單移除，這裡不再讀取 $_POST['author']
$newsId  = isset($_POST['news_id']) ? intval($_POST['news_id']) : 0;
$content = isset($_POST['content']) ? trim($_POST['content'])   : '';

// ── 驗證討論 ID ──────────────────────────────────────────────
if ($newsId <= 0) {
    die('無效的討論 ID。<br><a href="index.php">返回</a>');
}

// ── 驗證必填欄位 ─────────────────────────────────────────────
// 移除對 $author 的檢查，因為作者已改由 session 提供
if (empty($content)) {
    die('回應內容不能為空。<br><a href="show_news.php?id=' . $newsId . '">返回</a>');
}

// ── 確認討論主題存在 ─────────────────────────────────────────
// 防止回應一個已被刪除或不存在的討論
try {
    $stmt = $pdo->prepare('SELECT id FROM news WHERE id = ?');
    $stmt->execute([$newsId]);
    if (!$stmt->fetch()) {
        die('找不到此討論。<br><a href="index.php">返回首頁</a>');
    }
} catch (PDOException $e) {
    die('驗證失敗: ' . $e->getMessage());
}

// ── 限制輸入長度，對應資料表欄位設計 ────────────────────────
$content = substr($content, 0, 10000); // 對應 replies.content TEXT

// ── 寫入資料庫 ───────────────────────────────────────────────
// 改為寫入 member_id，不再寫入 author 文字
try {
    $stmt = $pdo->prepare('
        INSERT INTO replies (news_id, content, member_id)
        VALUES (?, ?, ?)
    ');
    $stmt->execute([$newsId, $content, $memberId]);

    // 回應成功，導回該討論頁
    header('Location: show_news.php?id=' . $newsId);
    exit;
} catch (PDOException $e) {
    die('發表回應失敗: ' . $e->getMessage() . '<br><a href="show_news.php?id=' . $newsId . '">返回</a>');
}