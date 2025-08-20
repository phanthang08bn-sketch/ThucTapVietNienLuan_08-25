<?php
session_start();
include "db.php";
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!function_exists('bcadd')) {
    function bcadd($left_operand, $right_operand, $scale = 2) {
        // Fallback dùng toán học thường (không hoàn toàn chính xác với số lớn)
        return number_format($left_operand + $right_operand, $scale, '.', '');
    }
}
if (!empty($_SESSION['restored'])) {
  echo "<div class='popup-feedback'>" . $_SESSION['restored'] . "</div>";
  unset($_SESSION['restored']);
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['hide_feedback'])) {
    $feedback_id = $_POST['feedback_id'] ?? 0;
    pg_query_params($conn, "UPDATE feedbacks SET is_read = TRUE WHERE id = $1 AND user_id = $2", [$feedback_id, $_SESSION['user_id']]);
}

// 1. Chuyển admin nếu user_id = 1
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == 1) {
    header("Location: admin_feedback.php");
    exit();
}

// 2. Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
$sql = " SELECT t.*, COALESCE(a.name,'[Không xác định]') AS account_name 
         FROM transactions t 
         LEFT JOIN accounts a ON t.account_id = a.id 
         WHERE t.user_id = $1 
         AND (t.type != 3 OR (t.deleted_at IS NOT NULL AND t.deleted_at >= NOW() - INTERVAL '30 seconds'))";

$sql .= " AND (t.is_hidden IS FALSE OR t.is_hidden IS NULL)";
$sql_feedback = "SELECT id, message, status FROM feedbacks WHERE user_id = $1 AND status != 'pending' ORDER BY created_at DESC LIMIT 1";
$res_feedback = pg_query_params($conn, $sql_feedback, [$user_id]);
$feedback_popup = pg_fetch_assoc($res_feedback);

function getAdminReplies($feedback_id) {
    global $conn;
    $query = "SELECT content, created_at FROM admin_feedbacks WHERE feedback_id = $1 ORDER BY created_at ASC";
    $result = pg_query_params($conn, $query, array($feedback_id));
    $replies = [];
    while ($row = pg_fetch_assoc($result)) {
        $replies[] = $row;
    }
    return $replies;
}

$admin_replies = [];
if (!empty($feedback_popup)) {
    $admin_replies = getAdminReplies($feedback_popup['id']);
}

$sql_user = "SELECT username, fullname, avatar, role FROM users WHERE id = $1";
$result = pg_query_params($conn, $sql_user, [$_SESSION['user_id']]);
$user = pg_fetch_assoc($result);
$avatarFile = $user['avatar'] ?? '';
$avatarPath = '/uploads/' . (file_exists(__DIR__ . '/uploads/' . $avatarFile) && !empty($avatarFile) ? $avatarFile : 'avt_mem.png');
$filter_account     = isset($_GET['account_id']) ? intval($_GET['account_id']) : 0;
$filter_type        = isset($_GET['type'])       ? $_GET['type']           : 'all';
$filter_description = isset($_GET['description'])? trim($_GET['description']) : '';
$from_date          = $_GET['from_date'] ?? '';
$to_date            = $_GET['to_date']   ?? '';
    echo "</div>"; // đóng khối phản hồi

// 4. Lấy danh sách tài khoản và tính tổng số dư
$accounts = [];
$totalAccountBalance = 0.0;

$account_q = pg_query_params(
    $conn,
    "SELECT id, name, balance 
     FROM accounts 
     WHERE user_id = $1",
    [$user_id]
);
while ($acc = pg_fetch_assoc($account_q)) {
    $accounts[] = $acc;
    // Ép float để cộng đúng
    $totalAccountBalance = bcadd($totalAccountBalance, $acc['balance'], 2);
}

// 5. Lấy danh sách giao dịch theo filter
$sql = "
    SELECT t.*, COALESCE(a.name,'[Không xác định]') AS account_name
    FROM transactions t
    LEFT JOIN accounts a ON t.account_id = a.id
    WHERE t.user_id = $1
";
$params = [$user_id];
$idx    = 2;

