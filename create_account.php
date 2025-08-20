<?php
session_start();
include "db.php";
define('MAX_BALANCE', 100000000);
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";

// Xử lý form
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error = "CSRF token không hợp lệ. Vui lòng thử lại.";
    }
    $name     = trim($_POST['name']);
    $rawBal   = $_POST['balance'] ?? '0';

    // 1. Sanitize số tiền: bỏ hết dấu phẩy và ký tự lạ
    $sanitized = preg_replace('/[^\d\.]/', '', $rawBal);
    if ($sanitized === '' || !is_numeric($sanitized)) {
        $error = "Số dư không hợp lệ. Vui lòng nhập số.";
    } else {
        $balance = floatval($sanitized);

        if ($balance < 0) {
            $error = "Số dư không được âm.";
        } elseif ($balance > MAX_BALANCE) {
            $error = "Số dư vượt quá giới hạn (tối đa " . number_format(MAX_BALANCE, 0, ',', '.') . " VND).";
        } elseif (empty($name)) {
            $error = "Vui lòng nhập tên tài khoản.";
        } else {
            // 2. Tạo tài khoản
            $insert = pg_query_params($conn,
                "INSERT INTO accounts (user_id, name, balance) VALUES ($1, $2, $3) RETURNING id",
                [$user_id, $name, $balance]
            );
            
            if ($insert && pg_num_rows($insert) === 1) {
                $row = pg_fetch_assoc($insert);
                $account_id = $row['id'];
            
                $now = date('Y-m-d H:i');
            
                // Giao dịch “thu” ban đầu
                $trans1 = pg_query_params($conn,
                      "INSERT INTO transactions (user_id, account_id, type, amount, description, remaining_balance, date)
                       VALUES ($1, $2, 1, $3, $4, $3, $5)",
                      [$user_id, $account_id, $balance, "Số dư ban đầu", $now]
                    );

                // Ghi chú hành động tạo tài khoản
                $trans2 = pg_query_params($conn,
                      "INSERT INTO transactions (user_id, account_id, type, amount, description, remaining_balance, date)
                       VALUES ($1, $2, 0, 0, $3, $4, $5)",
                      [$user_id, $account_id, "Tạo khoản tiền mới", $balance, $now]
                    );
            
                if ($trans1 && $trans2) {
                    // ✅ Chỉ chuyển hướng nếu giao dịch tạo tài khoản thành công
                    header("Location: dashboard.php");
                    exit();
                } else {
                    // ❌ Xử lý lỗi giao dịch
                    $pgError = pg_last_error($conn);
                    if (str_contains($pgError, 'numeric field overflow')) {
                        $error = "Giá trị số dư quá lớn. Vui lòng nhập số tiền nhỏ hơn (tối đa " . number_format(MAX_BALANCE, 0, ',', '.') . " VND).";
                    } else {
                        $error = "Không thể ghi giao dịch. Lỗi: " . htmlspecialchars($pgError);
                    }
                }
            } else {
                // ❌ Xử lý lỗi chèn account
                $pgError = pg_last_error($conn);
                if (str_contains($pgError, 'numeric field overflow')) {
                    $error = "Giá trị số dư quá lớn. Vui lòng nhập số tiền nhỏ hơn (tối đa " . number_format(MAX_BALANCE, 0, ',', '.') . " VND).";
                } else {
                    $error = "Không thể tạo tài khoản. Lỗi: " . htmlspecialchars($pgError);
                }
            }  
        }
    }
}
?>
    
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>➕ Tạo khoản tiền mới</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 480px;
            margin: 60px auto;
            background: #fff;
            border-radius: 14px;
            padding: 30px 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            margin-bottom: 25px;
        }
        h2::before { content: "➕ "; }
        .form-group { margin-bottom: 18px; }
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 6px;
        }
        input[type="text"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            margin-top: 10px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        .btn-add {
            background-color: #007bff;
            color: white;
        }
        .btn-add:hover {
            background-color: #0056b3;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 18px;
            color: #007bff;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .error {
            color: red;
            text-align: center;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Tạo khoản tiền mới</h2>

        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="post" id="createAccountForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="form-group">
                <label for="name">Tên khoản tiền:</label>
                <input type="text" id="name" name="name" maxlength="30" required>
            </div>

            <div class="form-group">
                <label for="balance">Số dư ban đầu:</label>
                <!-- Đổi về text để chèn dấu phẩy bằng JS -->
                <input
                    type="text"
                    id="balance"
                    name="balance"
                    inputmode="decimal"
                    maxlength="10"
                    title="Số dư tối đa: 99,999,999 VND"
                    placeholder="Tối đa 99,999,999 VND"
                    value="<?= isset($_POST['balance']) ? htmlspecialchars($_POST['balance']) : '0' ?>"
                    required
                >
            </div>
            <?php if (!empty($error)): ?>
              <p class="error"><?= $error ?></p>
            <?php endif; ?>
            <button type="submit" class="btn-add">💾 Tạo tài khoản</button>
            <small id="balanceWarning" class="error" style="display:none;"></small>
        </form>

        <a class="back-link" href="dashboard.php">← Quay lại Dashboard</a>
    </div>

    <script>
function formatWithCommas(value) {
    const parts = value.split('.');
    parts[0] = parts[0]
        .replace(/^0+(?=\d)|\D/g, '')           // Bỏ số 0 dư và ký tự không hợp lệ
        .replace(/\B(?=(\d{3})+(?!\d))/g, ','); // Chèn dấu phẩy
    return parts.join('.');
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('createAccountForm');
    const inp = document.getElementById('balance');
    const submitBtn = document.querySelector('.btn-add');
    const warning = document.getElementById('balanceWarning');

    // 🧠 Tự động thêm dấu phẩy khi nhập
    inp.addEventListener('input', () => {
        const pos = inp.selectionStart;
        let raw = inp.value.replace(/,/g, '');

        if (raw === '' || raw === '.') {
            inp.value = raw;
            return;
        }

        const [intP, decP] = raw.split('.');
        let formatted = formatWithCommas(intP);
        if (decP !== undefined) {
            formatted += '.' + decP.replace(/\D/g, '');
        }

        inp.value = formatted;
        const newPos = pos + (formatted.length - raw.length);
        inp.setSelectionRange(newPos, newPos);
    });

    // ✅ Kiểm tra giới hạn khi submit
    form.addEventListener('submit', (e) => {
        const rawValue = inp.value.replace(/,/g, '');
        const number = parseFloat(rawValue);

        if (number > 99999999.99) {
            e.preventDefault();
            inp.style.borderColor = 'red';
            warning.textContent = '⚠️ Số dư quá lớn. Vui lòng nhập ≤ 99.999.999 VND.';
            warning.style.display = 'block';
            inp.focus();
        } else {
            inp.style.borderColor = '#ccc';
            warning.style.display = 'none';
            warning.textContent = '';
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Đang xử lý...';
        }
    });
});
</script>   
</body>
</html>
