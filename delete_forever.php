<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$transaction_id = $_POST['transaction_id'] ?? null;

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
    $check_res = pg_query_params($conn, $check_sql, [$transaction_id, $user_id]);

    if (pg_num_rows($check_res) !== 1) {
        throw new Exception("Giao dịch không tồn tại hoặc không thuộc về bạn.");
    }

    // Xóa giao dịch
    $delete_sql = "DELETE FROM transactions WHERE id = $1";
    $delete_res = pg_query_params($conn, $delete_sql, [$transaction_id]);

    if (!$delete_res) {
        throw new Exception("Không thể xóa giao dịch.");
    }

    pg_query($conn, 'COMMIT');
    $_SESSION['restored'] = "✅ Giao dịch đã được xóa vĩnh viễn!";
} catch (Exception $e) {
    pg_query($conn, 'ROLLBACK');
    $_SESSION['restored'] = "❌ Lỗi xóa: " . $e->getMessage();
}

header("Location: dashboard.php");
exit();
?>