if ($filter_account > 0) {
    $sql    .= " AND t.account_id = \${$idx}";
    $params[] = $filter_account;
    $idx++;
}
if ($filter_type !== 'all') {
    $sql    .= " AND t.type = \${$idx}";
    $params[] = intval($filter_type);
    $idx++;
}
if ($filter_description !== '') {
  if ($filter_description === 'Tạo khoản tiền mới') {
    $sql .= " AND t.description ILIKE 'Tạo tài khoản mới:%'";
  } else {
    $sql .= " AND t.description ILIKE \${$idx}";
    $params[] = "%{$filter_description}%";
    $idx++;
  }
}
if ($from_date) {
    $sql    .= " AND DATE(t.date) >= \${$idx}";
    $params[] = $from_date;
    $idx++;
}
if ($to_date) {
    $sql    .= " AND DATE(t.date) <= \${$idx}";
    $params[] = $to_date;
    $idx++;
}

$sql .= " ORDER BY t.date DESC, t.id DESC";
$resTrans = pg_query_params($conn, $sql, $params);
function isExpiredDeletedTransaction($t) {
    return $t['type'] == 3 && isset($t['deleted_at']) && strtotime($t['deleted_at']) < time() - 30;
}


$transactions = [];
while ($row = pg_fetch_assoc($resTrans)) {
    if (isExpiredDeletedTransaction($row)) continue;
    $transactions[] = $row;
}

// 6. Tính tổng thu/chi
$totalThuAll = 0;
$totalChiAll = 0;
foreach ($transactions as $t) {
    if ($t['type'] == 1) {
        $totalThuAll = bcadd($totalThuAll, $t['amount'], 2);
    }
    if ($t['type'] == 2) {
        $totalChiAll = bcadd($totalChiAll, $t['amount'], 2);
    }
}
// 7. Nhóm giao dịch theo ngày
$grouped = [];
foreach ($transactions as $t) {
    $dateKey = date('d/m/Y', strtotime($t['date']));
    $grouped[$dateKey][] = $t;
}

