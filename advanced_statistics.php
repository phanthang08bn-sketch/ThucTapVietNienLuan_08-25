<?php
session_start();
include "db.php"; // Đảm bảo db.php dùng pg_connect()
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$mode = $_GET['mode'] ?? 'month';
$chartType = $_GET['chart'] ?? (($mode === 'year') ? 'pie' : 'line');

$labels = $thu_data = $chi_data = [];
$labels2 = $thu_data2 = $chi_data2 = [];

if ($mode === 'year') {
    $sql = "
        SELECT EXTRACT(YEAR FROM date) AS label,
            SUM(CASE WHEN type = 1 THEN amount ELSE 0 END) AS thu,
            SUM(CASE WHEN type = 2 THEN amount ELSE 0 END) AS chi
        FROM transactions
        WHERE user_id = $1
        GROUP BY label
        ORDER BY label DESC
        LIMIT 2
    ";
} elseif ($mode === 'week') {
    if ($chartType === 'line') {
        $sql = "
            SELECT DATE(date) AS label,
                SUM(CASE WHEN type = 1 THEN amount ELSE 0 END) AS thu,
                SUM(CASE WHEN type = 2 THEN amount ELSE 0 END) AS chi
            FROM transactions
            WHERE user_id = $1 AND date >= CURRENT_DATE - INTERVAL '8 days'
            GROUP BY label
            ORDER BY label ASC
        ";
    } else {
        $sql = "
            SELECT EXTRACT(YEAR FROM date) AS y, EXTRACT(WEEK FROM date) AS w,
                SUM(CASE WHEN type = 1 THEN amount ELSE 0 END) AS thu,
                SUM(CASE WHEN type = 2 THEN amount ELSE 0 END) AS chi
            FROM transactions
            WHERE user_id = $1
            GROUP BY y, w
            ORDER BY y DESC, w DESC
            LIMIT 2
        ";
    }
} elseif ($mode === 'month') {
    if ($chartType === 'line') {
        $sql = "
            SELECT DATE(date) AS label,
                SUM(CASE WHEN type = 1 THEN amount ELSE 0 END) AS thu,
                SUM(CASE WHEN type = 2 THEN amount ELSE 0 END) AS chi
            FROM transactions
            WHERE user_id = $1 AND date >= CURRENT_DATE - INTERVAL '29 days'
            GROUP BY label
            ORDER BY label ASC
        ";
    } else {
        $sql = "
            SELECT EXTRACT(YEAR FROM date) AS y, EXTRACT(MONTH FROM date) AS m,
                SUM(CASE WHEN type = 1 THEN amount ELSE 0 END) AS thu,
                SUM(CASE WHEN type = 2 THEN amount ELSE 0 END) AS chi
            FROM transactions
            WHERE user_id = $1
            GROUP BY y, m
            ORDER BY y DESC, m DESC
            LIMIT 2
        ";
    }
}

if ($mode === 'year' && $chartType === 'line') {
    $fullDates = [];
    $monthList = [];
    for ($i = 11; $i >= 0; $i--) {
        $monthLabel = date('Y-m', strtotime("-$i months"));
        $fullDates[$monthLabel] = ['thu' => 0, 'chi' => 0];
        $monthList[] = $monthLabel;
    }

    $placeholders = implode(',', array_map(fn($i) => '$' . ($i + 2), array_keys($monthList)));
    $sql = "
        SELECT TO_CHAR(date, 'YYYY-MM') AS label,
               SUM(CASE WHEN type = 1 THEN amount ELSE 0 END) AS thu,
               SUM(CASE WHEN type = 2 THEN amount ELSE 0 END) AS chi
        FROM transactions
        WHERE user_id = $1 AND TO_CHAR(date, 'YYYY-MM') IN ($placeholders)
        GROUP BY label
        ORDER BY label ASC
    ";
    $params = array_merge([$user_id], $monthList);
}

