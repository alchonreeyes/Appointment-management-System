<?php
session_start();
// This path assumes 'database.php' is in the 'EYE MASTER' folder,
// and this file is in 'EYE MASTER/admin/'


require_once __DIR__ . '/../database.php';




// ======================================================================
// <-- FIX #1: Ginamit ang tamang Session variables galing sa login.php
// ======================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../../public/login.php'); // Itatapon pabalik sa login kung hindi admin
    exit;
}

// ======================================================================
// <-- FIX #2: Inayos ang function para gumamit ng $conn (mysqli)
// ======================================================================
function checkDataExists($conn_db, $table, $whereClause) {
    if (!$whereClause) {
        return false;
    }
    $sql = "SELECT 1 FROM {$table} a WHERE {$whereClause} LIMIT 1";
    $result = $conn_db->query($sql);
    
    // Error checking
    if (!$result) {
        error_log("SQL Error in checkDataExists: " . $conn_db->error);
        return false;
    }
    // FIX 2.1: Ginamit ang num_rows imbes na fetchColumn()
    return $result && $result->num_rows > 0;
}

// ===== IMPROVED FILTER PARAMETERS WITH PROPER DEFAULTS =====
$currentYear = date('Y');
$currentMonth = date('F');
$currentMonthNum = date('m');

$filterYear = $_GET['year'] ?? $currentYear;
$filterMonth = $_GET['month'] ?? $currentMonth;
$filterWeek = $_GET['week'] ?? null;
$filterDay = $_GET['day'] ?? null;

// Convert month name to number
$monthNum = date('m', strtotime("1 {$filterMonth} {$filterYear}"));

// ===== DATA-AWARE DROPDOWN LOGIC =====

// --- YEAR DROPDOWN (Data Check) ---
$yearDropdownItems = '';
$availableYears = ['2025', '2024', '2023'];
$yearDataFlags = [];

foreach ($availableYears as $y) {
    $where = "YEAR(a.appointment_date) = '{$y}'";
    $hasData = checkDataExists($conn, 'appointments', $where); // <-- Ginamit ang $conn
    $yearDataFlags[$y] = $hasData ? 'has-data' : '';
    $activeClass = ($filterYear === $y) ? 'active' : '';
    $yearDropdownItems .= "<div class=\"dropdown-item {$activeClass} {$yearDataFlags[$y]}\" onclick=\"applyFilter('year', '{$y}')\">{$y}</div>";
}

// --- MONTH DROPDOWN (Data Check) ---
$months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$monthDropdownItems = '';

foreach ($months as $m) {
    $mNum = date('m', strtotime("1 {$m} {$filterYear}"));
    $where = "YEAR(a.appointment_date) = '{$filterYear}' AND MONTH(a.appointment_date) = '{$mNum}'";
    $hasData = checkDataExists($conn, 'appointments', $where); // <-- Ginamit ang $conn
    $hasDataClass = $hasData ? 'has-data' : '';
    $active = $filterMonth === $m ? 'active' : '';
    $monthDropdownItems .= "<div class=\"dropdown-item {$active} {$hasDataClass}\" onclick=\"applyFilter('month', '{$m}')\">{$m}</div>";
}

// --- DAY DROPDOWN (Data Check & Generation) ---
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, intval($monthNum), intval($filterYear));
$dayDropdownItems = '';

for ($d = 1; $d <= $daysInMonth; $d++) {
    $dayPadded = str_pad($d, 2, '0', STR_PAD_LEFT);
    $selectedDate = "{$filterYear}-{$monthNum}-{$dayPadded}";
    $where = "DATE(a.appointment_date) = '{$selectedDate}'";
    $hasData = checkDataExists($conn, 'appointments', $where); // <-- Ginamit ang $conn
    $hasDataClass = $hasData ? 'has-data' : '';

    $activeClass = (intval($filterDay) === $d) ? 'active' : '';
    $dayDropdownItems .= "<div class=\"dropdown-item {$activeClass} {$hasDataClass}\" onclick=\"applyFilter('day', '{$d}')\">Day {$d}</div>";
}

// Day button text
$dayBtnText = 'Day';
if (is_numeric($filterDay) && intval($filterDay) >= 1 && intval($filterDay) <= $daysInMonth) {
    $dayBtnText = "Day " . intval($filterDay);
}

// --- WEEK DROPDOWN (Data Check & Generation) ---
$weekDropdownItems = '';
$firstDayOfMonth = new DateTime("{$filterYear}-{$monthNum}-01");
$lastDayOfMonth = new DateTime("{$filterYear}-{$monthNum}-{$daysInMonth}");
$weekCount = 0;

