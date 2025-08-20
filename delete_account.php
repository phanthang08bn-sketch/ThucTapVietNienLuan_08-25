<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['account_id'])) {
    $account_id = intval($_GET['account_id']);
    $user_id = $_SESSION['user_id'];

    // Xoá tất cả giao dịch liên quan đến tài khoản này
    pg_query_params($conn,
        "DELETE FROM transactions WHERE account_id = $1",
        [$account_id]
    );

    // Xoá tài khoản khỏi bảng accounts
    pg_query_params($conn,
        "DELETE FROM accounts WHERE id = $1 AND user_id = $2",
        [$account_id, $user_id]
    );
}

header("Location: dashboard.php");
exit();
?>
