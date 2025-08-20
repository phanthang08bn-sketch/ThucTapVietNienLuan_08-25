<?php
// validation.php

// Loại bỏ khoảng trắng đầu/cuối và xóa thẻ HTML
function sanitize(string $str): string {
    return trim(strip_tags($str));
}

// 1. Username: 1–50 ký tự, chỉ chữ hoa/thường và số, không khoảng trắng
function validUsername(string $u): bool {
    return preg_match('/^[A-Za-z0-9]{1,50}$/', $u);
}

// 2. Password: 6+ ký tự, ít nhất 1 hoa, 1 số, 1 ký tự đặc biệt
function validPassword(string $pwd): bool {
    return preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*\W).{6,}$/', $pwd);
}

// 3. Full name: chỉ chữ (có dấu) và khoảng trắng
function validFullname(string $name): bool {
    return preg_match('/^[A-Za-zÀ-Ỵà-ỵ\s]+$/u', $name);
}

// 4. Email
function validEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// 5. Birthyear: integer trong khoảng [1900..năm hiện tại]
function validBirthyear(int $y): bool {
    $now = intval(date('Y'));
    return ($y >= 1900 && $y <= $now);
}

// 6. Số tiền (amount): số thực ≥ 0, tối đa 2 chữ số thập phân
function validAmount(string $amt): bool {
    return preg_match('/^\d+(\.\d{1,2})?$/', $amt) && floatval($amt) >= 0;
}

// 7. Mô tả giao dịch: tối đa 255 ký tự
function validDescription(string $desc): bool {
    return strlen($desc) <= 255;
}

// 8. Ngày: định dạng YYYY-MM-DD, hợp lệ trên calendar
function validDate(string $d): bool {
    $parts = explode('-', $d);
    return count($parts)===3 && checkdate(intval($parts[1]), intval($parts[2]), intval($parts[0]));
}

// 9. Category ID: integer dương
function validCategoryId($id): bool {
    return filter_var($id, FILTER_VALIDATE_INT, ["options"=>["min_range"=>1]]) !== false;
}
