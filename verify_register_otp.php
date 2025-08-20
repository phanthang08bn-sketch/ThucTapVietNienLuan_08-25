<?php
session_start();
include "db.php"; // Kết nối PostgreSQL

$error = $success = "";

// Kiểm tra dữ liệu đăng ký tạm thời
if (!isset($_SESSION["pending_user"]) || !isset($_SESSION["otp"])) {
    header("Location: register.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $entered_otp = trim($_POST["otp"]);
    $stored_otp  = $_SESSION["otp"];

    if ($entered_otp == $stored_otp) {
        $user = $_SESSION["pending_user"];

        // Lưu tài khoản vào DB
        $res = pg_query_params($conn,
            "INSERT INTO users (username,password,fullname,birthyear,email,avatar)
             VALUES ($1,$2,$3,$4,$5,$6)",
            [
                $user['username'],
                $user['password'],
                $user['fullname'],
                $user['birthyear'],
                $user['email'],
                $user['avatar']
            ]
        );

        if ($res) {
            // Xoá session tạm
            unset($_SESSION["pending_user"]);
            unset($_SESSION["otp"]);

            $success = "✅ Tài khoản đã được tạo thành công!";
        } else {
            $error = "❌ Lỗi khi lưu tài khoản. Vui lòng thử lại.";
        }
    } else {
        $error = "❌ Mã OTP không chính xác.";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Xác nhận đăng ký</title>
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background: #f3f4f6;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .otp-box {
            background: white;
            padding: 30px 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
            box-sizing: border-box;
            text-align: center;
        }

        h2 {
            margin-bottom: 20px;
            color: #111827;
        }

        input[type="text"] {
            width: 100%;
            padding: 10px;
            margin-top: 10px;
            margin-bottom: 20px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 16px;
        }

        button {
            padding: 10px 20px;
            background-color: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background-color: #1e40af;
        }

        .error {
            color: #dc2626;
            margin-bottom: 15px;
        }

        .success {
            color: #16a34a;
            margin-bottom: 15px;
        }

        .link {
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="otp-box">
        <h2>Xác nhận mã OTP đăng ký</h2>
        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?= $success ?></div>
            <div class="link"><a href="login.php">👉 Đăng nhập ngay</a></div>
        <?php else: ?>
            <form method="POST">
                <label for="otp">Nhập mã OTP đã gửi đến email:</label>
                <input type="text" name="otp" id="otp" placeholder="Nhập mã OTP" required>
                <button type="submit">Xác nhận</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
