<?php
include("../config/database.php");

$filter = $_GET['filter'] ?? 'day';

if ($filter === 'day') {
    $query = "SELECT DATE(created_at) AS period, SUM(total_price) AS revenue FROM book WHERE status = 'Accepted' GROUP BY period ORDER BY created_at";
} elseif ($filter === 'week') {
    $query = "SELECT YEARWEEK(created_at) AS period, SUM(total_price) AS revenue FROM book WHERE status = 'Accepted' GROUP BY period ORDER BY created_at";
} elseif ($filter === 'month') {
    $query = "SELECT DATE_FORMAT(created_at, '%b %Y') AS period, SUM(total_price) AS revenue FROM book WHERE status = 'Accepted' GROUP BY period ORDER BY created_at";
} else {
    $query = "SELECT YEAR(created_at) AS period, SUM(total_price) AS revenue FROM book WHERE status = 'Accepted' GROUP BY period ORDER BY created_at";
}

$result = mysqli_query($conn, $query);
$labels = [];
$revenues = [];

while ($row = mysqli_fetch_assoc($result)) {
    $labels[] = $row['period'];
    $revenues[] = $row['revenue'];
}

echo json_encode(['labels' => $labels, 'revenues' => $revenues]);
?>
