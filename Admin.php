<?php
// ============================================================
// admin.php — 管理員介面
// 功能: 管理員專用頁面，可新增、修改、刪除會員資料
// 權限: 只有 is_admin = 1 的會員可以進入
// 操作:
//   GET  action=edit&id=N  → 顯示編輯表單
//   POST action=add        → 新增會員
//   POST action=update     → 修改會員資料
//   POST action=delete     → 刪除會員
//   (預設)                 → 顯示會員列表
// ============================================================

header('Content-Type: text/html; charset=utf-8');
session_start();
require 'db_config.php';

// ── 管理員權限檢查 ────────────────────────────────────────────
// 未登入或非管理員一律擋回首頁，不顯示任何管理內容
$member = $_SESSION['member'] ?? null;
if (!$member || !$member['is_admin']) {
    header('Location: index.php');
    exit;
}

$message = ''; // 操作結果訊息（成功或失敗）
$action  = $_REQUEST['action'] ?? '';

// ── POST 操作處理 ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── 新增會員 ─────────────────────────────────────────────
    if ($action === 'add') {
        $username  = trim($_POST['username']  ?? '');
        $password  = trim($_POST['password']  ?? '');
        $nickname  = trim($_POST['nickname']  ?? '');
        $favColor  = trim($_POST['fav_color'] ?? '#ffffff');
        $isAdmin   = isset($_POST['is_admin']) ? 1 : 0;

        if (empty($username) || empty($password) || empty($nickname)) {
            $message = '❌ 帳號、密碼、暱稱為必填。';
        } else {
            // 確認帳號未被使用
            $chk = $pdo->prepare('SELECT id FROM members WHERE username = ?');
            $chk->execute([$username]);
            if ($chk->fetch()) {
                $message = '❌ 帳號已存在。';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare('
                    INSERT INTO members (username, password, nickname, fav_color, is_admin)
                    VALUES (?, ?, ?, ?, ?)
                ');
                $stmt->execute([
                    substr($username, 0, 50),
                    $hash,
                    substr($nickname, 0, 100),
                    $favColor,
                    $isAdmin,
                ]);
                $message = '✅ 會員新增成功。';
            }
        }

    // ── 修改會員 ─────────────────────────────────────────────
    } elseif ($action === 'update') {
        $id       = intval($_POST['id'] ?? 0);
        $nickname = trim($_POST['nickname']  ?? '');
        $favColor = trim($_POST['fav_color'] ?? '#ffffff');
        $isAdmin  = isset($_POST['is_admin']) ? 1 : 0;
        $newPass  = trim($_POST['password']  ?? '');

        if ($id <= 0 || empty($nickname)) {
            $message = '❌ 資料不完整。';
        } else {
            if (!empty($newPass)) {
                // 若有填新密碼，更新密碼欄位
                $hash = password_hash($newPass, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare('
                    UPDATE members
                    SET nickname = ?, fav_color = ?, is_admin = ?, password = ?
                    WHERE id = ?
                ');
                $stmt->execute([substr($nickname, 0, 100), $favColor, $isAdmin, $hash, $id]);
            } else {
                // 密碼欄留空 → 不更新密碼
                $stmt = $pdo->prepare('
                    UPDATE members
                    SET nickname = ?, fav_color = ?, is_admin = ?
                    WHERE id = ?
                ');
                $stmt->execute([substr($nickname, 0, 100), $favColor, $isAdmin, $id]);
            }
            $message = '✅ 會員資料已更新。';
        }

    // ── 刪除會員 ─────────────────────────────────────────────
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);

        // 防止管理員刪除自己，避免沒有管理員的情況
        if ($id === (int) $member['id']) {
            $message = '❌ 不能刪除自己的帳號。';
        } elseif ($id > 0) {
            $stmt = $pdo->prepare('DELETE FROM members WHERE id = ?');
            $stmt->execute([$id]);
            $message = '✅ 會員已刪除。';
        }
    }
}

// ── GET: 顯示編輯表單 ─────────────────────────────────────────
$editRow = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('
        SELECT id, username, nickname, fav_color, is_admin
        FROM members WHERE id = ?
    ');
    $stmt->execute([intval($_GET['id'])]);
    $editRow = $stmt->fetch(); // 若找不到則為 false，表單區不顯示
}

// ── 讀取所有會員列表 ──────────────────────────────────────────
$members = $pdo->query('
    SELECT id, username, nickname, fav_color, avatar, is_admin, created_at
    FROM members
    ORDER BY id ASC
')->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <title>管理員介面 - 討論區</title>
    <style>
        body {
            font-family: system-ui, -apple-system, Arial, sans-serif;
            margin: 0; padding: 20px; background: #f5f5f5;
        }
        .container { max-width: 960px; margin: 0 auto; }
        h1 { color: #333; border-bottom: 3px solid #6f42c1; padding-bottom: 10px; }
        h2 { color: #444; margin-top: 30px; }
        .back-link { color: #007bff; text-decoration: none; font-size: 14px; }
        .back-link:hover { text-decoration: underline; }

        /* 操作結果訊息 */
        .message {
            padding: 10px 14px; border-radius: 4px;
            margin-bottom: 20px; font-size: 14px;
            background: #e8f5e9; color: #2e7d32;
            border: 1px solid #a5d6a7;
        }
        .message.error { background: #ffebee; color: #c62828; border-color: #ef9a9a; }

        /* 表單 */
        .form-box {
            background: #fff; padding: 20px; border-radius: 8px;
            margin-bottom: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-row { display: flex; gap: 16px; flex-wrap: wrap; }
        .form-group { flex: 1; min-width: 160px; margin-bottom: 12px; }
        label { display: block; margin-bottom: 4px; font-weight: bold; font-size: 13px; color: #333; }
        input[type="text"],
        input[type="password"],
        input[type="color"] {
            width: 100%; padding: 8px 10px;
            border: 1px solid #ddd; border-radius: 4px;
            font-size: 13px; box-sizing: border-box;
        }
        input[type="color"] { height: 36px; padding: 2px 4px; cursor: pointer; }
        .checkbox-group { display: flex; align-items: center; gap: 8px; margin-top: 24px; }
        .checkbox-group label { font-weight: normal; margin: 0; }

        button {
            padding: 8px 18px; border: none; border-radius: 4px;
            cursor: pointer; font-size: 14px; color: #fff;
        }
        .btn-primary  { background: #007bff; }
        .btn-primary:hover  { background: #0056b3; }
        .btn-warning  { background: #fd7e14; }
        .btn-warning:hover  { background: #e65c00; }
        .btn-danger   { background: #dc3545; }
        .btn-danger:hover   { background: #b02a37; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #545b62; }

        /* 會員列表 */
        table {
            width: 100%; border-collapse: collapse;
            background: #fff; border-radius: 8px;
            overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        th {
            background: #6f42c1; color: #fff;
            padding: 10px 12px; text-align: left; font-size: 13px;
        }
        td { padding: 10px 12px; border-bottom: 1px solid #eee; font-size: 13px; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f9f9f9; }

        .avatar-sm {
            width: 32px; height: 32px;
            border-radius: 50%; object-fit: cover;
            vertical-align: middle;
        }
        .color-chip {
            display: inline-block; width: 20px; height: 20px;
            border-radius: 3px; border: 1px solid #ccc;
            vertical-align: middle;
        }
        .badge-admin {
            background: #6f42c1; color: #fff;
            padding: 2px 7px; border-radius: 3px; font-size: 11px;
        }
        .badge-user {
            background: #6c757d; color: #fff;
            padding: 2px 7px; border-radius: 3px; font-size: 11px;
        }
        .action-btns { display: flex; gap: 6px; }
    </style>
</head>
<body>
<div class="container">

    <h1>🛠 管理員介面</h1>
    <a href="index.php" class="back-link">← 返回討論區</a>

    <!-- 操作結果訊息 -->
    <?php if ($message): ?>
        <div class="message <?= str_starts_with($message, '❌') ? 'error' : '' ?>" style="margin-top:16px;">
            <?= escape($message) ?>
        </div>
    <?php endif; ?>

    <!-- ── 新增 / 編輯表單 ── -->
    <?php if ($editRow): ?>
        <!-- 編輯模式：表單預填現有資料 -->
        <h2>✏️ 編輯會員：<?= escape($editRow['username']) ?></h2>
        <div class="form-box">
            <form method="post">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= $editRow['id'] ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label>暱稱</label>
                        <input type="text" name="nickname" maxlength="100" required
                               value="<?= escape($editRow['nickname']) ?>">
                    </div>
                    <div class="form-group">
                        <label>新密碼（留空不更改）</label>
                        <input type="password" name="password" minlength="6">
                    </div>
                    <div class="form-group">
                        <label>喜歡顏色</label>
                        <input type="color" name="fav_color"
                               value="<?= escape($editRow['fav_color']) ?>">
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" name="is_admin" id="ea"
                               <?= $editRow['is_admin'] ? 'checked' : '' ?>>
                        <label for="ea">管理員</label>
                    </div>
                </div>
                <button type="submit" class="btn-warning">儲存變更</button>
                <a href="admin.php"><button type="button" class="btn-secondary">取消</button></a>
            </form>
        </div>
    <?php else: ?>
        <!-- 新增模式：空白表單 -->
        <h2>➕ 新增會員</h2>
        <div class="form-box">
            <form method="post">
                <input type="hidden" name="action" value="add">
                <div class="form-row">
                    <div class="form-group">
                        <label>帳號</label>
                        <input type="text" name="username" maxlength="50" required>
                    </div>
                    <div class="form-group">
                        <label>密碼</label>
                        <input type="password" name="password" minlength="6" required>
                    </div>
                    <div class="form-group">
                        <label>暱稱</label>
                        <input type="text" name="nickname" maxlength="100" required>
                    </div>
                    <div class="form-group">
                        <label>喜歡顏色</label>
                        <input type="color" name="fav_color" value="#ffffff">
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" name="is_admin" id="aa">
                        <label for="aa">管理員</label>
                    </div>
                </div>
                <button type="submit" class="btn-primary">新增會員</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- ── 會員列表 ── -->
    <h2>👥 所有會員（<?= count($members) ?> 人）</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>大頭貼</th>
                <th>帳號</th>
                <th>暱稱</th>
                <th>顏色</th>
                <th>身份</th>
                <th>註冊時間</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($members as $row): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td>
                    <!-- 大頭貼縮圖：若無則顯示預設圖 -->
                    <img class="avatar-sm"
                         src="<?= escape($row['avatar'] ?? 'uploads/default_avatar.png') ?>"
                         alt="avatar">
                </td>
                <td><?= escape($row['username']) ?></td>
                <td><?= escape($row['nickname']) ?></td>
                <td>
                    <!-- 顏色色塊 + HEX 值 -->
                    <span class="color-chip"
                          style="background:<?= escape($row['fav_color']) ?>;"></span>
                    <?= escape($row['fav_color']) ?>
                </td>
                <td>
                    <?php if ($row['is_admin']): ?>
                        <span class="badge-admin">管理員</span>
                    <?php else: ?>
                        <span class="badge-user">一般</span>
                    <?php endif; ?>
                </td>
                <td><?= escape($row['created_at']) ?></td>
                <td>
                    <div class="action-btns">
                        <!-- 編輯按鈕：導向同頁帶 action=edit&id=N -->
                        <a href="admin.php?action=edit&id=<?= $row['id'] ?>">
                            <button type="button" class="btn-warning">編輯</button>
                        </a>

                        <!-- 刪除按鈕：POST 表單，防止 CSRF 誤觸 -->
                        <form method="post"
                              onsubmit="return confirm('確定刪除會員「<?= escape($row['username']) ?>」？')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <button type="submit" class="btn-danger"
                                <?= ($row['id'] === (int)$member['id']) ? 'disabled title="不能刪除自己"' : '' ?>>
                                刪除
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

</div>
</body>
</html>