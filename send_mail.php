<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

function sendOTP($toEmail, $otp, $type = 'register') {
    $mail = new PHPMailer(true);

    try {
        // Cấu hình SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'phanthang08bn@gmail.com';
        $mail->Password = 'mxihfanfjmjlttlj';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->addAddress($toEmail);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->isHTML(true);

        // Nội dung email tùy theo loại
        if ($type === 'register') {
            $mail->setFrom('phanthang08bn@gmail.com', 'Email xác minh đăng ký tài khoản');
            $mail->Subject = 'Mã OTP đăng ký tài khoản';
            $mail->Body    = "Xin chào,<br><br>Mã OTP để hoàn tất đăng ký tài khoản của bạn là: <b>$otp</b>.<br>Mã có hiệu lực trong 10 phút.<br><br>Trân trọng.";
        } elseif ($type === 'reset') {
            $mail->setFrom('phanthang08bn@gmail.com', 'Email xác minh đặt lại mật khẩu');
            $mail->Subject = 'Mã OTP khôi phục mật khẩu';
            $mail->Body    = "Xin chào,<br><br>Mã OTP để đặt lại mật khẩu của bạn là: <b>$otp</b>.<br>Mã có hiệu lực trong 10 phút.<br><br>Trân trọng.";
        } else {
            $mail->Subject = 'Mã OTP hệ thống';
            $mail->Body    = "Mã OTP của bạn là: <b>$otp</b>.";
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        echo "Lỗi gửi email: " . $mail->ErrorInfo;
        return false;
    }
}

