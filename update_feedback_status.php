<?php
include "db.php"; // kết nối PostgreSQL

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Kiểm tra dữ liệu đầu vào
    if (isset($_POST['feedback_id'], $_POST['action'])) {
        $feedback_id = (int)$_POST['feedback_id'];
        $action      = $_POST['action'];

        // Chỉ chấp nhận các giá trị hợp lệ
        if (in_array($action, ['processed', 'ignored'])) {
            // Cập nhật trạng thái phản hồi
            $result = pg_query_params(
                $conn,
                "UPDATE feedbacks SET status = $1 WHERE id = $2",
                [ $action, $feedback_id ]
            );

            if ($result) {
                header("Location: admin_feedback.php");
                exit();
            } else {
                echo "❌ Không thể cập nhật phản hồi. Lỗi CSDL.";
            }
        } else {
            echo "❌ Giá trị 'action' không hợp lệ.";
        }
    } else {
        echo "❌ Thiếu dữ liệu đầu vào.";
    }
} else {
    echo "❌ Phương thức không hợp lệ.";
}
?>
