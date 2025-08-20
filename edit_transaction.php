<?php
session_start();
include "db.php"; // đảm bảo file db.php có kết nối pg_connect()
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$id = $_POST['id'] ?? $_GET['id'] ?? null;
if (!$id) {
    header("Location: dashboard.php");
    exit();
}

$query = "SELECT * FROM transactions WHERE id = $1 AND user_id = $2";
$result = pg_query_params($conn, $query, array($id, $user_id));
$transaction = pg_fetch_assoc($result);
if (!$result) {
    echo "<p style='color:red;'>Lỗi truy vấn cơ sở dữ liệu.</p>";
    exit();
}

if (!$transaction) {
    echo "<p style='color:red;'>Không tìm thấy giao dịch cần sửa.</p>";
    exit();
}
    // 👉 Gán các giá trị gốc để so sánh sau này
    $oldType = intval($transaction['type']);
    $oldAmount = floatval($transaction['amount']);
    $oldAccountId = intval($transaction['account_id']);

    // 👉 Truy vấn giao dịch cũ
    $oldQuery = "SELECT type, amount, account_id FROM transactions WHERE id = $1 AND user_id = $2";
    $oldResult = pg_query_params($conn, $oldQuery, array($id, $user_id));
    $oldTransaction = pg_fetch_assoc($oldResult);

    if (!$oldTransaction) {
        echo "<p style='color:red;'>Không tìm thấy giao dịch cũ.</p>";
        exit();
    }

    $oldType       = intval($oldTransaction['type']);
    $oldAmount     = floatval($oldTransaction['amount']);
    $oldAccountId  = intval($oldTransaction['account_id']);


$query = "SELECT t.*, a.name AS account_name, a.balance AS current_balance
          FROM transactions t
          JOIN accounts a ON t.account_id = a.id
          WHERE t.id = $1 AND t.user_id = $2";
$result = pg_query_params($conn, $query, array($id, $user_id));
$transaction = pg_fetch_assoc($result);

