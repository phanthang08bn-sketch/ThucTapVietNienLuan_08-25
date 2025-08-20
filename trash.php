<?php
session_start();
include "db.php";

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

// Thông báo khôi phục
if (!empty($_SESSION['restored'])) {
  echo "<div style='background-color: #fff3cd; color: #856404; padding: 12px; margin: 16px 0; border: 1px solid #ffeeba; border-radius: 6px; font-weight: bold;'>" . $_SESSION['restored'] . "</div>";
  unset($_SESSION['restored']);
}

// Truy vấn giao dịch đã xóa
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$description = $_GET['description'] ?? '';
$account_id = $_GET['account_id'] ?? '';
$sql = "SELECT t.*, a.name AS account_name 
        FROM transactions t 
        LEFT JOIN accounts a ON t.account_id = a.id 
        WHERE t.user_id = $1 AND t.type = 3";
$params = [$user_id];
$idx = 2;
$avatar = '';
if ($from_date) {
  $sql .= " AND DATE(date) >= \$$idx";
  $params[] = $from_date;
  $idx++;
}
if ($to_date) {
  $sql .= " AND DATE(date) <= \$$idx";
  $params[] = $to_date;
  $idx++;
}
if ($description) {
  $sql .= " AND description ILIKE \$$idx";
  $params[] = "%$description%";
  $idx++;
}
if ($account_id) {
  $sql .= " AND account_id = \$$idx";
  $params[] = $account_id;
  $idx++;
}

$sql .= " ORDER BY date DESC";
$res = pg_query_params($conn, $sql, $params);
if (!$res) {
  echo "Lỗi truy vấn cơ sở dữ liệu.";
  exit();
}
$deleted_count = pg_num_rows($res);
$deleted_transactions = pg_fetch_all($res) ?: [];

$grouped = [];

foreach ($deleted_transactions as $tran) {
    $date = date('Y-m-d', strtotime($tran['date']));
    if (!isset($grouped[$date])) {
        $grouped[$date] = [];
    }
    $grouped[$date][] = $tran;
}

// Truy vấn danh sách tài khoản
$account_res = pg_query_params($conn, "SELECT id, name, balance FROM accounts WHERE user_id = $1", [$user_id]);
$accounts = pg_fetch_all($account_res) ?: [];

