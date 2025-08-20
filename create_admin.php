<?php
include "db.php"; // Kết nối PostgreSQL

$username = 'admin';
$password = password_hash('admin123', PASSWORD_DEFAULT); // Băm mật khẩu
$avatar_path = 'avt_ad.png'; // Đường dẫn ảnh đại diện
$email = 'admin@example.com'; // Email hợp lệ
$role = 'admin';

// Kiểm tra xem tài khoản đã tồn tại chưa
$check_query = "SELECT id FROM users WHERE username = $1";
$check_result = pg_query_params($conn, $check_query, [$username]);

if (pg_fetch_assoc($check_result)) {
    echo "⚠️ Tài khoản admin đã tồn tại!";
    exit;
}

// Thêm tài khoản admin mới
$insert_query = "INSERT INTO users (username, password, avatar, email, role) VALUES ($1, $2, $3, $4, $5)";
$insert_result = pg_query_params($conn, $insert_query, [$username, $password, $avatar_path, $email, $role]);

if ($insert_result) {
    echo "✅ Tạo tài khoản admin thành công!";
} else {
    echo "❌ Lỗi khi tạo tài khoản.";
}
?>
