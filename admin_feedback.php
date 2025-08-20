<?php
session_start();
include "db.php";

// Chỉ admin (user_id = 1) mới được truy cập
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    die("Bạn không có quyền truy cập trang này.");
}

// Thiết lập đường dẫn upload
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', '/uploads/');

// Lấy thông tin admin
$admin_id      = $_SESSION['user_id'];
$sql_admin     = "SELECT username, avatar FROM users WHERE id = $1";
$params_admin  = [$admin_id];
$result_admin  = pg_query_params($conn, $sql_admin, $params_admin);
$admin         = pg_fetch_assoc($result_admin);

// Xác định file avatar thực tế hoặc default
$avatarName = !empty($admin['avatar'])
    ? basename($admin['avatar'])
    : 'avt_ad.png';

$avatarPath = UPLOAD_DIR . $avatarName;
if (! file_exists($avatarPath)) {
    // nếu file upload bởi user bị mất, quay về mặc định
    $avatarName = 'avt_ad.png';
}

$avatarUrl = UPLOAD_URL . $avatarName;

// Lấy phản hồi chưa xử lý
$pending_sql      = "
    SELECT f.id, u.username, f.message
    FROM feedbacks f
    JOIN users u ON f.user_id = u.id
    WHERE f.status = 'pending'
    ORDER BY f.created_at DESC
";
$pending_feedbacks = pg_query($conn, $pending_sql);

// Lọc phản hồi theo trạng thái
$status_filter = $_GET['status'] ?? '';
$feedback_sql  = "
    SELECT f.*, u.username
    FROM feedbacks f
    JOIN users u ON f.user_id = u.id
";
$params_filter = [];

if (in_array($status_filter, ['pending','processed','ignored'], true)) {
    $feedback_sql   .= " WHERE f.status = $1";
    $params_filter  = [$status_filter];
}
$feedback_sql .= " ORDER BY f.created_at DESC";
$feedbacks    = $params_filter
    ? pg_query_params($conn, $feedback_sql, $params_filter)
    : pg_query($conn, $feedback_sql);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>📬 Phản hồi người dùng</title>
    <style>
        :root {
          --color-primary: #6f42c1;
          --color-bg: #f1f1f1;
          --color-card: #fff;
          --color-border: #ccc;
          --color-text: #333;
          --color-success: #28a745;
          --color-warning: #ffc107;
          --color-muted: #888;
        }
        
        body {
          margin: 0;
          font-family: Arial, sans-serif;
          background: var(--color-bg);
        }
        
        .dashboard-wrapper {
          display: flex;
          min-height: 100vh;
        }
        
        .sidebar {
          width: 240px;
          background: var(--color-card);
          padding: 20px;
          box-shadow: 2px 0 5px rgba(0,0,0,0.05);
        }
        
        .sidebar h3 {
          margin-top: 0;
          font-size: 18px;
          color: var(--color-primary);
        }
        
        .sidebar ul {
          list-style: none;
          padding: 0;
        }
        
        .sidebar li {
          margin-bottom: 10px;
        }
        
        .sidebar a {
          text-decoration: none;
          color: var(--color-text);
        }
        
        .content {
          flex: 1;
          padding: 30px;
          background: var(--color-bg);
        }
        
        .header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          background: var(--color-primary);
          color: white;
          padding: 15px 30px;
        }
        
        .header h2 {
          margin: 0;
        }
        
        .header .user {
          display: flex;
          align-items: center;
        }
        
        .header .user span {
          font-weight: bold;
        }
        
        .header .user img {
          width: 40px;
          height: 40px;
          border-radius: 50%;
          margin-left: 10px;
          object-fit: cover;
          border: 2px solid #fff;
        }
        
        table {
          width: 100%;
          border-collapse: collapse;
          background: var(--color-card);
          box-shadow: 0 0 5px rgba(0,0,0,0.05);
        }
        
        th, td {
          padding: 12px;
          border: 1px solid var(--color-border);
          text-align: left;
        }
        
        th {
          background: #eee;
        }
        
        .status-actions button {
          padding: 6px 10px;
          font-size: 13px;
          margin-right: 5px;
          border: none;
          border-radius: 4px;
          cursor: pointer;
        }
        
        .status-actions button[value="processed"] {
          background: var(--color-warning);
        }
        
        .status-actions button[value="ignored"] {
          background: #ddd;
        }
        
        .status-actions span {
          font-weight: bold;
        }
        
        #overlay {
          position: fixed;
          top: 0; left: 0;
          width: 100vw; height: 100vh;
          background: rgba(0,0,0,0.8);
          display: none;
          justify-content: center;
          align-items: center;
          z-index: 9999;
        }
        
        #overlay img {
          max-width: 90%;
          max-height: 90%;
          border: 4px solid white;
          box-shadow: 0 0 20px rgba(0,0,0,0.5);
          cursor: zoom-out;
        }
    </style>
