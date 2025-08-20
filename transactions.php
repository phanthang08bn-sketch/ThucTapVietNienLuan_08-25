<?php
session_start();
include "db.php";
define('MAX_BALANCE', 100000000);
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

$user_id = $_SESSION['user_id'];
$success = "";
$error = "";

// Lấy danh sách tài khoản
$accounts = [];
$result = pg_query_params($conn, "SELECT id, name, balance FROM accounts WHERE user_id = $1", [$user_id]);
while ($row = pg_fetch_assoc($result)) {
  $accounts[] = $row;
}

// Xử lý POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $account_id = intval($_POST['account_id'] ?? 0);
  $type = $_POST['type'] ?? '';
  $rawAmount = $_POST['amount'] ?? '';
  $description = trim($_POST['description'] ?? '');
  $date_input = $_POST['transaction_date'] ?? '';
  $time_input = $_POST['transaction_time'] ?? date('H:i');

  try {
    $account = null;
    foreach ($accounts as $acc) {
      if ($acc['id'] == $account_id) {
        $account = $acc;
        break;
      }
    }
    if (!$account) throw new Exception("Tài khoản không hợp lệ.");

    $date_valid = DateTime::createFromFormat('d/m/Y', $date_input);
    $time_valid = preg_match('/^([01]\\d|2[0-3]):[0-5]\\d$/', $time_input);
    if (!$date_valid || !$time_valid) throw new Exception("Ngày giờ không hợp lệ.");

    $dtObj = DateTime::createFromFormat('d/m/Y H:i', "$date_input $time_input");
    $datetime = $dtObj->format('Y-m-d H:i:s');

    $sanitized = preg_replace('/[^\\d\\.\\-]/', '', $rawAmount);
    if (!is_numeric($sanitized)) throw new Exception("Số tiền không hợp lệ.");
    $amount = floatval($sanitized);
    if ($amount <= 0) throw new Exception("Số tiền phải > 0.");
    if ($amount > MAX_BALANCE) throw new Exception("Số tiền vượt giới hạn.");

    $type_value = ($type === 'chi') ? 1 : 0;
    $new_balance = ($type_value === 0) ? $account['balance'] + $amount : $account['balance'] - $amount;
    if ($new_balance < 0 || $new_balance > MAX_BALANCE) throw new Exception("Số dư sau giao dịch không hợp lệ.");

    pg_query($conn, 'BEGIN');
    pg_query_params($conn, "UPDATE accounts SET balance = $1 WHERE id = $2 AND user_id = $3", [$new_balance, $account_id, $user_id]);
    if ($description === '') {
      $description = $type_value === 0 ? 'Giao dịch thu' : 'Giao dịch chi';
    }
    pg_query_params($conn, "INSERT INTO transactions (account_id, user_id, type, amount, description, remaining_balance, date) VALUES ($1, $2, $3, $4, $5, $6, $7)", [$account_id, $user_id, $type_value, $amount, $description, $new_balance, $datetime]);
    pg_query($conn, 'COMMIT');
    $success = "✅ Giao dịch đã được thêm!";
  } catch (Exception $e) {
    pg_query($conn, 'ROLLBACK');
    $error = "❌ Lỗi: " . htmlspecialchars($e->getMessage());
  }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Thêm giao dịch</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <style>
    body { font-family: Arial, sans-serif; background: #f2f2f2; margin: 0; padding: 0; }
    .container { max-width: 560px; margin: 60px auto; padding: 30px 24px; background: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    h2 { text-align: center; margin-bottom: 26px; }
    label { font-weight: bold; margin-bottom: 6px; display: block; }
    .form-control { width: 100%; padding: 10px 12px; font-size: 16px; border: 1px solid #ccc; border-radius: 6px; margin-bottom: 18px; box-sizing: border-box; }
    button.form-control { background-color: #007BFF; color: white; border: none; cursor: pointer; }
    button.form-control:hover { background-color: #0056b3; }
    .back { display: block; text-align: center; margin-top: 22px; color: #007BFF; text-decoration: none; }
    .back:hover { text-decoration: underline; }
    .success { color: green; text-align: center; margin-bottom: 16px; }
    .error { color: red; text-align: center; margin-bottom: 16px; }
    .flatpickr-wrapper { position: relative; }
    .calendar-btn { position: absolute; top: 6px; right: 10px; background: none; border: none; font-size: 20px; color: #333; cursor: pointer; }
  </style>
</head>
<body>
  <div class="container">
    <h2>➕ Thêm giao dịch</h2>
    <?php if ($success): ?><p class="success"><?= $success ?></p><?php endif; ?>
    <?php if ($error): ?><p class="error"><?= $error ?></p><?php endif; ?>

    <form method="post">
      <label>Khoản tiền:</label>
      <select name="account_id" class="form-control" required>
        <?php foreach ($accounts as $acc): ?>
          <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['name']) ?> (<?= number_format($acc['balance'], 0, ',', '.') ?> VND)</option>
        <?php endforeach; ?>
      </select>

      <label>Loại giao dịch:</label>
      <select name="type" class="form-control" required>
        <option value="thu">Thu</option>
        <option value="chi">Chi</option>
      </select>

      <label>Số tiền:</label>
      <input type="text" name="amount" class="form-control" placeholder="VD: 500000" required>

      <label>Mô tả:</label>
      <input type="text" name="description" class="form-control" maxlength="255" placeholder="Nhập mô tả">

      <label>Thời gian giao dịch:</label>
      <div style="display: flex; gap: 12px;">
        <div style="flex: 1; position: relative;">
          <div class="flatpickr-wrapper">
            <input type="text" name="transaction_date" class="form-control" data-input placeholder="Chọn ngày" required>
            <button type="button" class="calendar-btn" data-toggle title="Chọn ngày">📅</button>
          </div>
        </div>
        <div style="flex: 1;">
          <input type="time" name="transaction_time" class="form-control" value="<?= date('H:i') ?>" required>
        </div>
      </div>

      <button type="submit" class="form-control">💾 Lưu giao dịch</button>
    </form>
    <a href="dashboard.php" class="back">← Quay lại Dashboard</a>
  </div>

  <script>
    flatpickr(".flatpickr-wrapper", {
      dateFormat: "d/m/Y",
      locale: "vi",
      defaultDate: new Date(),
      wrap: true,
      allowInput: true
    });
    document.querySelector("[data-toggle]").addEventListener("click", function() {
      document.querySelector("[name='transaction_date']")._flatpickr.open();
    });
  </script>
</body>
</html>
