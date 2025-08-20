<?php
include 'send_mail.php'; // File bạn đã gửi

$otp = rand(100000, 999999);
$success = sendOTP("your_other_email@gmail.com", $otp);

if ($success) {
    echo "Gửi OTP thành công!";
} else {
    echo "Không thể gửi OTP.";
}
