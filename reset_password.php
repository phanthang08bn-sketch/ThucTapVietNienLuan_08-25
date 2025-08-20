<?php
session_start();
include "db.php";

$error = $success = "";

// Kiểm tra đã xác minh OTP chưa
if (!isset($_SESSION["reset_email"]) || !isset($_SESSION["otp_verified"])) {
    header("Location: forgot_password.php");
    exit();
}

$email = $_SESSION["reset_email"];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password     = $_POST["new_password"];
    $confirm_password = $_POST["confirm_password"];

    // Regex: 6+ ký tự, 1 chữ hoa, 1 số, 1 đặc biệt
    $pattern = '/^(?=.*[A-Z])(?=.*\d)(?=.*\W).{6,}$/';

    if (!preg_match($pattern, $new_password)) {
        $error = "Mật khẩu phải có ít nhất 6 ký tự, bao gồm 1 chữ hoa, 1 chữ số và 1 ký tự đặc biệt.";
    }
    elseif ($new_password !== $confirm_password) {
        $error = "Mật khẩu xác nhận không khớp.";
    }
    else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $update = pg_query_params($conn,
            "UPDATE users
             SET password = $1, reset_token = NULL, reset_token_expiry = NULL
             WHERE email = $2",
            [$hashed_password, $email]
        );

        if ($update) {
            unset($_SESSION["reset_email"], $_SESSION["otp_verified"]);
            $success = "Đặt lại mật khẩu thành công. <a href='login.php'>Đăng nhập</a>";
        } else {
            $error = "Có lỗi xảy ra khi cập nhật mật khẩu.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đặt lại mật khẩu</title>
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
        
        input[type="password"] {
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
<div class="reset-container">
    <h2>Đặt lại mật khẩu</h2>
    <?php if ($error): ?>
        <p class="message error"><?= $error ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p class="message success"><?= $success ?></p>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST">
      <!-- Mật khẩu mới -->
      <input
        type="password"
        name="new_password"
        id="new_password"
        placeholder="Mật khẩu (6+ ký tự, 1 hoa, 1 số, 1 đặc biệt)"
        required
        pattern="(?=.*[A-Z])(?=.*\d)(?=.*\W).{6,}"
        title="Ít nhất 6 ký tự, gồm 1 chữ hoa, 1 số và 1 ký tự đặc biệt."
      >
    
      <!-- Xác nhận mật khẩu -->
      <input
        type="password"
        name="confirm_password"
        id="confirm_password"
        placeholder="Xác nhận mật khẩu"
        required
      >
    
      <button type="submit">Đổi mật khẩu</button>
    </form>

    <?php endif; ?>
</div>
</body>
</html>
