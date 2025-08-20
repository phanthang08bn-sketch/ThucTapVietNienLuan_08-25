<?php
session_start();
include "db.php";
define('MAX_BALANCE', 100000000);
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

$user_id = $_SESSION['user_id'];
$success = "";
$error = "";

// 🔹 Lấy danh sách tài khoản
$accounts = [];
$result = pg_query_params($conn, "SELECT id, name, balance FROM accounts WHERE user_id = $1", [$user_id]);
while ($row = pg_fetch_assoc($result)) {
  $accounts[] = $row;
}

if ($_SERVER["REQUEST_METHOD"] === 'POST') {
  if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    throw new Exception("❌ CSRF token không hợp lệ.");
  }
  $account_id = intval($_POST['account_id'] ?? 0);
  if ($account_id <= 0) {
    throw new Exception("Tài khoản không hợp lệ.");
  }
  $type = $_POST['type'] ?? '';
  $rawAmount = $_POST['amount'] ?? '';
  $description = htmlspecialchars(trim($_POST['description'] ?? ''));
  $date_input = $_POST['transaction_date'] ?? '';
  $time_input = $_POST['transaction_time'] ?? date('H:i');

  try {
    // 🔸 Kiểm tra độ dài và định dạng số tiền
    if (strlen($rawAmount) > 10) throw new Exception("Số tiền quá dài. Tối đa 10 ký tự.");
    if (strpos($rawAmount, '-') !== false) throw new Exception("Không được nhập số âm.");
    if (substr_count($rawAmount, '.') > 1) throw new Exception("Định dạng số tiền không hợp lệ.");
    
    // 🔸 Làm sạch và kiểm tra số tiền
    $rawAmount = str_replace(',', '', $rawAmount);
    $sanitized = preg_replace('/[^\d.]/', '', $rawAmount);
    if (!is_numeric($sanitized)) throw new Exception("Số tiền không hợp lệ.");
      
    // 🔸 Kiểm tra tài khoản
    $acc_result = pg_query_params($conn, "SELECT * FROM accounts WHERE id = $1 AND user_id = $2", [$account_id, $user_id]);
    $account = pg_fetch_assoc($acc_result);
    if (!$account) throw new Exception("Tài khoản không tồn tại.");

    // 🔸 Kiểm tra loại giao dịch
    if ($type !== 'thu' && $type !== 'chi') throw new Exception("Loại giao dịch không hợp lệ.");

    // 🔸 Kiểm tra ngày giờ
    $dtObj = DateTime::createFromFormat('d/m/Y H:i', "$date_input $time_input");
    if (!$dtObj) throw new Exception("Ngày giờ không hợp lệ.");
    $datetime = $dtObj->format('Y-m-d H:i:s');

    // 🔸 Xử lý số tiền
    $amount = floatval($sanitized);
    if ($amount <= 0) throw new Exception("Số tiền phải > 0.");
    if ($amount > MAX_BALANCE) throw new Exception("Số tiền vượt giới hạn.");

    // 🔸 Tính toán số dư mới
    $type_value = ($type === 'thu') ? 1 : 2;
    $new_balance = ($type_value === 1)
    ? $account['balance'] + $amount
    : $account['balance'] - $amount;
    if ($new_balance < 0 || $new_balance > MAX_BALANCE) throw new Exception("Số dư sau giao dịch không hợp lệ.");

    // 🔸 Mô tả mặc định nếu trống
    if ($description === '') {
      $description = ($type_value === 1) ? 'Giao dịch thu' : 'Giao dịch chi';
    }

    // 🔸 Giới hạn mô tả
    if (mb_strlen($description) > 30) {
      throw new Exception("Mô tả quá dài. Tối đa 30 ký tự.");
    }
    
    // 🔸 Lưu giao dịch
    pg_query($conn, 'BEGIN');
    pg_query_params($conn, "UPDATE accounts SET balance = $1 WHERE id = $2 AND user_id = $3", [$new_balance, $account_id, $user_id]);
    pg_query_params($conn, "INSERT INTO transactions (account_id, user_id, type, amount, description, remaining_balance, date) VALUES ($1, $2, $3, $4, $5, $6, $7)", [
      $account_id, $user_id, $type_value, $amount, $description, $new_balance, $datetime
    ]);
    pg_query($conn, 'COMMIT');
    echo "<script>
      alert('✅ Giao dịch đã được thêm!\\nSố dư mới: " . number_format($new_balance, 0, ',', '.') . " VND');
      window.location.href = 'dashboard.php';
    </script>";
    exit();    
  } catch (Exception $e) {
    pg_query($conn, 'ROLLBACK');
    $error = "❌ Lỗi: " . htmlspecialchars($e->getMessage()) . "<br>Vui lòng kiểm tra lại thông tin nhập.";
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
    body {
      font-family: Arial, sans-serif;
      background: #f2f2f2;
      margin: 0;
      padding: 0;
    }
    .container {
      max-width: 560px;
      margin: 60px auto;
      padding: 30px 24px;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    h2 {
      text-align: center;
      margin-bottom: 26px;
    }
    label {
      display: block;
      font-weight: bold;
      margin-bottom: 6px;
      font-size: 15px;
    }
    .form-control {
      width: 100%;
      padding: 10px 12px;
      font-size: 16px;
      border: 1px solid #ccc;
      border-radius: 6px;
      box-sizing: border-box;
      margin-bottom: 18px;
    }
    button.form-control {
      background-color: #007BFF;
      color: white;
      border: none;
      cursor: pointer;
    }
    button.form-control:hover {
      background-color: #0056b3;
    }
    .back {
      display: block;
      text-align: center;
      color: #007BFF;
      text-decoration: none;
    }
    .back:hover {
      text-decoration: underline;
    }
    .flatpickr-wrapper {
      position: relative;
    }
    .calendar-btn {
      position: absolute;
      top: 6px;
      right: 10px;
      background: none;
      border: none;
      font-size: 20px;
      color: #333;
      cursor: pointer;
    }
    @media (max-width: 480px) {
      .form-control, .container > div {
        width: 100%;
        margin-bottom: 14px;
      }
      .flatpickr-wrapper {
        display: block;
        width: 100%;
        margin-bottom: 10px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>➕ Thêm giao dịch</h2>
    <?php if ($success): ?>
      <div style="color: green; text-align: center; margin-bottom: 16px; font-weight: bold;">
        <?= $success ?>
      </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
      <div style="color: red; text-align: center; margin-bottom: 16px; font-weight: bold;">
        <?= $error ?>
      </div>
    <?php endif; ?>
    <form method="post" action="add_transaction.php">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <label for="account_id">Khoản tiền:</label>
      <select name="account_id" class="form-control" required>
        <option value="">-- Chọn tài khoản --</option>
        <?php foreach ($accounts as $acc): ?>
          <option value="<?= $acc['id'] ?>" data-balance="<?= $acc['balance'] ?>">
            <?= htmlspecialchars($acc['name']) ?> — <?= number_format($acc['balance'], 0, ',', '.') ?> VND
          </option>
        <?php endforeach; ?>
      </select>

      <label for="type">Loại giao dịch:</label>
      <select name="type" id="type" class="form-control" required>
        <option value="thu">Thu</option>
        <option value="chi">Chi</option>
      </select>

      <label for="amount">Số tiền:</label>
      <input type="text" name="amount" id="amount" maxlength="10" class="form-control" required>

      <label>Nội dung giao dịch:</label>
      <input list="description-options" name="description" id="description" maxlength="30"
             placeholder="Nhập hoặc chọn nội dung" class="form-control"
             value="<?= htmlspecialchars($_POST['description'] ?? '') ?>">
      <datalist id="description-options">
        <!-- Gợi ý sẽ được thêm bằng JavaScript -->
      </datalist>
      
      <label>Thời gian giao dịch:</label>
      <div style="display: flex; gap: 12px;">
        <div style="flex: 1; position: relative;">
          <div class="flatpickr-wrapper">
            <input type="text" name="transaction_date" class="form-control" data-input placeholder="Chọn ngày" required value="<?= date('d/m/Y') ?>">
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
      allowInput: true,
      maxDate: "today"
    });
  
    const accountSelect = document.querySelector("select[name='account_id']");
    const typeSelect = document.getElementById("type");
    const amountInput = document.getElementById("amount");
  
    function updateAmountPlaceholder() {
      const selectedOption = accountSelect.options[accountSelect.selectedIndex];
      const balance = parseInt(selectedOption.dataset.balance || '0');
      const type = typeSelect.value;
  
      let suggested = type === "thu"
        ? 99999999 - balance
        : balance;
  
      let formatted = suggested.toLocaleString("vi-VN");
      if (formatted.length > 10) {
        formatted = formatted.slice(0, 10).replace(/,$/, '');
      }
  
      amountInput.placeholder = "Tối đa " + formatted + " VND";
    }

    const presetThu = ["Lương", "Thưởng", "Tiền lãi", "Bán hàng", "Khác"];
    const presetChi = ["Ăn uống", "Di chuyển", "Giải trí", "Mua sắm", "Khác"];
    
    function updateDescriptionOptions() {
      const type = typeSelect.value;
      const datalist = document.getElementById("description-options");
      const options = type === "thu" ? presetThu : type === "chi" ? presetChi : [];
      datalist.innerHTML = options.map(item => `<option value="${item}">`).join("");
    }
    
    typeSelect.addEventListener("change", updateDescriptionOptions);
    document.addEventListener("DOMContentLoaded", updateDescriptionOptions);

    document.addEventListener("DOMContentLoaded", updateAmountPlaceholder);
    accountSelect.addEventListener("change", updateAmountPlaceholder);
    typeSelect.addEventListener("change", updateAmountPlaceholder);
  
    amountInput.addEventListener("input", function () {
      let raw = this.value.replace(/,/g, '').replace(/[^\d.]/g, '');
      if (raw.length > 10) raw = raw.slice(0, 10);
  
      const [intPart, decPart] = raw.split('.');
      let formatted = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
      if (decPart !== undefined) {
        formatted += '.' + decPart.replace(/\D/g, '');
      }
      this.value = formatted;
    });
  
    document.querySelector("[data-toggle]").addEventListener("click", function () {
      document.querySelector("[name='transaction_date']")._flatpickr.open();
    });
  </script>
</body>
</html>

