<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $message = trim($_POST['message']);
    $image_path = null;

    if (!empty($_FILES['image']['name'])) {
        $upload_dir = "uploads/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $filename = basename($_FILES["image"]["name"]);
        $target_file = $upload_dir . time() . "_" . $filename;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $image_path = $target_file;
        }
    }

    if (!empty($message)) {
        $sql = "INSERT INTO feedbacks (user_id, message, image, created_at) VALUES ($1, $2, $3, NOW())";
        $params = [$user_id, $message, $image_path];
        $result = pg_query_params($conn, $sql, $params);
    
        if ($result) {
            $success = "✅ Cảm ơn bạn đã gửi phản hồi!";
        } else {
            $error = "❌ Có lỗi khi lưu phản hồi!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>📩 Gửi phản hồi</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body, html {
            height: 100%;
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #f4f6f9;
        }

        .wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
        }

        .container {
            background-color: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 500px;
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        textarea {
            width: 100%;
            height: 150px;
            padding: 12px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 8px;
            resize: vertical;
        }

        input[type="file"] {
            margin-top: 12px;
        }

        button {
            width: 100%;
            padding: 12px;
            margin-top: 20px;
            background-color: #28a745;
            border: none;
            color: white;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
        }

        button:hover {
            background-color: #218838;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            text-decoration: none;
            color: #007bff;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .message {
            text-align: center;
            margin-bottom: 15px;
            font-weight: bold;
        }

        .success {
            color: green;
        }

        .error {
            color: red;
        }
    </style>
    <script>
        function confirmSubmit() {
            return confirm("Bạn có chắc chắn muốn gửi phản hồi này không?");
        }
    </script>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <h2>📩 Gửi phản hồi</h2>

            <?php if ($success): ?>
                <div class="message success"><?= htmlspecialchars($success) ?></div>
            <?php elseif ($error): ?>
                <div class="message error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" onsubmit="return confirmSubmit()">
                <label for="message">Nội dung phản hồi:</label>
                <textarea name="message" id="message" placeholder="Nhập nội dung tại đây..." required maxlength="200"><?= isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '' ?></textarea>

                <label for="image">🖼️ Đính kèm ảnh (nếu có):</label>
                <input type="file" name="image" id="image" accept="image/*">

                <button type="submit">📤 Gửi phản hồi</button>
            </form>

            <a class="back-link" href="dashboard.php">← Quay lại Dashboard</a>
        </div>
    </div>
</body>
</html>