$totalAccountBalance = '0';
foreach ($accounts as $acc) {
    $balance_res = pg_query_params($conn, "SELECT balance FROM accounts WHERE id = $1 AND user_id = $2", [$acc['id'], $user_id]);
    if ($balance_res && $row = pg_fetch_assoc($balance_res)) {
        $totalAccountBalance += $row['balance'];
    }
}
$res = pg_query_params($conn, "SELECT username, avatar, fullname, birthyear, email FROM users WHERE id = $1", [$user_id]);
$user = pg_fetch_assoc($res);
$avatarFile = $user['avatar'] ?? '';
$avatarPath = 'uploads/' . (file_exists(__DIR__ . '/uploads/' . $avatarFile) && !empty($avatarFile) ? $avatarFile : 'avt_mem.png');
$typeLabels = [
    1 => 'Thu nhập',
    2 => 'Chi tiêu',
    3 => 'Đã xóa'
];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>🗑️ Giao dịch đã xóa</title>
    <style>
    :root {
      --color-primary: #1e88e5;
      --color-danger: #e53935;
      --color-bg: #f9fafb;
      --color-card: #ffffff;
      --color-text: #2e3d49;
      --color-muted: #64748b;
      --border-radius: 8px;
      --spacing: 16px;
      --transition-speed: 0.3s;
      --sidebar-width: 260px;
    }
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    body {
      font-family: 'Segoe UI', Tahoma, sans-serif;
      background: var(--color-bg);
      color: var(--color-text);
    }
    
    .header {
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
    .header h2 {
      margin: 0;
    }
    .header .user img {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      margin-left: 10px;
      object-fit: cover;
      border: 2px solid white;
    }
    .user {
      display: flex;
      align-items: center;
    }
    
    .profile-link {
      color: white;
      text-decoration: none;
      display: flex;
      align-items: center;
    }
    
    .avatar-img {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      margin-left: 8px;
    }
    
    .dashboard-wrapper {
      display: grid;
      grid-template-columns: var(--sidebar-width) 1fr;
      gap: var(--spacing);
      padding: var(--spacing);
      min-height: 100vh;
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
      min-height: calc(100vh - 60px - 2 * var(--spacing));
    }
    .sidebar {
      background: var(--color-card);
      padding: var(--spacing);
      border-radius: var(--border-radius);
    }
    
    .sidebar h3 {
      font-size: 0.9rem;
      color: var(--color-muted);
      margin-bottom: 8px;
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
    }    
    .account-balance {
      font-size: 0.85rem;
      color: var(--color-text);
    }
    .add-account {
      display: block;
      margin-top: 8px;
      text-decoration: none;
      color: var(--color-primary);
    }
    
    .account-total {
      margin-top: 16px;
      padding: 12px;
      background: #ecfdf3;
      border: 1px solid #bbf7d0;
      border-radius: var(--border-radius);
      font-weight: 600;
    }
        
    .sidebar a {
      display: block;
      margin-top: 12px;
      text-decoration: none;
      color: var(--color-text);
    }
    
    .sidebar a.active {
      font-weight: bold;
      color: var(--color-primary);
    }
    
    .content {
      background: var(--color-card);
      padding: var(--spacing);
      border-radius: var(--border-radius);
    }
    
    .content-header h2 {
      margin-top: 0;
    }
    
    .filter-panel {
      background: var(--color-card);
      padding: var(--spacing);
      border-radius: var(--border-radius);
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      margin-bottom: var(--spacing);
    }
    .filters {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 16px;
    }
    .filter-row {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      align-items: center;
    }
    .filter-buttons {
      display: flex;
      gap: 12px;
      margin-top: 12px;
    }
    .filters {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }
    .filter-buttons button {
      background: var(--color-primary);
      color: white;
      border: none;
      padding: 10px 16px;
      border-radius: var(--border-radius);
      cursor: pointer;
    }
    .filter-summary-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 12px;
      flex-wrap: wrap;
    }
    
    .stats-inline span {
      margin-right: 16px;
    }
    .filter-buttons .reset {
      background: #f1f5f9;
      color: var(--color-text);
      border: 1px solid #cbd5e1;
    }
    .filter-buttons button,
    .filter-buttons .reset {
      background-color: var(--color-primary);
      color: white;
      border: none;
      padding: 8px 12px;
      border-radius: 4px;
      cursor: pointer;
      text-decoration: none;
    }
    
    .filter-buttons .reset {
      background-color: var(--color-danger);
    }
    
    .table-wrapper {
      overflow-x: auto;
    }
    
    table {
      width: 100%;
      border-collapse: collapse;
      background: var(--color-card);
      border-radius: var(--border-radius);
      box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    .table-wrapper table {
      width: 100%;
      border-collapse: collapse;
      font-size: 15px;
    }
    
    .table-wrapper th, .table-wrapper td {
      padding: 12px 8px;
      font-size: 15px;
      color: var(--color-text);
    }
    
    .table-wrapper th {
      background-color: #f0f4f8;
      color: #333;
      font-weight: 600;
    }
    .btn-restore {
      background-color: var(--color-primary);
      color: #fff;
      padding: 6px 12px;
      border-radius: 4px;
      font-weight: 500;
      border: none;
      cursor: pointer;
    }
    .btn-restore:hover {
      background-color: #1565c0;
    }
    .deleted-transaction {
      background-color: #ffecec;
      color: #d00;
      font-weight: bold;
    }

    th, td {
      padding: 12px;
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
    
    .deleted-transaction td {
      color: var(--color-danger);
    }
    
    .action-buttons {
      display: flex;
      gap: 8px;
    }
    
    .btn-edit,
    .btn-delete {
      padding: 6px 10px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      color: white;
    }
    .deleted-transaction {
      background-color: #ffecec;
      color: var(--color-danger);
      text-decoration: line-through;
      opacity: 0.7;
    }
    .btn-edit {
      background: #e3f2fd;
      color: #1565c0;
      padding: 6px 10px;
      border-radius: 4px;
      font-size: 14px;
      text-decoration: none;
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
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
      <h2>Giao dịch đã xóa</h2>
      <div class="user">
        <a href="profile.php" class="profile-link">
          <span>Xin chào, <?= htmlspecialchars($user['fullname'] ?? '') ?></span>
          <img src="<?= $avatarPath ?>" alt="Avatar">
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
            <div class="account-balance">Số dư: <?= number_format($acc['balance'], 0, ',', '.') ?> VND</div>
          </a>
        <?php endforeach; ?>
        <a href="create_account.php" class="add-account">+ Thêm khoản tiền</a>
        <div class="account-total">
          <strong>Tổng số dư:</strong> <?= number_format($totalAccountBalance, 0, ',', '.') ?> VND
        </div>
        <hr>
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="advanced_statistics.php">📊 Thống kê nâng cao</a>
        <a href="trash.php" class="active">🗑️ Giao dịch đã xóa</a>
        <a href="feedback.php">📩 Gửi phản hồi</a>
      </nav>
    
      <!-- Content -->
      <div class="content">
        <main class="main">
          <div class="content-header">
            <h2>🗑️ Giao dịch đã xóa</h2>
          </div>
            <?php if (isset($_SESSION['restored'])) : ?>
              <div class="alert alert-success" style="margin: 12px 0; padding: 10px; background: #e6ffed; border-left: 5px solid #28a745;">
                ✅ Giao dịch đã được khôi phục thành công.
              </div>
              <?php unset($_SESSION['restored']); ?>
            <?php endif; ?>
          <?php if (empty($deleted_transactions)): ?>
            <p>Không có giao dịch đã xóa nào.</p>
          <?php else: ?>
            <div class="table-wrapper">
              <table>
                <thead>
                  <tr>
                    <th>Ngày</th>
                    <th>Thời điểm xóa</th>
                    <th>Loại</th>
                    <th>Mô tả</th>
                    <th>Số tiền</th>
                    <th>Khoản tiền</th>
                    <th>Thao tác</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($deleted_transactions as $row): ?>
                    <tr class="deleted-transaction">
                      <td><?= date('d/m/Y H:i', strtotime($row['date'])) ?></td>
                      <td>
                          <?= !empty($row['deleted_at']) ? date('d/m/Y H:i', strtotime($row['deleted_at'])) : 'Chưa rõ' ?>
                      </td>
                      <td><?= $typeLabels[$row['type']] ?? '-' ?></td>
                      <td><?= htmlspecialchars($row['description']) ?></td>
                      <td><?= number_format($row['amount'], 0, ',', '.') ?> VND</td>
                      <td><?= htmlspecialchars($row['account_name']) ?></td>
                      <td class="action-buttons">
                        <form method="post" action="restore.php" style="display:inline;">
                          <input type="hidden" name="transaction_id" value="<?= $row['id'] ?>">
                          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                          <button type="submit" class="btn-edit">↩️ Khôi phục</button>
                        </form>
                        <form method="post" action="delete_forever.php" style="display:inline;" onsubmit="return confirm('Xóa vĩnh viễn giao dịch này?');">
                          <input type="hidden" name="transaction_id" value="<?= $row['id'] ?>">
                          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                          <button type="submit" class="btn-delete">❌ Xóa vĩnh viễn</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </main>
      </div>
    </div>
    <script>
    function toggleGroup(id) {
      const el = document.getElementById(id);
      el.style.display = (el.style.display === 'none') ? 'block' : 'none';
    }
    </script>
</body>
</html>
