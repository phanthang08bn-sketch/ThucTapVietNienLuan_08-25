<?php
session_start();
include "db.php";
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Tổng thu
$result1 = pg_query_params($conn,
    "SELECT SUM(amount) AS total_thu FROM transactions WHERE user_id = $1 AND type = 0",
    array($user_id)
);
$row1 = pg_fetch_assoc($result1);
$total_thu = $row1['total_thu'] ?? 0;

// Tổng chi
$result2 = pg_query_params($conn,
    "SELECT SUM(amount) AS total_chi FROM transactions WHERE user_id = $1 AND type = 1",
    array($user_id)
);
$row2 = pg_fetch_assoc($result2);
$total_chi = $row2['total_chi'] ?? 0;

// Số dư
$so_du = $total_thu - $total_chi;

// Tổng thu / chi theo tháng
$result3 = pg_query_params($conn, "
    SELECT 
        TO_CHAR(date, 'MM/YYYY') AS month,
        SUM(CASE WHEN type = 0 THEN amount ELSE 0 END) AS thu,
        SUM(CASE WHEN type = 1 THEN amount ELSE 0 END) AS chi
    FROM transactions
    WHERE user_id = $1
    GROUP BY month
    ORDER BY TO_DATE('01/' || month, 'DD/MM/YYYY')
", array($user_id));

$labels = [];
$thu_data = [];
$chi_data = [];

while ($row = pg_fetch_assoc($result3)) {
    $labels[] = $row['month'];
    $thu_data[] = $row['thu'];
    $chi_data[] = $row['chi'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Thống kê thu chi</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: Arial;
            background-color: #f4f4f4;
            padding: 20px;
        }
        .chart-container {
            max-width: 800px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 8px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            margin-bottom: 30px;
        }
        table {
            margin: auto;
            width: 60%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: center;
        }
        a {
            display: inline-block;
            margin-top: 20px;
            text-align: center;
            color: #007BFF;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="chart-container">
    <h2>📊 Thống kê thu chi</h2>

    <table>
        <tr><th>Loại</th><th>Số tiền (VND)</th></tr>
        <tr><td>Tổng thu</td><td><?= number_format($total_thu, 0, ',', '.') ?> VND</td></tr>
        <tr><td>Tổng chi</td><td><?= number_format($total_chi, 0, ',', '.') ?> VND</td></tr>
        <tr><td><strong>Số dư</strong></td><td><strong><?= number_format($so_du, 0, ',', '.') ?> VND</strong></td></tr>
    </table>

    <canvas id="pieChart" height="200"></canvas>
    <canvas id="barChart" height="250"></canvas>

    <div style="text-align: center;">
        <a href="dashboard.php">← Quay lại Dashboard</a> |
        <a href="transactions.php">Xem chi tiết giao dịch</a>
    </div>
</div>

<!-- ✅ Script tạo biểu đồ đặt ở cuối body -->
<script>
const pieCtx = document.getElementById('pieChart').getContext('2d');
new Chart(pieCtx, {
    type: 'pie',
    data: {
        labels: ['Tổng thu', 'Tổng chi'],
        datasets: [{
            data: [<?= $total_thu ?>, <?= $total_chi ?>],
            backgroundColor: ['#28a745', '#dc3545']
        }]
    }
});

const barCtx = document.getElementById('barChart').getContext('2d');
new Chart(barCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [
            {
                label: 'Thu',
                data: <?= json_encode($thu_data) ?>,
                backgroundColor: '#28a745'
            },
            {
                label: 'Chi',
                data: <?= json_encode($chi_data) ?>,
                backgroundColor: '#dc3545'
            }
        ]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString('vi-VN') + ' VND';
                    }
                }
            }
        }
    }
});
</script>

</body>
</html>
