<?php
session_start();
include "db.php"; // kết nối PostgreSQL

$error = $success = "";

// Kiểm tra có email lưu trong session không
if (!isset($_SESSION["reset_email"])) {
    header("Location: forgot_password.php");
    exit();
}

$email = $_SESSION["reset_email"];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $entered_otp = trim($_POST["otp"]);

    // Lấy OTP và thời gian hết hạn từ CSDL
    $result = pg_query_params($conn,
        "SELECT reset_token, reset_token_expiry FROM users WHERE email = $1",
        [$email]
    );

    if (pg_num_rows($result) == 1) {
        $row = pg_fetch_assoc($result);
        $stored_otp = $row["reset_token"];
        $expiry_time = $row["reset_token_expiry"];

        if ($entered_otp == $stored_otp) {
            if (strtotime($expiry_time) >= time()) {
                $_SESSION["otp_verified"] = true;
                header("Location: reset_password.php");
                exit();
            } else {
                $error = "Mã OTP đã hết hạn. Vui lòng thử lại.";
            }
        } else {
            $error = "Mã OTP không chính xác.";
        }
    } else {
        $error = "Không tìm thấy thông tin OTP.";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Xác minh OTP</title>
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
    </style>
</head>
<body>
    <div class="otp-box">
        <h2>Xác minh mã OTP</h2>
        <?php if ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>
        <form method="POST">
            <label for="otp">Nhập mã OTP đã gửi đến email:</label>
            <input type="text" name="otp" id="otp" placeholder="Nhập mã OTP" required>
            <button type="submit">Xác minh</button>
        </form>
    </div>
</body>
</html>