// Nhãn cho type
$typeLabels = [
  1 => 'Thu',
  2 => 'Chi',
  0 => 'Cập nhật',
  3 => 'Đã xoá'
];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard</title>

  <style>
    /* 1. Biến toàn cục */
    :root {
      --sidebar-width: 280px;
      --color-primary: #1e88e5;
      --color-secondary: #66bb6a;
      --color-danger: #e53935;
      --color-bg: #f9fafb;
      --color-card: #ffffff;
      --color-text: #2e3d49;
      --color-muted: #64748b;
      --border-radius: 8px;
      --spacing: 16px;
      --transition-speed: 0.3s;
    }

    /* 2. Reset + Global */
    *, *::before, *::after {
      box-sizing: border-box;
      margin: 0; padding: 0;
    }
    body {
      font-family: 'Segoe UI', Tahoma, sans-serif;
      background: var(--color-bg);
      color: var(--color-text);
      line-height: 1.6;
    }
    a { text-decoration: none; color: inherit; }
    .brand {
      font-size: 1.75rem;      
      font-weight: 700;        
      letter-spacing: 1px;     
      text-transform: uppercase;  
      color: #fff;         
    }
    /* 3. Layout chính */
    .dashboard-wrapper {
      display: grid;
      grid-template-columns: var(--sidebar-width) 1fr;
      width: 100%;         
      max-width: none;    
      margin: 0;       
      gap: var(--spacing);
      padding: var(--spacing);
      border-radius: var(--border-radius);
    }

    .dashboard-wrapper .sidebar {
      position: sticky;
      top: calc(60px + var(--spacing));
      height: calc(100vh - 60px - 2 * var(--spacing));
      overflow-y: auto;
      background: var(--color-card);
      padding: var(--spacing);
      border-radius: var(--border-radius);
      transition: transform var(--transition-speed);
    }

    .dashboard-wrapper .content {
      background: var(--color-card);
      padding: var(--spacing);
      border-radius: var(--border-radius);
      min-height: calc(100vh - 60px - 2 * var(--spacing));
    }

    /* 4. Header */
    .header {
      position: sticky;
      top: 0;
      z-index: 1000;
      background: var(--color-primary);
      color: white;
      padding: 12px 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .header .user {
      display: flex;
      align-items: center;
    }  
    .header .user a {
      display: flex;
      align-items: center;
      text-decoration: none;
      color: white;
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
      border: 2px solid white;
    }  
    #sidebar-toggle { /* giữ nguyên */ }
    .brand { /* giữ nguyên */ }
    .profile-link { /* giữ nguyên */ }

    /* 5. Module (Filter panel, Table, Sidebar items…) */
    .sidebar h3 {
      font-size: 0.9rem;
      color: var(--color-muted);
      margin-bottom: 8px;
    }
    .sidebar a {
      display: block;
      margin-bottom: 12px;
      color: var(--color-text);
      text-decoration: none;
      font-weight: 500;
    }
    .sidebar a:hover {
      color: var(--color-primary);
    }
    .account-card {
      display: block;
      background: var(--color-bg);
      padding: 12px;
      border-radius: var(--border-radius);
      margin-bottom: 8px;
      border: 1px solid #e2e8f0;
      transition: background var(--transition-speed);
    }
    .account-card:hover {
      background: #ebf4ff;
    }
    .account-name {
      font-weight: 600;
      margin-bottom: 4px;
    }
    .account-balance {
      font-size: 0.85rem;
      color: var(--color-text);
    }
    .add-account {
      display: inline-block;
      margin: var(--spacing) 0;
      color: var(--color-primary);
      font-weight: 500;
    }
    .account-total {
      margin-top: auto;
      padding: 12px;
      background: #ecfdf3;
      border: 1px solid #bbf7d0;
      border-radius: var(--border-radius);
      font-weight: 600;
      font-size: 0.9rem;
      color: var(--color-text);
    }
    .sidebar hr {
      margin: var(--spacing) 0;
      border: none;
      height: 1px;
      background: #e2e8f0;
    }
    .popup-feedback {
      position: fixed;
      bottom: 20px;
      right: 20px;
      background-color: #fff3cd;
      border: 1px solid #ffeeba;
      padding: 12px 16px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.2);
      z-index: 9999;
      max-width: 300px;
      font-size: 14px;
    }
    /* ——— Module: filter form ——— */
    .filter-panel {
      display: grid;
      gap: var(--spacing);
      background: var(--color-card);
      padding: var(--spacing);
      border-radius: var(--border-radius);
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      margin-bottom: var(--spacing);
    }
    .filter-panel .form-group {
      display: flex;
      flex-direction: column;
    }
    .filter-panel label {
      font-size: 0.85rem;
      color: var(--color-muted);
      margin-bottom: 6px;
    }
    .filter-panel input,
    .filter-panel select {
      padding: 8px;
      border: 1px solid #cbd5e1;
      border-radius: 4px;
      font-size: 0.95rem;
    }
    .filter-summary-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 16px;
      margin-top: 12px;
      padding: 0 8px;
    }
    .stats-inline {
      display: flex;
      flex-direction: row;
      gap: 24px;
      min-width: 320px;
      justify-content: flex-start;
      align-items: center;
    }
    .stats-inline span {
      display: inline-block;
      min-width: 180px;
      white-space: nowrap;
    }
    .filter-buttons {
      display: flex;
      gap: 12px;
    }
      .filter-buttons button,
    .filter-buttons .reset {
      padding: 10px 16px;
      border-radius: var(--border-radius);
      font-size: 0.95rem;
      cursor: pointer;
    }
    .filter-buttons button {
      background: var(--color-primary);
      color: #fff;
      border: none;
      padding: 10px 16px;
      border-radius: var(--border-radius);
      cursor: pointer;
      transition: background var(--transition-speed);
    }
    .filter-buttons button:hover {
      background: #1565c0;
    }
    .filter-buttons .reset {
      background: #f1f5f9;
      color: var(--color-text);
      padding: 10px 16px;
      border: 1px solid #cbd5e1;
      border-radius: var(--border-radius);
    }
    
    
    /* ——— Module: bảng giao dịch ——— */
    .table-wrapper {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background: var(--color-card);
      border-radius: var(--border-radius);
      overflow: hidden;
      box-shadow: 0 1px 2px rgba(0,0,0,0.05);
      min-width: 700px;
    }
    th, td {
      padding: 12px 8px;
      text-align: left;
    }
    th {
      background: #f1f5f9;
      font-weight: 600;
    }
    tr:nth-child(even) {
      background: #f8fafc;
    }
    tr:hover {
      background: #eef2f7;
    }
    .amount-income {
      color: var(--color-secondary);
      font-weight: 600;
    }
    .amount-expense {
      color: var(--color-danger);
      font-weight: 600;
    }
    
    
    /* ——— Module: nhóm ngày ——— */
    .date-group {
      margin-top: 24px;
    }
    .date-heading {
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #e2e8f0;
      padding: 8px 12px;
      border-left: 4px solid var(--color-primary);
      font-weight: 600;
      border-radius: var(--border-radius) 0 0 var(--border-radius);
    }
    .date-label {
      flex: 1;
    }
    .date-summary {
      display: flex;
      justify-content: center;
      gap: 24px;
      margin: 8px 0;
    }
    .date-summary span {
      display: inline-block;
      width: 270px;         /* bề ngang cố định như bạn yêu cầu */
      text-align: center;
      white-space: nowrap;
    }
    .filter-row {
      display: grid;
      grid-template-columns: 1fr;
      gap: 16px;
    }
    
    .filters {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 16px;
      width: 100%;
    }
    
    .form-group {
      display: flex;
      flex-direction: column;
    }
    
    .stats-inline {
      display: flex;
      flex-direction: row;
      gap: 5px;
      min-width: 180px;
      justify-content: center;
    }
    
    .filter-buttons {
      display: flex;
      flex-direction: column;
      gap: 5px;
    }
    .profile-link img {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
      margin-left: 10px;
      border: 2px solid white;
    }
    .avatar-img {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid white;
    }
    .add-transaction-btn {
      background-color: var(--color-primary);
      color: white;
      padding: 8px 16px;
      border-radius: var(--border-radius);
      text-decoration: none;
      font-weight: bold;
      margin-left: auto;
    }
    .deleted-transaction td {
      text-decoration: line-through;
      opacity: 0.6;
    }
    /* 6. Responsive */
    @media (max-width: 992px) {
      .dashboard-wrapper .sidebar.open {
        transform: translateX(0);
      }
        .main.full {
        margin-left: calc(-1 * var(--sidebar-width));
        width: calc(100% + var(--sidebar-width));
      }
    }

    @media (max-width: 800px) {
      .dashboard-wrapper {
        display: block;
        padding: 0;
      }
      .dashboard-wrapper .sidebar {
        position: relative;
        top: 0;
        height: auto;
        margin-bottom: var(--spacing);
        transform: translateX(0);
      }
    }
    @media (max-width: 768px) {
      .filter-summary-row {
        flex-direction: column;
        align-items: flex-start;
      }
      .stats-inline {
        flex-direction: column;
        align-items: flex-start;
      }
    
      .filter-buttons {
        flex-direction: column;
        gap: 8px;
        width: 100%;
      }
    }
      .table-wrapper table {
          width: 100%;
          border-collapse: collapse;
          table-layout: fixed;
        }
        
        .table-wrapper th,
        .table-wrapper td {
          padding: 8px 12px;
          text-align: left;
          vertical-align: middle;
          word-break: break-word;
        }
        
        .table-wrapper th {
          background-color: #f5f5f5;
          font-weight: bold;
        }
        .table-wrapper th:nth-child(1), /* Giờ */
        .table-wrapper td:nth-child(1) {
          width: 95px;
        }
        
        .table-wrapper th:nth-child(2), /* Loại */
        .table-wrapper td:nth-child(2) {
          width: 90px;
        }
        .table-wrapper th:nth-child(3), /* Mô tả */
        .table-wrapper td:nth-child(3) {
          width: 200px;
        }
        .table-wrapper th:nth-child(4), /* Số tiền */
        .table-wrapper td:nth-child(4) {
          width: 180px;
        }
        .table-wrapper th:nth-child(5), /* Số dư còn lại */
        .table-wrapper td:nth-child(5) {
          width: 180px;
        }
        .table-wrapper th:nth-child(6), /* Khoản tiền  */
        .table-wrapper td:nth-child(6) {
          width: 145px;
        }
        .table-wrapper th:nth-child(7), /* Thao tác */
        .table-wrapper td:nth-child(7) {
          width: 200px;
        }
    @media (max-width: 600px) {
      .filter-panel {
        grid-template-columns: 1fr;
      }
      .filters {
        grid-template-columns: 1fr;
      }
      .filters .form-group {
        width: 100%;
      }
      table th:nth-child(6),
      table td:nth-child(6) {
        display: none;
      }
      .stats-inline,
      .filter-buttons {
        flex-direction: column;
        align-items: flex-start;
      }
        .responsive-filters {
            grid-template-columns: 1fr;
          }

      .responsive-filters .form-group {
        width: 100%;
      }
        .date-heading {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
      }
    
      .date-heading span {
        display: block;
        width: 100%;
      }
    }
    .action-buttons a {
      display: inline-block;
      margin-right: 8px;
      padding: 6px 10px;
      border-radius: 4px;
      font-size: 14px;
      text-decoration: none;
    }
    
    .btn-edit {
      background: #e3f2fd;
      color: #1565c0;
    }
    
    .btn-delete {
      background-color: #ffebee;
      color: #c62828;
      padding: 6px 10px;
      font-size: 14px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-weight: 500;
      transition: background-color 0.2s ease;
    }
    .btn-delete:hover {
      background-color: #ffcdd2;
    }
    .toggle-btn {
        margin-left: 12px;
        padding: 4px 8px;
        font-size: 14px;
        cursor: pointer;
        background-color: #f0f0f0;
        border: 1px solid #ccc;
        border-radius: 4px;
    }
    .responsive-filters {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 16px;
    }
    .deleted-transaction {
        background-color: #ffecec;
        color: #d00;
        font-weight: bold;
    }
  </style>
