<?php
session_start();
include "db.php"; // file kết nối PostgreSQL

$error = $success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);

    // Kiểm tra email có tồn tại không
    $result = pg_query_params($conn,
        "SELECT id FROM users WHERE email = $1",
        [$email]
    );

    if (pg_num_rows($result) == 1) {
        $otp = rand(100000, 999999);
        $expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));

        // Cập nhật mã OTP và thời hạn
        $update = pg_query_params($conn,
            "UPDATE users SET reset_token = $1, reset_token_expiry = $2 WHERE email = $3",
            [$otp, $expiry, $email]
        );

        include "send_mail.php";
        if (sendOTP($email, $otp, 'reset')) {
            $_SESSION["reset_email"] = $email;
            header("Location: verify_otp.php");
            exit();
        } else {
            $error = "Không thể gửi mã OTP. Vui lòng thử lại.";
        }
    } else {
        $error = "Email không tồn tại trong hệ thống.";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quên mật khẩu</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background: #f3f4f6;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .container {
            background: #ffffff;
            padding: 30px 35px;
            border-radius: 12px;
            box-shadow: 0 0 14px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 400px;
        }

        h2 {
            text-align: center;
            color: #111827;
            margin-bottom: 20px;
        }

        label {
            font-weight: 500;
            margin-bottom: 8px;
            display: block;
        }

        input[type="email"] {
            width: 100%;
            padding: 12px 14px;
            font-size: 15px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            margin-bottom: 18px;
        }

        button {
            width: 100%;
            padding: 12px;
            background-color: #2563eb;
            color: white;
            font-size: 16px;
            font-weight: bold;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s ease-in-out;
        }

        button:hover {
            background-color: #1d4ed8;
        }

        .error, .success {
            text-align: center;
            margin-bottom: 16px;
            padding: 10px;
            border-radius: 8px;
            font-size: 14px;
        }

        .error {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .success {
            background-color: #d1fae5;
            color: #065f46;
        }
        .back-button {
            display: block;
            text-align: center;
            margin-top: 12px;
            text-decoration: none;
            color: #2563eb;
            font-weight: 500;
            transition: color 0.2s ease-in-out;
        }

        .back-button:hover {
            color: #1d4ed8;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔐 Quên mật khẩu</h2>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label for="email">Nhập email của bạn:</label>
            <input type="email" name="email" id="email" required placeholder="example@gmail.com">
            <button type="submit">Gửi mã xác nhận</button>
            <a href="login.php" class="back-button">← Quay lại đăng nhập</a>
        </form>
    </div>
</body>
</html>