// 👉 Khi người dùng cập nhật giao dịch
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $type        = $_POST['type'];
    $rawAmount   = $_POST['amount'] ?? '0';
    $description = trim($_POST['content'] ?? '');
    $account_id  = intval($_POST['account_id']);
    $date_input = $_POST['transaction_date'] ?? date('d/m/Y');
    $time = $_POST['transaction_time'] ?? date('H:i');

    $type_code = intval($type);
    $sanitized = preg_replace('/[^\d\.]/', '', $rawAmount);
    $amount = floatval($sanitized);

    $newType = $type_code;
    $newAmount = $amount;
    $newAccountId = $account_id;
    $newDateTime = DateTime::createFromFormat('d/m/Y H:i', "$date_input $time");
    if (!$newDateTime) {
        echo "<p style='color:red;'>❌ Định dạng ngày giờ không hợp lệ. Vui lòng kiểm tra lại.</p>";
        exit();
    }
    $datetime = $newDateTime->format('Y-m-d H:i:s');
    $oldDateTimeObj = new DateTime($transaction['date']);
    $sameDateTime = $oldDateTimeObj->format('Y-m-d H:i') === $newDateTime->format('Y-m-d H:i');
    
    $balance_q = pg_query_params($conn, "SELECT balance FROM accounts WHERE id = $1 AND user_id = $2", array($account_id, $user_id));
    $balance_data = pg_fetch_assoc($balance_q);
    $updated_balance = floatval($balance_data['balance'] ?? 0);
    
    $type_code = isset($_POST['type']) ? intval($_POST['type']) : -1;
    if (!in_array($type_code, [1, 2])) {
        echo "<p style='color:red;'>Loại giao dịch không hợp lệ. Vui lòng chọn lại.</p>";
        exit();
    }

    // ✅ Kiểm tra & lọc số tiền
    $sanitized = preg_replace('/[^\d\.]/', '', $rawAmount);
    if ($sanitized === '' || !is_numeric($sanitized)) {
        echo "<p style='color:red;'>Số tiền không hợp lệ. Vui lòng nhập số.</p>";
        exit();
    }

    $amount = floatval($sanitized);
    $newType       = $type_code;
    $newAmount     = $amount;
    if ($amount < 0) {
        echo "<p style='color:red;'>Số tiền phải lớn hơn 0.</p>";
        exit();
    } elseif ($amount > 1000000000000) {
        echo "<p style='color:red;'>Số tiền vượt quá giới hạn (tối đa 1,000,000,000,000 VND).</p>";
        exit();
    }
    if (!empty($transaction['date'])) {
    $oldDateTime = new DateTime($transaction['date']);
    } else {
        $oldDateTime = new DateTime(); // hoặc gán mặc định
    }
    $newDateTime = DateTime::createFromFormat('d/m/Y H:i', "$date_input $time");
    if (!$newDateTime) {
        echo "<p style='color:red;'>❌ Định dạng ngày giờ không hợp lệ. Vui lòng kiểm tra lại.</p>";
        exit();
    }
    $datetime = $newDateTime->format('Y-m-d H:i:s');

    function simulateBalance($oldType, $oldAmount, $newType, $newAmount, $balanceAtTransaction) {
        $balance = $balanceAtTransaction;
        if ($oldType === 2) $balance += $oldAmount;
        if ($oldType === 1) $balance -= $oldAmount;
        if ($newType === 1) $balance += $newAmount;
        if ($newType === 2) $balance -= $newAmount;
        return $balance;
    }

    // sau khi có $oldType, $oldAmount, $oldAccountId, $sameDateTime
    $skipBalanceCheck = (
        $oldType      === $newType    &&
        $oldAmount    === $newAmount  &&
        $oldAccountId === $newAccountId &&
        !$sameDateTime
    );
    
    // Chỉ với loại Chi, và không phải chỉ đổi ngày
    if ($type_code === 2 && !$skipBalanceCheck) {
        $balanceQuery = <<<SQL
            SELECT SUM(CASE WHEN type = 1 THEN amount ELSE -amount END) AS bal
            FROM transactions
            WHERE account_id = $1
              AND user_id    = $2
              AND date       <= $3
              AND id         != $4
        SQL;
    
        $r = pg_query_params($conn, $balanceQuery, [
            $newAccountId, $user_id, $datetime, $id
        ]);
    
        if (!$r || pg_num_rows($r) === 0) {
            echo "<p style='color:red;'>Không thể truy vấn số dư. Vui lòng thử lại.</p>";
            exit();
        }
    
        $row = pg_fetch_assoc($r);
        $balanceBeforeUpdate = floatval($row['bal'] ?? 0);
        $simulated_balance = $balanceBeforeUpdate - $newAmount;
    
        if ($simulated_balance < 0) {
            echo "<div style='color:red; font-weight:bold;'>
                   ⚠️ Số dư sẽ âm tại thời điểm mới. Vui lòng chọn ngày khác hoặc giảm số tiền chi.
                 </div>";
            exit();
        }
    }


    // 👉 Tính toán ảnh hưởng đến số dư
    $delta = 0;

    if ($oldType === 1) { $delta += $oldAmount; } // Thu
    elseif ($oldType === 2) { $delta -= $oldAmount; } // Chi
    
    if ($newType === 1) { $delta += $newAmount; } // Thu
    elseif ($newType === 2) { $delta -= $newAmount; } // Chi
    

    $balanceQuery = "
        SELECT SUM(CASE WHEN type = 1 THEN amount ELSE -amount END) AS balance
        FROM transactions
        WHERE account_id = $1 AND user_id = $2 AND date <= $3 AND id != $4
    ";
    $balanceResult = pg_query_params($conn, $balanceQuery, array($account_id, $user_id, $datetime, $id));
    $balanceRow = pg_fetch_assoc($balanceResult);
    $balanceAtTransaction = floatval($balanceRow['balance'] ?? 0);
    
    // Cộng thêm giao dịch đang sửa
    $balanceAtTransaction += ($type_code === 1) ? $amount : -$amount;

