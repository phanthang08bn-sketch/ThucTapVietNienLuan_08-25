<?php
session_start();
include "db.php";
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id    = $_SESSION['user_id'];
$account_id = filter_input(INPUT_GET, 'account_id', FILTER_VALIDATE_INT);
if (!$account_id) {
    echo "ID tài khoản không hợp lệ.";
    exit();
}

// 🔹 Lấy thông tin tài khoản
$sql    = "SELECT * FROM accounts WHERE id = $1 AND user_id = $2";
$result = pg_query_params($conn, $sql, [ $account_id, $user_id ]);
$account = pg_fetch_assoc($result);

if (! $account) {
    echo "Tài khoản không tồn tại.";
    exit();
}

$success = "";
$error   = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $input_password = $_POST['confirm_password'] ?? '';
    $sql_balance = "SELECT balance FROM accounts WHERE id = $1 AND user_id = $2";
    $res_balance = pg_query_params($conn, $sql_balance, [$account_id, $user_id]);
    $row_balance = pg_fetch_assoc($res_balance);
    $current_balance = $row_balance['balance'];

    // Kiểm tra mật khẩu trước
    if (empty($input_password)) {
        $error = "⚠️ Vui lòng nhập mật khẩu.";
    } else {
        $sql = "SELECT password FROM users WHERE id = $1";
        $res = pg_query_params($conn, $sql, [ $user_id ]);
        $user = pg_fetch_assoc($res);

        if (! $user || !password_verify($input_password, $user['password'])) {
            $error = "❌ Mật khẩu không đúng.";
        } else {
            // ✅ Trường hợp xóa khoản tiền
            if (isset($_POST['delete_account']) && $_POST['delete_account'] === 'yes') {
                pg_query($conn, 'BEGIN');
                try {
                    $res_log = pg_query_params($conn,
                        "INSERT INTO transactions (user_id, account_id, type, description, amount, date, created_at, remaining_balance)
                         VALUES ($1, $2, $3, $4, $5, $6, NOW(), $7)",
                        [ $user_id, $account_id, 3, '🗑️ Xóa khoản tiền', 0, date('Y-m-d H:i:s'), $current_balance ]
                    );
                    
                    if (!$res_log) {
                        throw new Exception("Không thể ghi lịch sử xóa khoản tiền.");
                    }
                    pg_query_params($conn,
                        "DELETE FROM transactions WHERE account_id = $1 AND user_id = $2",
                        [ $account_id, $user_id ]
                    );
                    pg_query_params($conn,
                        "DELETE FROM accounts WHERE id = $1 AND user_id = $2",
                        [ $account_id, $user_id ]
                    );
                    pg_query($conn, 'COMMIT');
                    header("Location: dashboard.php?deleted=1");
                    exit();
                } catch (Exception $e) {
                    pg_query($conn, 'ROLLBACK');
                    $error = "❌ Lỗi xoá: " . $e->getMessage() . " | DB: " . pg_last_error($conn);
                }
            }

            // ✅ Trường hợp đổi tên khoản tiền
            elseif (isset($_POST['action']) && $_POST['action'] === 'rename') {
                $new_name = trim($_POST['new_name'] ?? '');
            
                if ($new_name === '') {
                    $error = "⚠️ Vui lòng nhập tên khoản tiền.";
                } elseif ($new_name === $account['name']) {
                    $error = "⚠️ Tên khoản tiền mới không được trùng với tên hiện tại.";
                } else {
                    pg_query($conn, 'BEGIN');
                    try {
                        $prefix = 'Đổi tên thành: ';
                        $desc = $prefix . $new_name;
                        
                        // Giới hạn độ dài tối đa 30 ký tự
                        if (mb_strlen($desc) > 30) {
                            $desc = mb_substr($desc, 0, 30);
                        }
                        // Ghi lịch sử giao dịch
                        $res1 = pg_query_params($conn,
                            "INSERT INTO transactions (user_id, account_id, type, description, amount, date, created_at, remaining_balance)
                             VALUES ($1, $2, $3, $4, $5, $6, NOW(), $7)",
                            [ $user_id, $account_id, 2, $desc, 0, date('Y-m-d H:i:s'), $current_balance ]
                        );

                        if (!$res1) {
                            throw new Exception("Không thể ghi lịch sử giao dịch.");
                        }
            
                        // Cập nhật tên tài khoản
                        $res2 = pg_query_params($conn,
                            "UPDATE accounts SET name = $1 WHERE id = $2 AND user_id = $3",
                            [ $new_name, $account_id, $user_id ]
                        );
            
                        if (!$res2) {
                            throw new Exception("Không thể cập nhật tên tài khoản.");
                        }
            
                        pg_query($conn, 'COMMIT');
                        $account['name'] = $new_name;
                        header("Location: dashboard.php?renamed=1");
                        exit();
            
                    } catch (Exception $e) {
                        pg_query($conn, 'ROLLBACK');
                        $error = "❌ Lỗi cập nhật: " . htmlspecialchars($e->getMessage());
                    }
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
  <title>Sửa khoản tiền</title>
  <style>
      @media (max-width: 480px) {
          .form-control,
          .container > div {
            width: 100%;
            margin-bottom: 14px;
          }
        
          .flatpickr-wrapper {
            display: block;
            width: 100%;
            margin-bottom: 10px;
          }
        
          #transaction-time {
            display: block;
            width: 100%;
            margin-bottom: 10px;
          }
        }

    body {
      font-family: Arial, sans-serif;
      background: #f2f2f2;
      margin: 0;
      padding: 0;
    }
    .container {
      max-width: 560px;
      margin: 60px auto;
      padding: 30px 24px;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    h2 {
      text-align: center;
      margin-bottom: 26px;
    }
    label {
      display: block;
      font-weight: bold;
      margin-bottom: 6px;
      font-size: 15px;
    }
    .form-control {
      width: 100%;
      padding: 10px 12px;
      font-size: 16px;
      border: 1px solid #ccc;
      border-radius: 6px;
      box-sizing: border-box;
      margin-bottom: 18px;
    }
    button.form-control {
      background-color: #007BFF;
      color: white;
      border: none;
      cursor: pointer;
    }
    button.form-control:hover {
      background-color: #0056b3;
    }
    .danger {
      background-color: #dc3545;
    }
    .danger:hover {
      background-color: #b02a37;
    }
    .success {
      color: green;
      text-align: center;
      margin-bottom: 16px;
    }
    .error {
      color: red;
      text-align: center;
      margin-bottom: 16px;
    }
    .back {
      display: block;
      text-align: center;
      margin-top: 22px;
      color: #007BFF;
      text-decoration: none;
    }
    .back:hover {
      text-decoration: underline;
    }
    .flatpickr-wrapper {
      position: relative;
    }
    
    .calendar-btn {
      position: absolute;
      top: 6px;
      right: 10px;
      background: none;
      border: none;
      font-size: 20px;
      color: #333;
      cursor: pointer;
    }
    label {
      font-weight: bold;
      margin-bottom: 6px;
      display: inline-block;
    }
  </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
    <body>
      <div class="container">
        <?php
            if (isset($_GET['renamed'])) {
                echo "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                    ✅ Đã đổi tên khoản tiền thành công.
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                  </div>";
            }
        ?>
        <h2>✏️ Đổi tên khoản tiền</h2>
    
        <form method="post" id="balanceForm">
          <label>Tên khoản tiền:</label>
          <input type="text" name="name" id="accountName" maxlength="30"
                 value="<?= htmlspecialchars($account['name']) ?>"
                 required class="form-control">
    
          <label>Số dư hiện tại:</label>
          <input type="text" readonly
                 value="<?= number_format($account['balance'], 0, ',', '.') ?> VND"
                 class="form-control">
    
          <input type="hidden" name="action" value="rename">
          <button type="submit" class="form-control">💾 Lưu thay đổi</button>
        </form>
    
        <form method="post" id="deleteForm">
          <input type="hidden" name="delete_account" value="yes">
          <button type="submit" class="form-control danger">🗑️ Xóa khoản tiền</button>
        </form>
          
        <?php if (!empty($success)): ?>
          <div class="success"><?= $success ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
          <div class="error"><?= $error ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['renamed'])): ?>
          <div class="success">✅ Đã đổi tên khoản tiền thành công!</div>
        <?php endif; ?>
    
        <a href="dashboard.php" class="back">← Quay lại Dashboard</a>
      </div>
    
      <!-- Modal xác nhận mật khẩu -->
      <div id="passwordModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%;
           background:rgba(0,0,0,0.5); z-index:999; justify-content:center; align-items:center;">
        <div style="background:#fff; padding:24px; border-radius:8px; max-width:400px; width:90%;">
          <h3>🔐 Xác nhận mật khẩu</h3>
          <p>Vui lòng nhập mật khẩu để tiếp tục:</p>
          <input type="password" id="modalPassword" class="form-control" required>
          <div style="margin-top:12px; display:flex; gap:12px;">
            <button onclick="submitAction()" class="form-control">✅ Xác nhận</button>
            <button onclick="closeModal()" class="form-control danger">❌ Hủy</button>
          </div>
        </div>
      </div>
        <form id="hiddenRenameForm" method="post" action="edit_account_balance.php?account_id=<?= $account_id ?>" style="display:none;">
          <input type="hidden" name="confirm_password" id="hiddenPassword">
          <input type="hidden" name="new_name" id="hiddenNewName">
          <input type="hidden" name="action" value="rename">
        </form>
        <form id="hiddenDeleteForm" method="post" action="edit_account_balance.php?account_id=<?= $account_id ?>" style="display:none;">
          <input type="hidden" name="confirm_password" id="hiddenDeletePassword">
          <input type="hidden" name="delete_account" value="yes">
        </form>

      <script>
        let actionType = "";
    
        function openModal(type) {
          actionType = type;
          document.getElementById("passwordModal").style.display = "flex";
          document.getElementById("modalPassword").value = "";
          document.getElementById("modalPassword").focus();
        }
    
        function closeModal() {
          document.getElementById("passwordModal").style.display = "none";
          const submitBtn = document.querySelector('#balanceForm button[type="submit"]');
          if (submitBtn.disabled) {
            submitBtn.disabled = false;
            submitBtn.textContent = "💾 Lưu thay đổi";
          }
        }
        function submitAction() {
          const password = document.getElementById("modalPassword").value;
        
          if (actionType === "rename") {
            const newName = document.getElementById("accountName").value;
            document.getElementById("hiddenPassword").value = password;
            document.getElementById("hiddenNewName").value = newName;
            document.getElementById("hiddenRenameForm").submit();
          } else if (actionType === "delete") {
            document.getElementById("hiddenDeletePassword").value = password;
            document.getElementById("hiddenDeleteForm").submit();
          }
        }

        document.addEventListener("DOMContentLoaded", function() {
          document.getElementById("balanceForm").addEventListener("submit", function(e) {
            e.preventDefault();
            openModal("rename");
          });
            if (actionType === "edit") {
                const newName = document.getElementById("accountName").value;
                document.getElementById("hiddenPassword").value = password;
                document.getElementById("hiddenNewName").value = newName;
                document.getElementById("hiddenRenameForm").submit();
              } else if (actionType === "delete") {
                document.getElementById("hiddenDeletePassword").value = password;
                document.getElementById("hiddenDeleteForm").submit();
              }
          document.getElementById("deleteForm").addEventListener("submit", function(e) {
            e.preventDefault();
            openModal("delete");
          });
        });
      </script>
    </body>
</html>
