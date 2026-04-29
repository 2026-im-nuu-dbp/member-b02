-- ============================================================
-- 討論區資料庫結構
-- Database: test_db
-- 說明: 包含討論主題、回應、以及會員系統三張資料表
-- 重要: members 必須最先建立，因為 news 與 replies 的
--       FOREIGN KEY 都參照 members.id
-- ============================================================


-- ============================================================
-- Table: members  ← 必須第一個建立
-- 說明: 儲存會員帳號資料，為本次作業新增的資料表
-- 欄位設計依照作業需求:
--   帳號 / 密碼 / 暱稱 / 喜歡顏色 / 大頭貼
--   另加 is_admin 欄位用於管理員介面權限控制
-- ============================================================
CREATE TABLE members (
    id           INT AUTO_INCREMENT PRIMARY KEY,      -- 會員唯一識別碼
    username     VARCHAR(50)  NOT NULL UNIQUE,         -- 登入帳號，不可重複
    password     VARCHAR(255) NOT NULL,                -- 密碼 (儲存 bcrypt hash，非明文)
    nickname     VARCHAR(100) NOT NULL,                -- 顯示在討論區的暱稱
    fav_color    VARCHAR(7)   NOT NULL DEFAULT '#ffffff',
    -- 喜歡的顏色，以 HEX 格式儲存，例如 #ff5733
    -- 用於回應留言欄位的背景色 (選項功能)
    avatar       VARCHAR(255) DEFAULT NULL,            -- 大頭貼圖片的檔案路徑，例如 uploads/abc.jpg
    is_admin     TINYINT(1)   NOT NULL DEFAULT 0,      -- 0 = 一般會員, 1 = 管理員
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,  -- 註冊時間，自動填入

    INDEX idx_username (username)                      -- 加速登入時以帳號查詢
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- Table: news  ← 第二個建立，參照 members
-- 說明: 儲存討論主題
-- 修改: 將 author 欄位改為參照 members.id (member_id)
--       以取代原本純文字的作者名稱，連結到會員系統
-- ============================================================
CREATE TABLE news (
    id         INT AUTO_INCREMENT PRIMARY KEY,   -- 討論主題的唯一識別碼
    title      VARCHAR(200) NOT NULL,            -- 討論標題，最長200字元
    content    TEXT NOT NULL,                    -- 討論內文
    member_id  INT NOT NULL,                     -- 發文者，對應 members.id
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- 發文時間，自動填入

    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    -- 若會員被刪除，其發表的討論也一併刪除

    INDEX idx_created_at (created_at)            -- 加速依時間排序的查詢
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- Table: replies  ← 第三個建立，參照 members 與 news
-- 說明: 儲存每則討論的回應留言
-- 修改: 將 author 欄位改為參照 members.id (member_id)
--       確保每則回應都與登入會員綁定
-- ============================================================
CREATE TABLE replies (
    id         INT AUTO_INCREMENT PRIMARY KEY,   -- 回應的唯一識別碼
    news_id    INT NOT NULL,                     -- 對應的討論主題 id
    content    TEXT NOT NULL,                    -- 回應內文
    member_id  INT NOT NULL,                     -- 回應者，對應 members.id
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- 回應時間，自動填入

    FOREIGN KEY (news_id)   REFERENCES news(id)    ON DELETE CASCADE,
    -- 若討論主題被刪除，底下所有回應也一併刪除

    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    -- 若會員被刪除，其回應也一併刪除

    INDEX idx_news_id (news_id)                  -- 加速查詢特定討論的所有回應
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- 預設管理員帳號
-- 帳號: admin
-- 密碼: admin1234
-- 用途: 方便老師或開發者第一次登入管理介面
-- 注意: 此 hash 由 password_hash('admin1234', PASSWORD_BCRYPT) 產生
-- ============================================================
INSERT INTO members (username, password, nickname, fav_color, is_admin)
VALUES (
    'admin',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    '管理員',
    '#343a40',
    1
);