if (!isset($params)) {
    $params = [$user_id];
}
$result = pg_query_params($conn, $sql, $params);

if ($chartType === 'line') {
    switch ($mode) {
        case 'month':
            for ($i = 29; $i >= 0; $i--) {
                $label = date('Y-m-d', strtotime("-$i days"));
                $fullDates[$label] = ['thu' => 0, 'chi' => 0];
            }
            break;
        case 'week':
            for ($i = 7; $i >= 0; $i--) {
                $label = date('Y-m-d', strtotime("-$i days"));
                $fullDates[$label] = ['thu' => 0, 'chi' => 0];
            }
            break;
    }
}
// … trước đó bạn đã chạy pg_query_params và có $result

$index = 0;
while ($row = pg_fetch_assoc($result)) {
    // 1) NHÁNH LINE
    if ($chartType === 'line') {
        if ($mode === 'year') {
            // cập nhật vào fullDates[ 'YYYY-MM' ]
            $label = $row['label']; 
            if (isset($fullDates[$label])) {
                $fullDates[$label]['thu'] = (float)$row['thu'];
                $fullDates[$label]['chi'] = (float)$row['chi'];
            }

        } elseif ($mode === 'week' || $mode === 'month') {
            // cập nhật vào fullDates[ 'YYYY-MM-DD' ]
            $date = $row['label'];
            if (isset($fullDates[$date])) {
                $fullDates[$date]['thu'] = (float)$row['thu'];
                $fullDates[$date]['chi'] = (float)$row['chi'];
            }
        }

    // 2) NHÁNH PIE
    } else {
        if (($mode === 'week' || $mode === 'month') && $chartType === 'pie') {
            $label = ($mode === 'week')
                ? "Tuần {$row['w']}/{$row['y']}"
                : "Tháng {$row['m']}/{$row['y']}";
            if ($index === 0) {
                $labels[] = $label;
                $thu_data[] = $row['thu'];
                $chi_data[] = $row['chi'];
            } else {
                $labels2[] = $label;
                $thu_data2[] = $row['thu'];
                $chi_data2[] = $row['chi'];
            }

        } elseif ($mode === 'year' && $chartType === 'pie') {
            if ($index === 0) {
                $labels[] = $row['label'];
                $thu_data[] = $row['thu'];
                $chi_data[] = $row['chi'];
            } else {
                $labels2[] = $row['label'];
                $thu_data2[] = $row['thu'];
                $chi_data2[] = $row['chi'];
            }
        }
    }

    $index++;
}

