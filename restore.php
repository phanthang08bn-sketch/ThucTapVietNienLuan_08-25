<?php
session_start();
include "db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['restored'] = "❌ CSRF token không hợp lệ.";
        header("Location: trash.php");
        exit();
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

$user_id = $_SESSION['user_id'];
$transaction_id = $_POST['transaction_id'] ?? null;
$csrf_token = $_POST['csrf_token'] ?? '';

if (!$transaction_id || !is_numeric($transaction_id)) {
  $_SESSION['restored'] = "❌ Giao dịch không hợp lệ.";
  header("Location: trash.php");
  exit();
}

// Bắt đầu giao dịch
pg_query($conn, 'BEGIN');

try {
  // Kiểm tra giao dịch có tồn tại và thuộc về user
  $check_sql = "SELECT * FROM transactions WHERE id = $1 AND user_id = $2 AND type = 3";
  $check_res = pg_query_params($conn, $check_sql, [ $transaction_id, $user_id ]);

  if (pg_num_rows($check_res) !== 1) {
    throw new Exception("Giao dịch không tồn tại hoặc không thuộc về bạn.");
  }

  // Khôi phục: cập nhật type về 1 hoặc 2
  $original_type = pg_fetch_result($check_res, 0, 'original_type');
    if (!in_array($original_type, [1, 2])) {
      throw new Exception("Loại giao dịch gốc không hợp lệ.");
    }
  $restore_sql = "UPDATE transactions SET type = $1, deleted_at = NULL WHERE id = $2";
  $restore_res = pg_query_params($conn, $restore_sql, [ $original_type, $transaction_id ]);

  
  $info_sql = "SELECT amount, account_id, type FROM transactions WHERE id = $1";
  $info_res = pg_query_params($conn, $info_sql, [$transaction_id]);
  $info = pg_fetch_assoc($info_res);
  $amount = floatval($info['amount']);
    if ($amount <= 0) {
      throw new Exception("Số tiền không hợp lệ.");
    }
  // Nếu là thu nhập (type = 1) thì cộng, nếu là chi tiêu (type = 2) thì trừ
  $adjustment = ($original_type == 2) ? -$info['amount'] : $info['amount'];
    if (!$info['account_id']) {
      throw new Exception("Giao dịch không có tài khoản hợp lệ.");
    }

    $balance_check_sql = "SELECT balance FROM accounts WHERE id = $1 AND user_id = $2";
    $balance_check_res = pg_query_params($conn, $balance_check_sql, [$info['account_id'], $user_id]);
       if (pg_num_rows($balance_check_res) !== 1) {
        throw new Exception("Không tìm thấy tài khoản để cập nhật số dư.");
    }
    $current_balance = pg_fetch_result($balance_check_res, 0, 0);
    $new_balance = $current_balance + $adjustment;

    if ($new_balance < 0) {
        throw new Exception("Khôi phục sẽ khiến số dư âm.");
    }

  $update_balance_sql = "UPDATE accounts SET balance = balance + $1 WHERE id = $2";
  pg_query_params($conn, $update_balance_sql, [$adjustment, $info['account_id']]);
  
  $clear_sql = "UPDATE transactions SET original_type = NULL WHERE id = $1";
  pg_query_params($conn, $clear_sql, [ $transaction_id ]);

  if (!$restore_res) {
    throw new Exception("Không thể khôi phục giao dịch.");
  }

  pg_query($conn, 'COMMIT');
  $_SESSION['restored'] = "✅ Giao dịch đã được khôi phục!";
} catch (Exception $e) {
  pg_query($conn, 'ROLLBACK');
  $_SESSION['restored'] = "❌ Lỗi khôi phục: " . $e->getMessage();
}

header("Location: dashboard.php");
exit();
?>
