<?php
// ============================================================
// logout.php — 會員登出
// 功能: 清除 session 資料，讓使用者回到未登入狀態
// 流程:
//   1. session_start()  — 必須先啟動才能操作 session
//   2. $_SESSION = []   — 清空所有 session 變數
//   3. session_destroy() — 刪除伺服器端的 session 資料
//   4. 導回首頁
// ============================================================

session_start();

// 清空所有 session 變數（包含 $_SESSION['member']）
$_SESSION = [];

// 刪除伺服器端 session 檔案，確保資料完全清除
// 僅清空 $_SESSION 陣列不足夠，session_destroy() 才真正移除
session_destroy();

// 登出後導回首頁
header('Location: index.php');
exit;