</head>
<body>
  <!-- Header -->
  <div class="header">
      <h2>Quản lý thu chi</h2>
      <div class="user">
        <a href="profile.php" class="profile-link">
          <span>Xin chào, <?= htmlspecialchars($user['fullname'] ?? '') ?></span>
          <img src="<?= $avatarPath ?>" alt="Avatar" class="avatar-img">
        </a>
      </div>
    </div>

  <div class="dashboard-wrapper">  
      <!-- Sidebar -->
      <nav class="sidebar">
        <h3>Các khoản tiền</h3>
        <?php foreach ($accounts as $acc): ?>
          <a href="edit_account_balance.php?account_id=<?= $acc['id'] ?>" class="account-card">
            <div class="account-name"><?= htmlspecialchars($acc['name']) ?></div>
            <div class="account-balance">
              Số dư: <?= number_format($acc['balance'] ?? 0, 0, ',', '.') ?> VND
            </div>
          </a>
        <?php endforeach; ?>
        <a href="create_account.php" class="add-account">+ Thêm khoản tiền</a>
        <div class="account-total">
          <strong>Tổng số dư:</strong>
          <?= number_format($totalAccountBalance, 0, ',', '.') ?> VND
        </div>
        <hr>
        <a href="advanced_statistics.php">📊 Thống kê nâng cao</a>
        <a href="trash.php" class="active">🗑️ Giao dịch đã xóa</a>
        <a href="feedback.php">📩 Gửi phản hồi</a>
        <?php if ($user['role'] === 'admin'): ?>
          <a href="admin_feedback.php">📬 Xem phản hồi</a>
        <?php endif; ?>
      </nav>
    <div class="content">
      <!-- Main Content -->
      <main class="main">
        <div class="content-header">
          <h2>Lịch sử thu chi</h2>
        </div>
    
        <!-- Filter Form -->
        <form method="get" class="filter-panel">
          <div class="filter-row">
            <!-- Các bộ lọc -->
            <div class="filters">
              <div class="form-group">
                <label for="from_date">Từ ngày</label>
                <input type="date" id="from_date" name="from_date" value="<?= htmlspecialchars($from_date) ?>">
              </div>
              <div class="form-group">
                <label for="to_date">Đến ngày</label>
                <input type="date" id="to_date" name="to_date" value="<?= htmlspecialchars($to_date) ?>">
              </div>
              <div class="form-group">
                <label for="type">Loại</label>
                <select id="type" name="type">
                    <option value="all" <?= $filter_type === 'all'? 'selected':'' ?>>Tất cả</option>
                    <option value="1" <?= $filter_type === '1'? 'selected':'' ?>>Thu</option>
                    <option value="2" <?= $filter_type === '2'? 'selected':'' ?>>Chi</option>
                    <option value="0" <?= $filter_type === '0'? 'selected':'' ?>>Cập nhật</option>
                </select>
              </div>
              <div class="form-group">
                <label for="description">Mô tả</label>
                <select id="description" name="description">
                  <option value="">Tất cả</option>
                  <?php
                  $desc_q = pg_query_params($conn, "SELECT DISTINCT description FROM transactions WHERE user_id = $1 ORDER BY description", [$user_id]);
                    while ($row = pg_fetch_assoc($desc_q)) {
                      $rawDesc = $row['description'];
                      $desc = (strpos($rawDesc, 'Tạo tài khoản mới:') === 0) ? 'Tạo khoản tiền mới' : htmlspecialchars($rawDesc);
                      $selected = ($filter_description === $rawDesc || $filter_description === 'Tạo khoản tiền mới') ? 'selected' : '';
                      echo "<option value=\"$desc\" $selected>$desc</option>";
                    }
                    ?>
                </select>
              </div>
              <div class="form-group">
                <label for="account_id">Khoản tiền</label>
                <select id="account_id" name="account_id">
                  <option value="0" <?= $filter_account===0? 'selected':'' ?>>Tất cả</option>
                  <?php
                  foreach ($accounts as $acc) {
                      $selected = ($filter_account == $acc['id']) ? 'selected' : '';
                      echo "<option value=\"{$acc['id']}\" $selected>{$acc['name']}</option>";
                  }
                  ?>
                </select>
              </div>
            </div>
              
            <!-- Tổng thu/chi -->
            <div class="filter-summary-row">
              <div class="stats-inline">
                <span>🔼 Tổng thu: <strong><?= number_format($totalThuAll ?? 0,0,',','.') ?> VND</strong></span>
                <span>🔽 Tổng chi: <strong><?= number_format($totalChiAll ?? 0,0,',','.') ?> VND</strong></span>
              </div>
                <a href="add_transaction.php" class="btn btn-primary add-transaction-btn">
                ➕ Thêm giao dịch
              </a>
              <div class="filter-buttons">
                <button type="submit">🔍 Lọc</button>
                <a href="dashboard.php" class="reset">🧹 Làm mới</a>
              </div>
            </div>  
          </div>
        </form>
        <!-- Grouped Transactions -->
        <?php if (empty($grouped)): ?>
          <p>Không có giao dịch nào.</p>
        <?php else: ?>
          <?php
                $groupedData = $grouped;
                foreach ($groupedData as $label => $entries):
                    $totalThu = 0;
                    $totalChi = 0;
                    foreach ($entries as $row) {
                        if ($row['type'] == 1) $totalThu += $row['amount'];  // Thu
                        elseif ($row['type'] == 2) $totalChi += $row['amount'];  // Chi
                    }
                    $groupId = 'group_' . md5($label);
                ?>
                <div class="date-group">
                  <div class="date-heading">
                      <div class="date-label"><?= htmlspecialchars($label) ?></div>
                      <div class="date-summary">
                        <span>🔼 Tổng thu: <?= number_format($totalThu,0,',','.') ?> VND</span>
                        <span>🔽 Tổng chi: <?= number_format($totalChi,0,',','.') ?> VND</span>
                      </div>
                      <button onclick="toggleGroup('<?= $groupId ?>')" class="toggle-btn">👁️ Xem chi tiết</button>
                    </div>
                </div>
                <?php $todayLabel = date('d/m/Y'); ?>
                <div id="<?= $groupId ?>" style="display: <?= ($label === $todayLabel) ? 'block' : 'none' ?>;">
                    <div class="table-wrapper">
                      <table>
                        <thead>
                            <tr>
                              <th>Giờ</th>
                              <th>Loại</th>
                              <th>Mô tả</th>
                              <th>Số tiền</th>
                              <th>Số dư còn lại</th>
                              <th>Khoản tiền</th>
                              <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($entries as $row): ?>
                            <?php if (!is_array($row)) continue; ?>
                            <?php
                              // fix mô tả khởi tạo
                              $d = $row['description'];
                              if (strpos($d, 'Tạo tài khoản mới:')===0) {
                                $d = 'Tạo khoản tiền mới';
                              }
                            if ($row['type'] == 3) {
                                    $d = '🗑️ ' . $d;
                                }
                            ?>
                            <tr class="<?= $row['type'] == 3 ? 'deleted-transaction' : '' ?>">
                              <td><?= date('H:i:s', strtotime($row['date'])) ?></td>
                              <td><?= $typeLabels[$row['type']] ?? '-' ?></td>
                              <td><?= htmlspecialchars($d ?: '-') ?></td>
                              <td class="<?= $row['type']==1? 'amount-income': ($row['type']==2? 'amount-expense':'') ?>">
                                <?= in_array($row['type'], [1,2]) ? number_format($row['amount']??0,0,',','.') : '0' ?> VND
                              </td>
                              <td><?= number_format($row['remaining_balance']??0,0,',','.') ?> VND</td>
                              <td><?= htmlspecialchars($row['account_name']) ?></td>
                              <td class="action-buttons">
                                  <?php if ($row['type'] == 1 || $row['type'] == 2): ?>
                                    <a href="edit_transaction.php?id=<?= $row['id'] ?>" class="btn-edit">✏️ Sửa</a>
                                    <form method="post" action="delete_transaction.php" style="display:inline;">
                                      <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                      <input type="hidden" name="step" value="info">
                                      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                      <button type="submit" class="btn-delete">🗑️ Xoá</button>
                                    </form>
                                  <?php elseif ($row['type'] == 3): ?>
                                    <form method="post" action="restore.php" style="display:inline;" onsubmit="return confirm('Khôi phục giao dịch này?');">
                                      <input type="hidden" name="transaction_id" value="<?= $row['id'] ?>">
                                      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                      <button type="submit" class="btn-edit">↩️ Khôi phục</button>
                                    </form>
                                  <?php else: ?>
                                    <span style="opacity: 0.5; color: gray;">🚫 Không thể chỉnh sửa</span>
                                  <?php endif; ?>
                                </td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
      </main>
    </div>
  </div>
    <!-- Popup phản hồi từ admin -->
    <?php if (!empty($admin_replies) && empty($_SESSION['feedback_hidden'])): ?>
      <div class="popup-feedback" id="adminFeedbackPopup">
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <strong>📬 Phản hồi từ admin:</strong>
          <button onclick="document.getElementById('adminFeedbackPopup').style.display='none'" style="background: none; border: none; font-size: 16px; cursor: pointer;">✖</button>
        </div>
        <div style="margin-top: 8px;">
          <?php foreach ($admin_replies as $reply): ?>
            <p style="margin-bottom: 8px;">
              🗨️ <?= nl2br(htmlspecialchars($reply['content'])) ?><br>
              <small style="color: gray;">🕒 <?= date('d/m/Y H:i', strtotime($reply['created_at'])) ?></small>
            </p>
          <?php endforeach; ?>
          <form method="post" style="margin-top: 8px;">
              <input type="hidden" name="feedback_id" value="<?= $feedback_popup['id'] ?>">
              <button type="submit" name="hide_feedback" style="...">✅ Đã đọc</button>
            </form>
        </div>
      </div>
    <?php endif; ?>


    
    <!-- Popup phản hồi từ hệ thống -->
    <?php if (!empty($admin_replies) && !$feedback_popup['is_read']): ?>
      <div class="popup-feedback" id="systemFeedbackPopup">
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <strong>📬 Phản hồi từ hệ thống</strong>
          <button onclick="document.getElementById('systemFeedbackPopup').style.display='none'" style="background: none; border: none; font-size: 16px; cursor: pointer;">✖</button>
        </div>
        <div style="margin-top: 8px;">
          <p><strong>Bạn đã gửi:</strong> <?= htmlspecialchars($feedback_popup['message']) ?></p>
          <p><strong>Trạng thái:</strong> <?= htmlspecialchars($feedback_popup['status']) ?></p>
          <?php if (!empty($feedback_popup['admin_reply'])): ?>
            <p><strong>Phản hồi từ Admin:</strong><br><?= nl2br(htmlspecialchars($feedback_popup['admin_reply'])) ?></p>
          <?php endif; ?>
          <form method="post" style="margin-top: 8px;">
            <button type="submit" name="hide_feedback" style="padding: 6px 12px; background: #ffc107; border: none; border-radius: 4px; cursor: pointer;">✅ Đã đọc</button>
          </form>
        </div>
      </div>
    <?php endif; ?>
    
    <style>
    .popup-feedback {
      position: fixed;
      bottom: 20px;
      right: 20px;
      background-color: #fff3cd;
      border: 1px solid #ffeeba;
      padding: 16px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.2);
      max-width: 320px;
      z-index: 9999;
      font-size: 14px;
    }
    </style>
    
    <!-- JS xử lý đóng popup -->
    <script>
    function closeAdminFeedback() {
      const popup = document.getElementById('adminFeedbackPopup');
      if (popup) popup.style.display = 'none';
    }
    function toggleGroup(id) {
        const el = document.getElementById(id);
        if (el.style.display === 'none') {
          el.style.display = 'block';
        } else {
          el.style.display = 'none';
        }
      }
    const deletedRows = document.querySelectorAll('.deleted-transaction');
     deletedRows.forEach(row => {
        // Đặt hẹn giờ 30 giây để ẩn dòng đó
      });
    </script>
</body>
</html>
