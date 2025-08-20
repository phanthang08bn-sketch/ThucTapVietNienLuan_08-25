<?php
session_start();
include "db.php";

// 1. Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

// 2. Kiểm tra phương thức POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  $_SESSION['message'] = "❌ Phương thức không hợp lệ.";
  header("Location: dashboard.php");
  exit();
}

// 3. Kiểm tra CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
  $_SESSION['message'] = "❌ CSRF token không hợp lệ.";
  header("Location: dashboard.php");
  exit();
}

// 4. Kiểm tra transaction_id
$transaction_id = $_POST['transaction_id'] ?? null;
if (!$transaction_id || !ctype_digit($transaction_id)) {
  $_SESSION['message'] = "❌ Giao dịch không hợp lệ.";
  header("Location: dashboard.php");
  exit();
}

$user_id = $_SESSION['user_id'];

// 5. Kiểm tra giao dịch có tồn tại và thuộc về user
$check_sql = "SELECT * FROM transactions WHERE id = $1 AND user_id = $2 AND type = 3";
$check_res = pg_query_params($conn, $check_sql, [$transaction_id, $user_id]);

if (pg_num_rows($check_res) !== 1) {
  $_SESSION['message'] = "❌ Không tìm thấy giao dịch cần ẩn.";
  header("Location: dashboard.php");
  exit();
}

// 6. Cập nhật is_hidden = TRUE
$hide_sql = "UPDATE transactions SET is_hidden = TRUE WHERE id = $1";
$hide_res = pg_query_params($conn, $hide_sql, [$transaction_id]);

if (!$hide_res) {
  $_SESSION['message'] = "❌ Không thể ẩn giao dịch.";
} else {
  $_SESSION['message'] = "✅ Giao dịch đã được ẩn khỏi lịch sử.";
}

// 7. Quay về dashboard
header("Location: dashboard.php");
exit();
?>
