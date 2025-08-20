<?php
session_start();
include "db.php";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_SESSION["user_id"] ?? null;
$transaction_id = $_POST["id"] ?? $_GET["id"] ?? null;
$step = $_POST["step"] ?? "info";

if (!$user_id || !$transaction_id) {
    echo "Thiếu thông tin người dùng hoặc giao dịch.";
    exit;
}

$now = date('Y-m-d H:i:s');

// Truy vấn thông tin giao dịch
$query = "SELECT amount, type, account_id FROM transactions WHERE id = $1 AND user_id = $2";
$result = pg_query_params($conn, $query, [$transaction_id, $user_id]);
$info = pg_fetch_assoc($result);

if (!$info) {
    echo "Giao dịch không tồn tại hoặc không thuộc quyền truy cập.";
    exit;
}

$amount = floatval($info["amount"]);
$formatted_amount = number_format($amount, 0, '.', ',');
$type = intval($info["type"]);
if (!in_array($type, [1, 2])) {
    echo "Loại giao dịch không hợp lệ để xóa.";
    exit;
}
if ($type === 3) {
    echo "Giao dịch đã bị xoá trước đó.";
    exit;
}

$account_id = intval($info["account_id"]);

$account_query = "SELECT name FROM accounts WHERE id = $1 AND user_id = $2";
$account_result = pg_query_params($conn, $account_query, [$account_id, $user_id]);
$account_data = pg_fetch_assoc($account_result);
$account_name = $account_data['name'] ?? 'Không xác định';


// Truy vấn số dư hiện tại
$balance_query = "SELECT balance FROM accounts WHERE id = $1 AND user_id = $2";
$balance_result = pg_query_params($conn, $balance_query, [$account_id, $user_id]);
$balance_data = pg_fetch_assoc($balance_result);
$current_balance = floatval($balance_data["balance"]);