function recalculateRemainingBalance($conn, $user_id, $account_id) {
    $query = "
        SELECT id, type, amount, date
        FROM transactions
        WHERE user_id = $1 AND account_id = $2
        ORDER BY date ASC
    ";
    $result = pg_query_params($conn, $query, array($user_id, $account_id));

    $running_balance = 0;
    $last_transaction_id = null;

    while ($row = pg_fetch_assoc($result)) {
        $amount = floatval($row['amount']);
        $type = intval($row['type']);

        if ($type === 1) { // Thu
            $running_balance += $amount;
        } elseif ($type === 2) { // Chi
            $running_balance -= $amount;
        }

        // Cập nhật lại số dư còn lại cho từng giao dịch
        pg_query_params($conn,
            "UPDATE transactions SET remaining_balance = $1 WHERE id = $2 AND user_id = $3",
            array($running_balance, $row['id'], $user_id)
        );

        $last_transaction_id = $row['id'];
    }

    // Sau khi xử lý xong, cập nhật lại số dư tài khoản
    pg_query_params($conn,
        "UPDATE accounts SET balance = $1 WHERE id = $2 AND user_id = $3",
        array($running_balance, $account_id, $user_id)
    );
}

    
    function updateBalance($conn, $user_id, $account_id, $amount, $type) {
        $adjustment = ($type === 1) ? $amount : -$amount;
        pg_query_params($conn,
            "UPDATE accounts SET balance = balance + $1 WHERE id = $2 AND user_id = $3",
            array($adjustment, $account_id, $user_id)
        );
    }
    
    if ($oldType === $newType && $oldAmount === $newAmount && $oldAccountId === $account_id && $sameDateTime) {
        $_SESSION['message'] = "⚠️ Không có thay đổi nào được thực hiện.";
        header("Location: dashboard.php");
        exit();
    }
    $newAccountId = $account_id;
    $adjustment = 0;

    // Trừ giao dịch cũ
    if ($oldType === 1) { $adjustment += $oldAmount; } // Thu
    elseif ($oldType === 2) { $adjustment -= $oldAmount; } // Chi
    
    if ($newType === 1) { $adjustment += $newAmount; } // Thu
    elseif ($newType === 2) { $adjustment -= $newAmount; } // Chi

    pg_query_params($conn,
        "UPDATE accounts SET balance = balance + $1 WHERE id = $2 AND user_id = $3",
        array($adjustment, $account_id, $user_id)
    );

    
    // 👉 Cập nhật giao dịch
    $updateQuery = "UPDATE transactions 
                    SET type = $1, amount = $2, description = $3, date = $4, account_id = $5 
                    WHERE id = $6 AND user_id = $7";
    pg_query_params($conn, $updateQuery, array(
        $type_code, $amount, $description, $datetime, $account_id, $id, $user_id
    ));
    recalculateRemainingBalance($conn, $user_id, $account_id);
    
    $_SESSION['message'] = "✅ Giao dịch đã được cập nhật thành công.";
    header("Location: dashboard.php");
    exit();
}

// Gán biến để sử dụng trong HTML
$account_name = $transaction['account_name'] ?? 'Không xác định';
$current_balance = floatval($transaction['current_balance'] ?? 0);
$transaction_type_code = intval($transaction['type'] ?? 0);
$transaction_type = ($transaction_type_code === 1) ? 'thu' : (($transaction_type_code === 2) ? 'chi' : 'khác');
$amount = floatval($transaction['amount'] ?? 0);
if ($amount < 0) {
    echo "<p style='color:red;'>Số tiền phải lớn hơn 0.</p>";
    exit();
}
$selected_content = $transaction['description'] ?? '';
$datetime = $transaction['date'] ?? date('Y-m-d H:i');
$date = date('Y-m-d', strtotime($datetime));
$time = date('H:i', strtotime($datetime));
$account_id = $transaction['account_id'] ?? 0;

