<?php
session_start();
include "db.php";
include "validation.php";

$errors = [];
$old    = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // 1. Sanitize
    $old['username'] = sanitize($_POST['username'] ?? '');
    $old['password'] = $_POST['password'] ?? '';

    // 2. Validate
    if (! validUsername($old['username'])) {
        $errors['username'] = 'Tên đăng nhập 1–50 ký tự A–Z, a–z, 0–9';
    }
    if (empty($old['password'])) {
        $errors['password'] = 'Mật khẩu không được để trống';
    }

    // 3. Nếu không có lỗi, kiểm tra DB
    if (empty($errors)) {
        $result = pg_query_params(
            $conn,
            "SELECT id, password FROM users WHERE username = $1",
            [$old['username']]
        );

        if ($result && pg_num_rows($result) === 1) {
            $user = pg_fetch_assoc($result);
            if (password_verify($old['password'], $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                header("Location: dashboard.php");
                exit();
            }
        }

        // Nếu không match hoặc query lỗi
        $errors['general'] = 'Tên đăng nhập hoặc mật khẩu không đúng';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng nhập</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="login-container">
        <h2>Đăng nhập hệ thống</h2>

        <!-- Lỗi chung -->
        <?php if (isset($errors['general'])): ?>
            <p class="error"><?= htmlspecialchars($errors['general']) ?></p>
        <?php endif; ?>

        <form method="post">
            <!-- Username -->
            <input
              type="text"
              name="username"
              placeholder="Tên đăng nhập"
              value="<?= htmlspecialchars($old['username'] ?? '') ?>"
              required
            />
            <span class="error"><?= $errors['username'] ?? '' ?></span><br>

            <!-- Password -->
            <input
              type="password"
              name="password"
              placeholder="Mật khẩu"
              required
            />
            <span class="error"><?= $errors['password'] ?? '' ?></span><br>

            <button type="submit">Đăng nhập</button>
        </form>

        <p>
            Chưa có tài khoản? <a href="register.php">Đăng ký</a><br>
            <a href="forgot_password.php">Quên mật khẩu?</a>
        </p>
    </div>
</body>
</html>