// Tính số dư mới
$new_balance = ($type == 1) ? $current_balance - $amount : $current_balance + $amount;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token không hợp lệ.");
    }
    $error = "";
    if ($step === "info") {
        if ($new_balance < 0) {
            $step = "warning";
        } else {
            $step = "confirm";
        }
    }
        if ($step === "warning") {
        echo '<div style="max-width: 500px; margin: 40px auto; padding: 20px; border: 2px solid #f44336; border-radius: 8px; background-color: #fff5f5; font-family: Arial, sans-serif;">';
        echo '<h2 style="color: #d32f2f;">⚠️ Cảnh báo: Số dư sẽ bị âm nếu xoá giao dịch này</h2>';
        echo '<p><strong>Tài khoản:</strong> ' . htmlspecialchars($account_name ?? 'Không xác định') . '</p>';
        echo '<p><strong>Số dư hiện tại:</strong> ' . number_format($current_balance, 0, '.', ',') . ' VND</p>';
        echo '<p><strong>Số dư sau khi xoá:</strong> <span style="color: #d32f2f; font-weight: bold;">' . number_format($new_balance, 0, ',', '.') . ' VND</span></p>';
        echo '<form method="post" style="margin-top: 20px;">';
        echo '<input type="hidden" name="id" value="' . htmlspecialchars($transaction_id) . '">';
        echo '<input type="hidden" name="step" value="confirm">';
        echo '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
        echo '<button type="submit" style="background-color: #d32f2f; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">Tiếp tục xoá</button>';
        echo '<a href="dashboard.php" style="margin-left: 10px; padding: 10px 20px; background-color: #ccc; color: black; text-decoration: none; border-radius: 4px;">Quay lại</a>';
        echo '</form>';
        echo '</div>';
        exit;
    } elseif ($step === "confirm") {
        echo '<div style="max-width: 500px; margin: 40px auto; padding: 20px; border: 2px solid #1976d2; border-radius: 8px; background-color: #e3f2fd; font-family: Arial, sans-serif;">';
        echo '<h2 style="color: #1976d2;">🔐 Xác nhận xoá giao dịch</h2>';
        if (!empty($error)) {
            echo '<p style="color:red;">' . htmlspecialchars($error) . '</p>';
        }
        echo '<p>Vui lòng nhập mật khẩu để xác nhận xoá giao dịch khỏi tài khoản <strong>' . htmlspecialchars($account_name ?? 'Không xác định') . '</strong>.</p>';
        echo '<form method="post" style="margin-top: 20px;">';
        echo '<input type="hidden" name="id" value="' . htmlspecialchars($transaction_id) . '">';
        echo '<input type="hidden" name="step" value="delete">';
        echo '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
        echo '<label for="password" style="display:block; margin-bottom:8px;">Mật khẩu:</label>';
        echo '<input type="password" name="password" id="password" required style="width:100%; padding:8px; margin-bottom:16px; border:1px solid #ccc; border-radius:4px;">';
        echo '<button type="submit" style="background-color: #1976d2; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">Xác nhận xoá</button>';
        echo '<a href="dashboard.php" style="margin-left: 10px; padding: 10px 20px; background-color: #ccc; color: black; text-decoration: none; border-radius: 4px;">Huỷ bỏ</a>';
        echo '</form>';
        echo '</div>';
        exit;
    } elseif ($step === "delete") {
    $entered_password = $_POST["password"] ?? "";

    // Kiểm tra mật khẩu
    $user_query = "SELECT password FROM users WHERE id = $1";
    $user_result = pg_query_params($conn, $user_query, [$user_id]);
    $user_data = pg_fetch_assoc($user_result);

    if (!$user_data || !password_verify($entered_password, $user_data["password"])) {
        $error = "Mật khẩu không đúng. Vui lòng thử lại.";
        $step = "confirm"; // Quay lại form xác nhận
      } else {
        $step = "confirmed"; // Đặt cờ để thực hiện xoá
      }
    }
    if ($step === "confirmed") {
      pg_query($conn, "BEGIN");
      try {
        // Cập nhật số dư
        $delete_sql = "UPDATE transactions SET type = 3, original_type = $1, deleted_at = NOW() WHERE id = $2 AND user_id = $3";
        pg_query_params($conn, $delete_sql, [ $type, $transaction_id, $user_id ]);
        
        $adjustment = ($type == 1) ? -$amount : $amount;
        $update_balance_sql = "UPDATE accounts SET balance = balance + $1 WHERE id = $2 AND user_id = $3";
        pg_query_params($conn, $update_balance_sql, [ $adjustment, $account_id, $user_id ]);
        
        pg_query($conn, "COMMIT");
        echo "<div style='text-align:center; margin-top:50px; font-family:Arial;'>
                ✅ Giao dịch đã được xóa tạm thời.<br>
                Sẽ quay lại dashboard sau 3 giây...
              </div>";
        echo '<meta http-equiv="refresh" content="3;url=dashboard.php?deleted=1">';
        exit;
      } catch (Exception $e) {
        pg_query($conn, "ROLLBACK");
        echo "<p style='color:red;'>Lỗi khi xoá: " . htmlspecialchars($e->getMessage()) . "</p>";
        exit;
      }
    }
}
?>
    
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Xác nhận xóa giao dịch</title>
  <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Roboto', Arial, sans-serif;
      background-color: #f0f2f5;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
    }

    form {
      background-color: #fff;
      padding: 30px 40px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      max-width: 500px;
      width: 100%;
      text-align: left;
    }

    h2 {
      font-size: 22px;
      margin-bottom: 20px;
      color: #dc3545;
    }

    p {
      margin: 10px 0;
      font-size: 16px;
    }

    label {
      display: block;
      margin-top: 20px;
      font-weight: bold;
    }

    input[type="password"] {
      width: 100%;
      padding: 10px;
      margin-top: 8px;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 15px;
    }

    .actions {
      margin-top: 25px;
      display: flex;
      justify-content: space-between;
    }

    .actions button,
    .actions a {
      padding: 10px 20px;
      border: none;
      border-radius: 6px;
      font-weight: bold;
      text-decoration: none;
      color: white;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }
    .actions button {
      background-color: #dc3545;
    }
    .actions a {
      background-color: #6c757d;
    }
    button, a {
      padding: 10px 20px;
      border: none;
      border-radius: 6px;
      font-weight: bold;
      text-decoration: none;
      color: white;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }
    .actions button:hover {
      background-color: #c82333;
    }
    
    .actions a:hover {
      background-color: #5a6268;
    }
    button {
      background-color: #dc3545;
    }

    a {
      background-color: #6c757d;
    }

    button:hover {
      background-color: #c82333;
    }

    a:hover {
      background-color: #5a6268;
    }
      .overlay {
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(0,0,0,0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
      }
      .confirm-box {
        background: white;
        padding: 30px 40px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        text-align: center;
        max-width: 400px;
        font-family: 'Roboto', sans-serif;
      }
      .confirm-box h3 {
        color: #dc3545;
        margin-bottom: 20px;
      }
      .confirm-box p {
        margin: 10px 0;
        font-size: 16px;
      }
      .confirm-actions {
          display: flex;
          justify-content: space-between;
          margin-top: 20px;
        }
      .confirm-actions button,
        .confirm-actions a {
          padding: 10px 20px;
          border-radius: 6px;
          font-weight: bold;
          text-decoration: none;
          color: white;
          background-color: #dc3545;
          border: none;
          cursor: pointer;
        }
      .confirm-actions button {
        background-color: #dc3545;
      }
        .confirm-actions a {
          background-color: #6c757d;
        }
        .confirm-actions button:hover {
          background-color: #c82333;
        }
        .confirm-actions a:hover {
          background-color: #5a6268;
        }
      .form-box {
          background-color: #f8f9fa;
          padding: 20px;
          border-radius: 8px;
          box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
      .confirm-box {
          background-color: #fff3cd;
          border: 1px solid #ffeeba;
          padding: 20px;
          border-radius: 8px;
        }
        .confirm-box h3 {
          color: #856404;
        }
  </style>
</head>
    <body>
      <form method="post" action="">
        <div class="form-box">
          <h2>⚠️ Xác nhận xóa giao dịch</h2>
    
          <p><strong>Tài khoản:</strong> <?= htmlspecialchars($account_name) ?></p>
          <?php
            $type_label = match ($info['type']) {
                1 => 'Thu',
                2 => 'Chi',
                0 => 'Hệ thống',
                3 => 'Đã xoá',
                default => 'Không xác định'
            };
            ?>
            <p><strong>Loại:</strong> <?= $type_label ?></p>
          <p><strong>Số tiền:</strong> <?= number_format($info['amount'], 2) ?> VND</p>
          <?php
            $desc = trim($info['description'] ?? '');
            if (strpos($desc, 'Tạo tài khoản mới:') === 0) {
                $desc = 'Tạo khoản tiền mới';
            }
          ?>
          <p><strong>Mô tả:</strong> <?= htmlspecialchars($desc ?: 'Không có') ?></p>
    
          <?php if ($step === 'warning' && $new_balance < 0): ?>
            <div class="overlay">
              <div class="confirm-box">
                <h3>⚠️ Số dư sẽ bị âm nếu xoá giao dịch này</h3>
                <p>Số dư hiện tại: <?= number_format($current_balance, 0, ',', '.') ?> VND</p>
                <p>Số dư sau khi xoá: <?= number_format($new_balance, 0, ',', '.') ?> VND</p>
                <input type="hidden" name="step" value="confirm">
                <div class="confirm-actions">
                  <button type="submit">🗑️ Xóa giao dịch</button>
                  <a href="dashboard.php">← Quay lại</a>
                </div>
              </div>
            </div>
          <?php endif; ?>
    
          <?php if ($step === 'confirm'): ?>
            <?php if (isset($error)): ?>
              <p style="color:red;"><?= $error ?></p>
            <?php endif; ?>
            <label for="password">🔐 Nhập mật khẩu để xác nhận:</label>
            <input type="password" name="password" id="password" required>
            <input type="hidden" name="step" value="confirm">
            <div class="confirm-actions">
              <button type="submit">🗑️ Xóa giao dịch</button>
              <a href="dashboard.php">← Quay lại</a>
            </div>
          <?php elseif (!($step === 'warning' && $new_balance < 0)): ?>
            <input type="hidden" name="step" value="confirm">
            <div class="confirm-actions">
              <button type="submit">🗑️ Xóa giao dịch</button>
              <a href="dashboard.php">← Quay lại Dashboard</a>
            </div>
          <?php endif; ?>
        </div>
      </form>
    </body>
</html>