$temp = clone $firstDayOfMonth;
$weekCounter = 1;
while ($temp <= $lastDayOfMonth && $weekCounter <= 5) {
    $startOfWeek = clone $temp;
    $endOfWeek = (clone $startOfWeek)->modify('+6 days');

    if ($endOfWeek > $lastDayOfMonth) {
        $endOfWeek = $lastDayOfMonth;
    }

    $startDate = $startOfWeek->format('Y-m-d');
    $endDate = $endOfWeek->format('Y-m-d');
    
    $where = "DATE(a.appointment_date) BETWEEN '{$startDate}' AND '{$endDate}'";
    $hasData = checkDataExists($conn, 'appointments', $where); // <-- Ginamit ang $conn
    $hasDataClass = $hasData ? 'has-data' : '';

    $activeClass = (intval($filterWeek) === $weekCounter) ? 'active' : '';
    $weekDropdownItems .= "<div class=\"dropdown-item {$activeClass} {$hasDataClass}\" onclick=\"applyFilter('week', '{$weekCounter}')\">Week {$weekCounter}</div>";
    
    $temp->modify('+1 week');
    $weekCounter++;
}


// Week button text
$weekBtnText = 'Week';
if (is_numeric($filterWeek) && intval($filterWeek) >= 1) {
    $weekBtnText = "Week " . intval($filterWeek);
}

// ===== BUILD SQL FILTERS (Idinagdag ang 'a.' alias) =====

// 1. BASE FILTER: Month + Year (default view)
$baseFilter = "MONTH(a.appointment_date) = '{$monthNum}' AND YEAR(a.appointment_date) = '{$filterYear}'";

// 2. APPLY DAY FILTER (most specific)
$statFilter = $baseFilter;
if (is_numeric($filterDay) && intval($filterDay) >= 1 && intval($filterDay) <= $daysInMonth) {
    $selectedDate = "{$filterYear}-{$monthNum}-" . str_pad($filterDay, 2, '0', STR_PAD_LEFT);
    $statFilter = "DATE(a.appointment_date) = '{$selectedDate}'";
}
// 3. APPLY WEEK FILTER (if day not set)
elseif ($filterWeek) {
    if (is_numeric($filterWeek)) {
        $weekNum = intval($filterWeek);
        $startOfWeek = (clone $firstDayOfMonth)->modify('+' . ($weekNum - 1) . ' weeks');
        $endOfWeek = (clone $startOfWeek)->modify('+6 days');
        
        if ($endOfWeek > $lastDayOfMonth) {
            $endOfWeek = $lastDayOfMonth;
        }
        
        $startDate = $startOfWeek->format('Y-m-d');
        $endDate = $endOfWeek->format('Y-m-d');
        $statFilter = "DATE(a.appointment_date) BETWEEN '{$startDate}' AND '{$endDate}'";
    }
}

// ======================================================================
// <-- FIX #3: Inayos ang lahat ng queries para gumamit ng $conn (mysqli)
//             at tinama ang mga column names (full_name, status_id, etc.)
// ======================================================================

// 1. Total Appointments (based on filter)
$sql1 = "SELECT COUNT(a.appointment_id) FROM appointments a WHERE {$statFilter}";
$result1 = $conn->query($sql1);
$totalAppointmentsToday = $result1 ? $result1->fetch_array()[0] : 0;

// Comparison with yesterday
$sql2 = "SELECT COUNT(a.appointment_id) FROM appointments a WHERE DATE(a.appointment_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
$result2 = $conn->query($sql2);
$yesterdayCount = $result2 ? $result2->fetch_array()[0] : 0;
$percentChange = $yesterdayCount > 0 ? round((($totalAppointmentsToday - $yesterdayCount) / $yesterdayCount) * 100) : 0;

// 2. Total Patients (Pinalitan ang 'patient_name' ng 'full_name')
$sql3 = "SELECT COUNT(DISTINCT a.full_name) FROM appointments a WHERE {$baseFilter}";
$result3 = $conn->query($sql3);
$totalPatients = $result3 ? $result3->fetch_array()[0] : 0;

// Last month comparison
$lastMonth = date('m', strtotime("-1 month", strtotime("1 {$filterMonth} {$filterYear}")));
$lastYear = date('Y', strtotime("-1 month", strtotime("1 {$filterMonth} {$filterYear}")));
$sql4 = "SELECT COUNT(DISTINCT a.full_name) FROM appointments a WHERE MONTH(a.appointment_date) = '{$lastMonth}' AND YEAR(a.appointment_date) = '{$lastYear}'";
$result4 = $conn->query($sql4);
$lastMonthPatients = $result4 ? $result4->fetch_array()[0] : 0;
$patientPercentChange = $lastMonthPatients > 0 ? round((($totalPatients - $lastMonthPatients) / $lastMonthPatients) * 100) : 0;