// Gán danh sách nội dung mẫu
$content_options = ["Ăn uống", "Đi lại", "Lương", "Thưởng", "Tiền điện", "Tiền nước", "Số dư ban đầu", "Chuyển khoản"];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Sửa giao dịch</title>
  <link rel="stylesheet" href="styles.css">
  <script>
    function updateMaxAmount() {
      const type = document.getElementById('type').value;
      const balanceRaw = document.getElementById('balance').value.replace(/[^\d]/g, '');
      const balance = parseInt(balanceRaw);
      const amountInput = document.getElementById('amount');
      if (type === 'thu') {
        amountInput.max = 99999999 - balance;
      } else {
        amountInput.max = balance;
      }
    }
  </script>
    <style>
        /* Reset & base styles */
        body {
          margin: 0;
          padding: 0;
          font-family: 'Segoe UI', Tahoma, sans-serif;
          background-color: #f4f6f8;
          color: #333;
        }
        
        /* Container */
        .container {
          max-width: 600px;
          margin: 40px auto;
          background-color: #fff;
          padding: 30px 40px;
          border-radius: 12px;
          box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        /* Heading */
        h1 {
          text-align: center;
          color: #2c3e50;
          margin-bottom: 30px;
        }
        
        /* Labels & inputs */
        label {
          display: block;
          margin-top: 20px;
          font-weight: 600;
          color: #34495e;
        }
        
        input[type="text"],
        input[type="number"],
        input[type="date"],
        input[type="time"],
        select,
        input[list] {
          width: 100%;
          padding: 10px 12px;
          margin-top: 8px;
          border: 1px solid #ccc;
          border-radius: 6px;
          box-sizing: border-box;
          font-size: 15px;
        }
        
        /* Time row */
        div[style*="display: flex"] {
          margin-top: 8px;
        }
        
        /* Submit button */
        input[type="submit"] {
          background-color: #007BFF;;
          color: white;
          padding: 12px;
          margin-top: 30px;
          border: none;
          border-radius: 6px;
          cursor: pointer;
          font-size: 16px;
          width: 100%;
          transition: background-color 0.3s ease;
        }
        
        input[type="submit"]:hover {
          background-color: #2980b9;
        }
        
        /* Back link */
        .back-link {
          display: block;
          text-align: center;
          margin-top: 20px;
          color: #7f8c8d;
          text-decoration: none;
          font-size: 14px;
        }
        
        .back-link:hover {
          text-decoration: underline;
        }

        .btn-save {
          background-color: #2ecc71;
          color: white;
          padding: 12px 20px;
          border: none;
          border-radius: 8px;
          font-size: 16px;
          font-weight: 600;
          cursor: pointer;
          transition: background-color 0.3s ease, transform 0.2s ease;
        }
        
        .btn-save:hover {
          background-color: #0056b3;
          transform: translateY(-2px);
        }
        
        .btn-back {
          display: block;
          text-align: center;
          margin-top: 22px;
          color: #007BFF;
          text-decoration: none;
        }
        
        .btn-back:hover {
          text-decoration: underline;
        }
        .flatpickr-wrapper {
          position: relative;
          width: 100%;
        }
        
        .flatpickr-wrapper input {
          width: 100%;
          height: 38px;
          font-size: 15px;
          padding-right: 40px; /* chừa chỗ cho nút 📅 */
        }
        
        .calendar-btn {
          position: absolute;
          top: 50%;
          right: 10px;
          transform: translateY(-50%);
          background: none;
          border: none;
          font-size: 20px;
          color: #333;
          cursor: pointer;
        }
    </style>
</head>
<body onload="updateMaxAmount()">
  <div class="container">
    <h1>✏️ Sửa giao dịch</h1>
    <form action="edit_transaction.php?id=<?= $id ?>" method="POST">
    <input type="hidden" name="id" value="<?= $id ?>">
      <label>Tên khoản tiền</label>
      <input type="text" name="account" value="<?= $account_name ?>" readonly>
        <input type="hidden" name="account_id" value="<?= $account_id ?>">

      <label>Số dư hiện tại</label>
      <input type="text" id="balance" value="<?= number_format((float)$current_balance, 0, ',', '.') ?> VND" readonly>

      <label>Loại giao dịch</label>
      <select name="type" id="type" onchange="updateMaxAmount()">
        <option value="1" <?= $transaction['type'] == 1 ? 'selected' : '' ?>>Thu</option>
        <option value="2" <?= $transaction['type'] == 2 ? 'selected' : '' ?>>Chi</option>
      </select>

      <label>Số tiền</label>
      <input type="text" id="amount" maxlength="10" name="amount" value="<?= number_format($amount, 0, ',', ',') ?>" required>

      <label>Nội dung giao dịch</label>
      <input list="content-list" name="content" maxlength="10" value="<?= $selected_content ?>">
      <datalist id="content-list">
        <?php foreach ($content_options as $option): ?>
          <option value="<?= $option ?>">
        <?php endforeach; ?>
      </datalist>

      <label>Thời gian giao dịch:</label>
        <div style="display: flex; gap: 12px;">
          <div style="flex: 1; position: relative;">
            <div class="flatpickr-wrapper">
              <input
                type="text"
                id="datepicker"
                name="transaction_date"
                class="form-control"
                data-input
                placeholder="Chọn ngày"
                value="<?= htmlspecialchars($_POST['transaction_date'] ?? date('d/m/Y', strtotime($datetime))) ?>"
                required
              >
              <button type="button" class="calendar-btn" data-toggle title="Chọn ngày">📅</button>
            </div>
          </div>
        
          <div style="flex: 1;">
            <input
              type="time"
              name="transaction_time"
              class="form-control"
              value="<?= htmlspecialchars($_POST['transaction_time'] ?? date('H:i', strtotime($datetime))) ?>"
              required
            >
          </div>
        </div>

      <input type="submit" value="💾 Lưu thay đổi" class="btn-save">
        <a href="dashboard.php" class="btn-back">← Quay lại Dashboard</a>
    </form>
  </div>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
      document.addEventListener("DOMContentLoaded", function () {
        const datepickerInstance = flatpickr("#datepicker", {
          dateFormat: "d/m/Y",
          defaultDate: "<?= date('d/m/Y', strtotime($datetime)) ?>",
          maxDate: "today"
        });
    
        const calendarBtn = document.querySelector(".calendar-btn");
        if (calendarBtn) {
          calendarBtn.addEventListener("click", function () {
            datepickerInstance.open();
          });
        }
    
        const amountInput = document.getElementById('amount');
        if (amountInput) {
          amountInput.addEventListener('input', function () {
            let raw = this.value.replace(/,/g, '');
            if (!isNaN(raw) && raw !== '') {
              this.value = parseFloat(raw).toLocaleString('en-US');
            } else {
              this.value = '';
            }
          });
    
          document.querySelector('form').addEventListener('submit', function () {
            const raw = amountInput.value.replace(/,/g, '');
            amountInput.value = raw;
          });
        }
      });
    </script>
</body>
</html>