</head>
<body>
  <div class="header">
    <h2>📬 Phản hồi người dùng</h2>
    <div class="user">
      <span><?= htmlspecialchars($admin['username']) ?></span>
      <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="Avatar">
    </div>
  </div>

  <div class="dashboard-wrapper">
    <div class="sidebar">
      <h3>Menu quản trị</h3>
      <ul>
        <li><a href="logout.php">🚪 Đăng xuất</a></li>
      </ul>

      <?php if (pg_num_rows($pending_feedbacks) > 0): ?>
        <h4>📌 Phản hồi chưa xử lý</h4>
        <ul>
          <?php while ($row = pg_fetch_assoc($pending_feedbacks)): ?>
            <li>
              <strong><?= htmlspecialchars($row['username']) ?></strong>: <?= htmlspecialchars(mb_strimwidth($row['message'], 0, 40, '...')) ?>
            </li>
          <?php endwhile; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div class="content">
      <h3>Danh sách phản hồi</h3>
      <form method="get">
        <label for="status_filter">Lọc theo trạng thái:</label>
        <select name="status" id="status_filter" onchange="this.form.submit()">
          <option value="">Tất cả</option>
          <option value="pending"   <?= $status_filter==='pending'   ? 'selected':'' ?>>Chưa xử lý</option>
          <option value="processed" <?= $status_filter==='processed' ? 'selected':'' ?>>Đã xử lý</option>
          <option value="ignored"   <?= $status_filter==='ignored'   ? 'selected':'' ?>>Không xử lý</option>
        </select>
      </form>

      <?php if (pg_num_rows($feedbacks) === 0): ?>
        <p>Chưa có phản hồi nào từ người dùng.</p>
      <?php else: ?>
        <table>
          <tr>
            <th>Người gửi</th>
            <th>Nội dung</th>
            <th>Thời gian</th>
            <th>Trạng thái</th>
          </tr>
          <?php while ($row = pg_fetch_assoc($feedbacks)): ?>
            <tr>
              <td><?= htmlspecialchars($row['username']) ?></td>
              <td>
                <?= nl2br(htmlspecialchars($row['message'])) ?>
                <?php
                $imagePath = UPLOAD_DIR . $row['image'];
                if (!empty($row['image']) && file_exists($imagePath)):
                ?>
                  <div style="margin-top:8px;">
                    <img
                      src="<?= UPLOAD_URL . htmlspecialchars($row['image']) ?>"
                      alt="Feedback image"
                      class="zoomable"
                      style="max-width:200px;max-height:150px;border:1px solid #ccc;cursor:zoom-in;"
                    >
                  </div>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($row['created_at']) ?></td>
              <td class="status-actions">
                <?php if ($row['status']==='processed'): ?>
                  <span style="color:green;">✔ Đã xử lý</span>
                <?php elseif ($row['status']==='ignored'): ?>
                  <span style="color:gray;">🚫 Không xử lý</span>
                <?php else: ?>
                  <form method="post" action="update_feedback_status.php">
                      <input type="hidden" name="feedback_id" value="<?= $row['id'] ?>">
                      <textarea name="admin_reply" placeholder="Nhập phản hồi gửi đến người dùng..." rows="2" style="width:100%;margin-bottom:8px;"></textarea>
                      <button name="action" value="processed">✔ Đã xử lý</button>
                      <button name="action" value="ignored">🚫 Không xử lý</button>
                    </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <div id="overlay" onclick="hideOverlay()">
    <img id="overlay-img" src="" alt="Zoomed Image">
  </div>

  <script>
    document.querySelectorAll('.zoomable').forEach(img => {
      img.addEventListener('click', e => {
        e.stopPropagation();
        document.getElementById('overlay').style.display = 'flex';
        document.getElementById('overlay-img').src = img.src;
      });
    });
    function hideOverlay() {
      document.getElementById('overlay').style.display = 'none';
    }
    document.addEventListener('keydown', e => {
      if (e.key==='Escape') hideOverlay();
    });
  </script>
</body>
</html>