// Cuối cùng: nếu line-chart thì build mảng labels/data từ $fullDates
if ($chartType === 'line') {
    if ($mode === 'year') {
        foreach ($fullDates as $ym => $data) {
            $labels[]   = date('M Y', strtotime($ym . '-01'));
            $thu_data[] = $data['thu'];
            $chi_data[] = $data['chi'];
        }
    } else {
        foreach ($fullDates as $date => $data) {
            $labels[]   = date('d/m', strtotime($date));
            $thu_data[] = $data['thu'];
            $chi_data[] = $data['chi'];
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Thống kê nâng cao</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @media screen and (max-width: 768px) {
          body {
            padding: 15px;
          }
        
          .container {
            padding: 15px;
            border-radius: 0;
            box-shadow: none;
          }
        
          .pie-row {
            flex-direction: column;
            gap: 30px;
            align-items: center;
          }
        
          canvas.pie-chart {
            max-width: 90vw;
          }
        
          .filter form {
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: stretch;
          }
        
          select, button {
            font-size: 14px;
            width: 100%;
            margin: 5px 0;
          }
        
          h2 {
            font-size: 20px;
          }
        
          a {
            font-size: 14px;
          }
        }
        
        @media screen and (max-width: 500px) {
          h2 {
            font-size: 18px;
          }
        
          .container {
            padding: 10px;
          }
        
          select, button {
            padding: 8px;
          }
        
          p {
            font-size: 14px;
          }
        
          canvas.pie-chart {
            max-width: 100%;
          }
        }

        body {
            font-family: Arial;
            background: #f0f2f5;
            margin: 0;
            padding: 30px;
        }
        .container {
            max-width: 1000px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .filter {
            text-align: center;
            margin-bottom: 20px;
        }
        select, button {
            padding: 6px 10px;
            font-size: 16px;
            margin: 0 10px;
        }
        canvas {
            margin-top: 30px;
        }
        .pie-row {
            display: flex;
            justify-content: center;
            gap: 40px;
        }
        canvas.pie-chart {
            max-width: 400px;
            max-height: 400px;
            width: 100%;
            height: auto;
        }
        a {
            display: block;
            text-align: center;
            margin-top: 30px;
            text-decoration: none;
            color: #007bff;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>📈 Thống kê nâng cao</h2>
    <div class="filter">
        <form method="GET">
            <label>Thống kê:</label>
            <select name="mode">
                <option value="week" <?= $mode === 'week' ? 'selected' : '' ?>>Theo tuần</option>
                <option value="month" <?= $mode === 'month' ? 'selected' : '' ?>>Theo tháng</option>
                <option value="year" <?= $mode === 'year' ? 'selected' : '' ?>>Theo năm</option>
            </select>

            <?php if (in_array($mode, ['week', 'month', 'year'])): ?>
            <label>Biểu đồ:</label>
            <select name="chart">
                <option value="pie" <?= $chartType === 'pie' ? 'selected' : '' ?>>Biểu đồ tròn</option>
                <option value="line" <?= $chartType === 'line' ? 'selected' : '' ?>>Biểu đồ đường</option>
            </select>
            <?php endif; ?>

            <button type="submit">Xem</button>
        </form>
    </div>

    <?php if ($mode === 'year' && $chartType === 'pie'): ?>
    <div class="pie-row">
        <div>
            <canvas id="pieChart2" class="pie-chart"></canvas>
            <p style="text-align:center">
                Năm <?= $labels2[0] ?? '' ?><br>
                Tổng thu: <strong><?= number_format($thu_data2[0] ?? 0, 0, ',', '.') ?> VND</strong><br>
                Tổng chi: <strong><?= number_format($chi_data2[0] ?? 0, 0, ',', '.') ?> VND</strong>
            </p>
        </div>
        <div>
            <canvas id="pieChart1" class="pie-chart"></canvas>
            <p style="text-align:center">
                Năm <?= $labels[0] ?? '' ?><br>
                Tổng thu: <strong><?= number_format($thu_data[0] ?? 0, 0, ',', '.') ?> VND</strong><br>
                Tổng chi: <strong><?= number_format($chi_data[0] ?? 0, 0, ',', '.') ?> VND</strong>
            </p>
        </div>
    </div>
    <?php endif; ?>
                
    <?php if (isset($_GET['mode']) && ($mode === 'week' || $mode === 'month') && $chartType === 'pie'): ?>
    <div class="pie-row">
        <div>
            <canvas id="pieChart2" class="pie-chart"></canvas>
            <p style="text-align:center">
                Tổng thu: <strong><?= number_format($thu_data2[0] ?? 0, 0, ',', '.') ?> VND</strong><br>
                Tổng chi: <strong><?= number_format($chi_data2[0] ?? 0, 0, ',', '.') ?> VND</strong>
            </p>
        </div>
        <div>
            <canvas id="pieChart1" class="pie-chart"></canvas>
            <p style="text-align:center">
                Tổng thu: <strong><?= number_format($thu_data[0] ?? 0, 0, ',', '.') ?> VND</strong><br>
                Tổng chi: <strong><?= number_format($chi_data[0] ?? 0, 0, ',', '.') ?> VND</strong>
            </p>
        </div>
    </div>
    <?php else: ?>
    <?php if ($chartType === 'line'): ?>
        <canvas id="myChart" class="line-chart"></canvas>
    <?php endif; ?>
    <?php if ($chartType === 'line'): ?>
        <p style="text-align:center; margin-top: 20px;">
            Tổng thu: <strong><?= number_format(array_sum($thu_data), 0, ',', '.') ?> VND</strong><br>
            Tổng chi: <strong><?= number_format(array_sum($chi_data), 0, ',', '.') ?> VND</strong>
        </p>
    <?php endif; ?>

    <?php endif; ?>

    <a href="dashboard.php">← Quay lại Dashboard</a>
</div>

<script>
const mode = <?= json_encode($mode) ?>;
const chartType = <?= json_encode($chartType) ?>;
const labels = <?= json_encode($labels) ?>;
const thu = <?= json_encode($thu_data) ?>;
const chi = <?= json_encode($chi_data) ?>;

if (chartType === 'line') {
    const ctx = document.getElementById('myChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Thu',
                    data: thu,
                    borderColor: '#28a745',
                    backgroundColor: '#28a74533',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Chi',
                    data: chi,
                    borderColor: '#dc3545',
                    backgroundColor: '#dc354533',
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: value => value.toLocaleString('vi-VN') + ' VND'
                    }
                }
            }
        }
    });
} else if (mode === 'year') {
    const ctx1 = document.getElementById('pieChart1').getContext('2d');
    new Chart(ctx1, {
        type: 'pie',
        data: {
            labels: ['Tổng thu', 'Tổng chi'],
            datasets: [{
                data: [<?= $thu_data[0] ?? 0 ?>, <?= $chi_data[0] ?? 0 ?>],
                backgroundColor: ['#28a745', '#dc3545']
            }]
        },
        options: {
            plugins: {
                title: {
                    display: true,
                    text: "Năm <?= $labels[0] ?? '' ?>"
                }
            }
        }
    });

    const ctx2 = document.getElementById('pieChart2').getContext('2d');
    new Chart(ctx2, {
        type: 'pie',
        data: {
            labels: ['Tổng thu', 'Tổng chi'],
            datasets: [{
                data: [<?= $thu_data2[0] ?? 0 ?>, <?= $chi_data2[0] ?? 0 ?>],
                backgroundColor: ['#28a745', '#dc3545']
            }]
        },
        options: {
            plugins: {
                title: {
                    display: true,
                    text: "Năm <?= $labels2[0] ?? '' ?>"
                }
            }
        }
    });

} else if ((mode === 'week' || mode === 'month') && chartType === 'pie') {
    const ctx1 = document.getElementById('pieChart1').getContext('2d');
    new Chart(ctx1, {
        type: 'pie',
        data: {
            labels: ['Tổng thu', 'Tổng chi'],
            datasets: [{
                data: [<?= $thu_data[0] ?? 0 ?>, <?= $chi_data[0] ?? 0 ?>],
                backgroundColor: ['#28a745', '#dc3545']
            }]
        },
        options: {plugins: {title: {display: true, text: <?= json_encode($labels[0] ?? '') ?>}}}
    });

    const ctx2 = document.getElementById('pieChart2').getContext('2d');
    new Chart(ctx2, {
        type: 'pie',
        data: {
            labels: ['Tổng thu', 'Tổng chi'],
            datasets: [{
                data: [<?= $thu_data2[0] ?? 0 ?>, <?= $chi_data2[0] ?? 0 ?>],
                backgroundColor: ['#28a745', '#dc3545']
            }]
        },
        options: {plugins: {title: {display: true, text: <?= json_encode($labels2[0] ?? '') ?>}}}
    });
} else {
    const ctx = document.getElementById('myChart').getContext('2d');
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['Tổng thu', 'Tổng chi'],
            datasets: [{
                data: [<?= array_sum($thu_data) ?>, <?= array_sum($chi_data) ?>],
                backgroundColor: ['#28a745', '#dc3545']
            }]
        }
    });
}
</script>
</body>
</html>