// 3. Pending Appointments (Idinagdag ang JOIN at pinalitan ang 'status' ng 'status_name')
$sql5 = "SELECT COUNT(a.appointment_id) 
         FROM appointments a
         JOIN appointmentstatus s ON a.status_id = s.status_id
         WHERE s.status_name = 'Pending' AND {$statFilter}";
$result5 = $conn->query($sql5);
$pendingAppointments = $result5 ? $result5->fetch_array()[0] : 0;

// Comparison with yesterday
$sql6 = "SELECT COUNT(a.appointment_id) 
         FROM appointments a
         JOIN appointmentstatus s ON a.status_id = s.status_id
         WHERE s.status_name = 'Pending' AND DATE(a.appointment_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
$result6 = $conn->query($sql6);
$yesterdayPending = $result6 ? $result6->fetch_array()[0] : 0;
$pendingChange = $pendingAppointments - $yesterdayPending;

// 4. Completed Appointments
$sql7 = "SELECT COUNT(a.appointment_id) 
         FROM appointments a
         JOIN appointmentstatus s ON a.status_id = s.status_id
         WHERE s.status_name = 'Completed' AND {$statFilter}";
$result7 = $conn->query($sql7);
$completedToday = $result7 ? $result7->fetch_array()[0] : 0;
$completionRate = $totalAppointmentsToday > 0 ? round(($completedToday / $totalAppointmentsToday) * 100) : 0;

// ======================================================================
// <-- 5. ADDED: Missed Appointments
// ======================================================================
$sql_missed = "SELECT COUNT(a.appointment_id) 
               FROM appointments a
               JOIN appointmentstatus s ON a.status_id = s.status_id
               WHERE s.status_name = 'Missed' AND {$statFilter}";
$result_missed = $conn->query($sql_missed);
$missedAppointments = $result_missed ? $result_missed->fetch_array()[0] : 0;
// ======================================================================

// ===== CHART DATA (Inayos din ang queries) =====

// Daily appointments
$sql_daily = "SELECT DATE(a.appointment_date) AS date, COUNT(a.appointment_id) AS count
              FROM appointments a
              WHERE {$baseFilter}
              GROUP BY DATE(a.appointment_date)
              ORDER BY DATE(a.appointment_date)";
$result_daily = $conn->query($sql_daily);
$dailyData = $result_daily ? $result_daily->fetch_all(MYSQLI_ASSOC) : [];

// Status distribution
$sql_status = "SELECT s.status_name, COUNT(a.appointment_id) AS count 
               FROM appointments a
               JOIN appointmentstatus s ON a.status_id = s.status_id
               WHERE {$baseFilter}
               GROUP BY s.status_name";
$result_status = $conn->query($sql_status);
$statusData = $result_status ? $result_status->fetch_all(MYSQLI_ASSOC) : [];

// Weekly appointments
$weeklyFilter = $statFilter;
$sql_weekly = "SELECT DAYNAME(a.appointment_date) AS day, COUNT(a.appointment_id) AS count
               FROM appointments a
               WHERE {$weeklyFilter}
               GROUP BY DAYNAME(a.appointment_date), DAYOFWEEK(a.appointment_date)
               ORDER BY DAYOFWEEK(a.appointment_date)";
$result_weekly = $conn->query($sql_weekly);
$weeklyData = $result_weekly ? $result_weekly->fetch_all(MYSQLI_ASSOC) : [];

// Recent appointments
$sql_recent = "SELECT a.full_name, ser.service_name, a.appointment_date, s.status_name
               FROM appointments a
               LEFT JOIN services ser ON a.service_id = ser.service_id
               LEFT JOIN appointmentstatus s ON a.status_id = s.status_id
               WHERE {$baseFilter}
               ORDER BY a.appointment_date DESC
               LIMIT 3";
$result_recent = $conn->query($sql_recent);
$recentAppointments = $result_recent ? $result_recent->fetch_all(MYSQLI_ASSOC) : [];

// ===== FALLBACK DATA (if empty) =====
if (empty($dailyData)) {
    $dailyData = [['date' => date('Y-m-d'), 'count' => 0]];
}
if (empty($statusData)) {
    $statusData = [['status_name' => 'No Data', 'count' => 1]]; // Pinalitan ng status_name
}
if (empty($weeklyData)) {
    $weeklyData = [['day' => 'Monday', 'count' => 0]];
}
if (empty($recentAppointments)) {
    $recentAppointments = [];
}
?>