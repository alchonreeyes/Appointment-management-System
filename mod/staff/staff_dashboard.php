<?php
session_start();
// This path assumes 'database.php' is in the 'EYE MASTER' folder,
// and this file is in 'EYE MASTER/staff/'


require_once __DIR__ . '/../database.php';




// ======================================================================
// <-- FIX #1: Ginamit ang tamang Session variables galing sa login.php
// ======================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'staff') {
     header('Location: ../../public/login.php'); // Itatapon pabalik sa login kung hindi staff
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

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Eye Master Clinic - staff Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/html5-qrcode"></script>
<style>
    /* ... (Ang iyong buong CSS ay andito pa rin) ... */
/* =================================== */
/* <-- FIX #4: BAGONG CSS PARA SA PAGE LOADER
/* =================================== */
#page-loader-overlay {
    position: fixed;
    inset: 0;
    background: #ffffff;
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    transition: opacity 0.5s ease;
}
.loader-spinner-fullpage {
    width: 60px;
    height: 60px;
    border: 6px solid #f3f3f3; /* Light grey */
    border-top: 6px solid #991010; /* Theme Red */
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 20px;
}
#page-loader-overlay p {
    color: #333;
    font-weight: 600;
    font-size: 16px;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
/* --------------------------------- */

.dropdown-item.has-data:not(.active) {
    background-color: #e6f7e9; 
}
.dropdown-item.has-data:hover:not(.active) {
    background-color: #d0edd4;
}
.dropdown-item.has-data::after {
    content: '●';
    color: #2ecc71;
    font-size: 10px;
    margin-left: 5px;
    position: absolute;
    right: 10px;
    line-height: 18px;
}
.dropdown-item.has-data.active::after {
    color: white;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
    background: #f8f9fa; 
    position: relative;
    overflow-x: hidden;
}
.vertical-bar {
    position: fixed;
    left: 0;
    top: 0;
    width: 55px;
    height: 100vh;
    background: linear-gradient(180deg, #991010ff 0%, #6b1010ff 100%);
    z-index: 1000;
}
.vertical-bar .circle {
    width: 70px;
    height: 70px;
    background: #b91313ff;
    border-radius: 50%;
    position: absolute;
    left: -8px;
    top: 45%;
    transform: translateY(-50%);
    border: 4px solid #5a0a0aff;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}
header { 
    display: flex; 
    align-items: center; 
    background: #fff; 
    padding: 12px 20px 12px 75px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    position: relative;
    z-index: 100;
}
.logo-section {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-right: auto;
}
.logo-section img { height:32px; border-radius:4px; object-fit:cover; }
.logo-section strong { 
    font-size: 16px; 
    color: #2c3e50;
    letter-spacing: 0.3px;
}
nav { 
    display: flex;
    gap: 8px;
    align-items: center;
}
nav a { 
    text-decoration: none; 
    padding: 8px 12px;
    font-weight: 600;
    color: #5a6c7d;
    border-radius: 6px;
}
nav a:hover { 
    background: #f0f0f0;
}
nav a.active {
    background: #dc3545;
    color: white;
}
.dashboard { 
    padding: 20px 20px 20px 75px;
    max-width: 100%;
    min-height: calc(100vh - 65px); /* BAGO: Binago ang height para sa footer */
    overflow-y: auto;
}
.welcome-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap; /* BAGO: para sa mobile */
    gap: 15px; /* BAGO: nagdagdag ng gap */
}
.welcome-text h1 { 
    font-size: 28px; 
    margin-bottom: 3px;
    color: #2c3e50;
}
.welcome-text p { 
    color: #7f8c8d; 
    font-size: 14px;
}
.top-controls {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap; /* BAGO: para sa mobile */
}
.close-btn { 
    background: #dc3545;
    color: #fff; 
    border: none; 
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer; 
    font-weight: 600;
    font-size: 13px;
    transition: all 0.2s;
}
.close-btn:hover {
    background: #c82333;
}
.filter-group {
    display: flex;
    gap: 8px;
    flex-wrap: wrap; /* BAGO: para sa mobile */
}
.filter-btn {
    background: white;
    border: 1.5px solid #ddd;
    padding: 8px 18px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    font-size: 13px;
    color: #5a6c7d;
    transition: all 0.2s;
    position: relative;
}
.filter-btn:hover {
    border-color: #bbb;
}
.filter-btn::after {
    content: '▼';
    margin-left: 6px;
    font-size: 9px;
}
.filter-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    background: white;
    border: 1.5px solid #ddd;
    border-radius: 6px;
    margin-top: 4px;
    min-width: 140px;
    display: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    z-index: 1000;
    max-height: 250px;
    overflow-y: auto;
}
.dropdown.active {
    display: block;
}
.dropdown-item {
    padding: 10px 16px;
    cursor: pointer;
    transition: background 0.2s;
    font-size: 13px;
    color: #2c3e50;
    position: relative;
}
.dropdown-item:hover {
    background: #f5f5f5;
}
.dropdown-item.active {
    background: #dc3545;
    color: white;
}
.dropdown-divider {
    height: 1px;
    background: #e0e0e0;
    margin: 4px 0;
}
.stats { 
    display: grid;
    /* BAGO: Ginawang responsive */
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px; 
    margin-bottom: 20px;
}
.card { 
    background: #fff;
    border: 1.5px solid #e0e0e0;
    border-radius: 12px;
    padding: 18px;
    text-align: center;
}
.card p { 
    color: #7f8c8d; 
    font-size: 13px;
    margin-bottom: 8px;
}
.card h2 { 
    font-size: 36px; 
    color: #2c3e50;
    margin-bottom: 6px;
}
.card .change {
    font-size: 12px;
    color: #27ae60;
}
.card .change.negative {
    color: #e74c3c;
}
.charts-grid {
    display: grid;
    grid-template-columns: 1.2fr 0.8fr;
    gap: 15px;
    margin-bottom: 15px;
}
.chart-box {
    background: #fff;
    border: 1.5px solid #e0e0e0;
    border-radius: 12px;
    padding: 18px;
}
.chart-box h3 {
    font-size: 14px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 15px;
}
.chart-wrapper {
    position: relative;
    height: 140px;
}
.bottom-section {
    display: grid;
    grid-template-columns: 0.8fr 1.2fr;
    gap: 15px;
}
.weekly-box {
    background: #fff;
    border: 1.5px solid #e0e0e0;
    border-radius: 12px;
    padding: 18px;
}
.weekly-box h3 {
    font-size: 14px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 15px;
}
.weekly-wrapper {
    position: relative;
    height: 140px;
}
.right-section {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 15px;
}
.recent { 
    background: #fff;
    border: 1.5px solid #e0e0e0;
    border-radius: 12px;
    padding: 18px;
}
.recent h3 {
    font-size: 14px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 15px;
}
.recent-item { 
    display: flex; 
    justify-content: space-between; 
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
}
.recent-item:last-child { 
    border-bottom: none; 
}
.recent-item-info h4 {
    font-size: 14px;
    color: #2c3e50;
    margin-bottom: 4px;
}
.recent-item-info p {
    font-size: 12px;
    color: #95a5a6;
}
.status { 
    border-radius: 16px;
    padding: 5px 14px;
    font-size: 11px;
    font-weight: 600;
}
.approved, .completed { 
    background: #d4edda;
    color: #155724;
}
.cancel, .cancelled, .missed { /* BAGO: Idinagdag ang missed */
    background: #f8d7da;
    color: #721c24;
}
.pending { 
    background: #fff3cd;
    color: #856404;
}
.qr-section {
    background: #fff;
    border: 1.5px solid #e0e0e0;
    border-radius: 12px;
    padding: 18px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
}
.qr-code-display {
    width: 160px;
    height: 160px;
    margin-bottom: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    padding: 10px;
}
.qr-code-display img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
.scan-btn {
    background: transparent;
    border: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    color: #2c3e50;
    padding: 8px;
    transition: color 0.2s;
}
.scan-btn:hover {
    color: #dc3545;
}
/* BAGO: Inayos ang #popup para maging responsive */
#popup {
    display: none;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 20px; /* BAGO: Binawasan ang padding */
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    z-index: 2000;
    width: 90%; /* Fluid width */
    max-width: 450px; /* BAGO: Nilakihan ng kaunti para sa 3-step */
    min-width: auto; /* Inalis ang fixed min-width */
}
#popup h3 {
    margin-bottom: 15px;
    color: #2c3e50;
    font-size: 18px;
    padding: 0 5px; /* BAGO: Dinagdag para sa alignment */
}
#popup button {
    background: #dc3545;
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    margin-top: 12px;
    font-size: 13px;
}
.popup-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.4);
    z-index: 1999;
}
.popup-overlay.active,
#popup.active {
    display: block;
}
footer { 
    text-align: center; 
    padding: 12px 20px 12px 75px;
    color: #7f8c8d; 
    font-size: 13px;
    background: #fff;
    border-top: 1px solid #e0e0e0;
}
.dashboard::-webkit-scrollbar {
    width: 8px;
}
.dashboard::-webkit-scrollbar-track {
    background: #f1f1ff;
}
.dashboard::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}
.dashboard::-webkit-scrollbar-thumb:hover {
    background: #555;
}
.empty-state {
    text-align: center;
    color: #95a5a6;
    padding: 20px;
    font-size: 13px;
}

.container { padding:20px 20px 40px 75px; max-width:1400px; margin:0 auto; }
.header-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; gap:12px; }
.header-row h2 { font-size:20px; color:#2c3e50; }
.filters { display:flex; gap:10px; align-items:center; margin-bottom:16px; flex-wrap:wrap; }
select, input[type="date"], input[type="text"] { padding:9px 10px; border:1px solid #dde3ea; border-radius:8px; background:#fff; }
button.btn { padding:9px 12px; border-radius:8px; border:none; cursor:pointer; font-weight:700; }
.stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:12px; margin-bottom:18px; } /* Inayos na 'to */
.stat-card { background:#fff; border:1px solid #e6e9ee; border-radius:10px; padding:14px; text-align:center; }
.stat-card h3 { margin-bottom:6px; font-size:22px; color:#21303a; }
.stat-card p { color:#6b7f86; font-size:13px; }
.action-btn { padding:8px 12px; border-radius:8px; border:none; color:#fff; font-weight:700; cursor:pointer; font-size:13px; transition:all .2s; }
.action-btn:hover { transform:translateY(-1px); box-shadow:0 4px 8px rgba(0,0,0,0.15); }
.accept { background:#16a34a; }
.cancel { background:#dc2626; }
.view { background:#1d4ed8; }
.edit { background:#f59e0b; }
.detail-overlay, .confirm-modal { display: none; position: fixed; inset: 0; background: rgba(2, 12, 20, 0.6); z-index: 3000; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px); }
.detail-overlay.show, .confirm-modal.show { display: flex; animation: fadeIn .2s ease; }
@keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
.detail-card, .confirm-card { max-width: 96%; background: #fff; border-radius: 16px; padding: 0; box-shadow: 0 20px 60px rgba(8, 15, 30, 0.25); animation: slideUp .3s ease; }
.detail-card { width: 700px; max-width: 96%; } /* Inayos para sa mobile */
.confirm-card { width: 440px; max-width: 96%; padding: 24px; } /* Inayos para sa mobile */
@keyframes slideUp { from { transform:translateY(20px); opacity:0; } to { transform:translateY(0); opacity:1; } }
.detail-header { background: linear-gradient(135deg, #991010 0%, #6b1010 100%); padding: 24px 28px; border-radius: 16px 16px 0 0; display: flex; justify-content: space-between; align-items: center; }
.detail-title { font-weight: 800; color: #fff; font-size: 22px; display: flex; align-items: center; gap: 10px; }
.detail-id { background: rgba(255, 255, 255, 0.2); color: #fff; padding: 6px 14px; border-radius: 20px; font-weight: 700; font-size: 14px; }
.detail-title:before { content: '📋'; font-size: 24px; }
.detail-content { padding: 28px; display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
.detail-section { display: flex; flex-direction: column; gap: 18px; }
.detail-row { background: #f8f9fb; padding: 14px 16px; border-radius: 10px; border: 1px solid #e8ecf0; }
.detail-label { font-weight: 700; color: #4a5568; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 8px; }
.detail-value { color: #1a202c; font-weight: 600; font-size: 15px; }
.detail-notes { background: #fff9e6; border: 1px solid #ffeaa7; padding: 14px 16px; border-radius: 10px; color: #856404; font-size: 14px; line-height: 1.5; grid-column: 1 / -1; }
.badge { display: inline-block; padding: 8px 16px; border-radius: 20px; font-weight: 800; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
.badge.pending { background: #fff4e6; color: #a66300; border: 2px solid #ffd280; }
.badge.accepted { background: #dcfce7; color: #16a34a; border: 2px solid #86efac; }
.badge.cancelled { background: #fee; color: #dc2626; border: 2px solid #fca5a5; }
.badge.completed { background: #e0e7ff; color: #4f46e5; border: 2px solid #a5b4fc; }

/* BAGO: Dinagdag ang CSS para sa 'Missed' at 'Cancel' na badge */
.badge.missed { background: #fee; color: #dc2626; border: 2px solid #fca5a5; }
.badge.cancel { background: #fee; color: #dc2626; border: 2px solid #fca5a5; }


.detail-actions, .confirm-actions { padding: 20px 28px; background: #f8f9fb; border-radius: 0 0 16px 16px; display: flex; gap: 10px; justify-content: flex-end; border-top: 1px solid #e8ecf0; }
.btn-small { padding: 10px 18px; border-radius: 8px; border: none; cursor: pointer; font-weight: 700; font-size: 14px; transition: all .2s; }
.btn-small:hover { transform: translateY(-1px); }
.btn-close { background: #fff; color: #4a5568; border: 2px solid #e2e8f0; }
.btn-accept { background: linear-gradient(135deg, #16a34a, #15803d); color: #fff; box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3); }
.btn-cancel { background: linear-gradient(135deg, #dc2626, #b91c1c); color: #fff; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3); }
.btn-edit { background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3); }
.confirm-header { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
.confirm-icon { width: 56px; height: 56px; border-radius: 12px; background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 28px; flex: 0 0 56px; }
/* BAGO: Idinagdag para sa cancel button prompt */
.confirm-icon.danger { background: linear-gradient(135deg, #dc2626, #b91c1c); }
#reasonInputWrapper { margin-bottom: 20px; }
#cancelReasonInput {
    width: 100%;
    padding: 10px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    font-size: 14px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    resize: vertical;
    min-height: 80px;
}
#cancelReasonInput:focus {
    border-color: #991010;
    outline: none;
    box-shadow: 0 0 0 3px rgba(153, 16, 16, 0.2);
}


.confirm-title { font-weight: 800; color: #1a202c; font-size: 20px; }
.confirm-msg { color: #4a5568; font-size: 15px; line-height: 1.6; margin-bottom: 20px; }
#editModal .detail-title:before { content: '✏️'; }
#editModal .detail-card { width: 500px; }
#editModal .detail-content { padding: 28px; display: block; }
#editModal .detail-row { margin-bottom: 20px; }
#editModal select { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px; font-weight: 600; margin-top: 10px; }

/* BAGO: Pinalitan ang toast. Gagamitin na ang overlay. */
.toast-overlay {
    position: fixed;
    inset: 0;
    background: rgba(34, 49, 62, 0.6); 
    z-index: 9998;
    display: none; /* Hidden by default */
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease-out;
    backdrop-filter: blur(4px);
}
.toast-overlay.show {
    display: flex;
    opacity: 1;
}
.toast {
    background: #fff;
    color: #1a202c;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    z-index: 9999;
    display: flex;
    align-items: center;
    gap: 16px;
    font-weight: 600;
    min-width: 300px;
    max-width: 450px;
    text-align: left;
    animation: slideUp .3s ease; 
}
.toast-icon {
    font-size: 24px;
    font-weight: 800;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: #fff;
}
.toast-message {
    font-size: 15px;
    line-height: 1.5;
}
.toast.success { 
    border-top: 4px solid #16a34a;
}
.toast.success .toast-icon {
    background: #16a34a; 
}
.toast.error { 
    border-top: 4px solid #dc2626;
}
.toast.error .toast-icon {
    background: #dc2626;
}
/* ----- END TOAST ----- */
@media (max-width: 900px) { .detail-content { grid-template-columns: 1fr; } }
@media (max-width: 600px) { .filters { flex-direction: column; align-items: stretch; } }
#loader-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(4px);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}
.loader-content {
    text-align: center;
}
.loader-spinner {
    width: 50px;
    height: 50px;
    border: 5px solid #f3f3f3; 
    border-top: 5px solid #991010; 
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 15px;
}
#loader-text {
    color: #333;
    font-weight: 600;
    font-size: 16px;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* --- BAGO: Mobile Navigation Toggle --- */
#menu-toggle {
  display: none; /* Hidden on desktop */
  background: #f1f5f9;
  border: 2px solid #e2e8f0;
  color: #334155;
  font-size: 24px;
  padding: 5px 12px;
  border-radius: 8px;
  cursor: pointer;
  margin-left: 10px;
  z-index: 2100; 
}


/* --- BAGO: Responsive Media Query --- */
@media (max-width: 1000px) {
  .vertical-bar {
    display: none; /* Itago ang vertical bar */
  }
  header {
    padding: 12px 20px; /* Alisin ang left padding */
    justify-content: space-between; /* I-space out ang logo at toggle */
  }
  .logo-section {
    margin-right: 0; /* Alisin ang auto margin */
  }
  .dashboard, .container, footer { /* BAGO: Isinama ang dashboard at footer */
    padding: 20px; /* Alisin ang left padding */
  }
  
  #menu-toggle {
    display: block; /* Ipakita ang hamburger button */
  }

  /* Itago ang original nav, gawing mobile nav */
  nav#main-nav {
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(20, 0, 0, 0.9); /* Mas madilim na background */
    backdrop-filter: blur(5px);
    z-index: 2000; /* Mataas sa header */
    padding: 80px 20px 20px 20px;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
  }

  nav#main-nav.show {
    opacity: 1;
    visibility: visible;
  }

  nav#main-nav a {
    color: #fff;
    font-size: 24px;
    font-weight: 700;
    padding: 15px;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,0.2);
  }
  
  nav#main-nav a:hover {
      background: rgba(255,255,255,0.1);
  }
  
  nav#main-nav a.active {
    background: none; /* Alisin ang red background sa mobile view */
    color: #ff6b6b; /* Ibahin ang kulay ng active link */
  }

  /* BAGO: Ayusin ang layout ng charts at stats */
  .stats {
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  }
  .charts-grid, .bottom-section, .right-section {
    grid-template-columns: 1fr; /* Stack everything vertically */
  }
  .welcome-section {
    flex-direction: column;
    align-items: flex-start;
    gap: 15px;
  }
}

/* =================================== */
/* <-- BAGO: CSS PARA SA QR SCANNER MODAL
/* =================================== */
.qr-modal-overlay {
    display: none; /* Naka-tago by default */
    position: fixed;
    inset: 0;
    background: rgba(2, 12, 20, 0.6); /* Semi-transparent background */
    z-index: 4000; /* Mataas para nasa ibabaw ng lahat */
    align-items: center;
    justify-content: center;
    padding: 20px;
    backdrop-filter: blur(4px); /* Ito 'yung blur effect */
    animation: fadeIn .2s ease; /* Galing sa existing styles mo */
}

.qr-modal-content {
    background: #fff;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 20px 60px rgba(8, 15, 30, 0.25);
    animation: slideUp .3s ease; /* Galing sa existing styles mo */
    width: 90%;
    max-width: 500px; /* Pwede mong i-adjust 'to */
    text-align: center;
    position: relative; /* Para sa "X" button */
}

.qr-modal-close {
    position: absolute;
    top: 10px;
    right: 15px;
    background: #f1f5f9;
    border: 2px solid #e2e8f0;
    color: #334155;
    font-size: 20px;
    font-weight: 800;
    line-height: 1;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    cursor: pointer;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.qr-modal-close:hover {
    background: #e2e8f0;
}

.qr-modal-content h3 {
    font-size: 20px;
    color: #1a202c;
    margin-bottom: 8px;
}

.qr-modal-content p {
    font-size: 14px;
    color: #4a5568;
    margin-bottom: 20px;
}

#qr-reader-container {
    width: 100%;
    max-width: 400px; 
    margin: 0 auto;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    overflow: hidden; /* Para 'yung video ay sakto sa box */
}

#qr-reader {
    width: 100%;
    /* Hayaan na ang library ang mag-set ng height */
}
/* --- END QR SCANNER MODAL CSS --- */

/* =================================== */
/* BAGO: CSS PARA SA FULL DETAIL MODAL */
/* =================================== */
.detail-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
}
.detail-row {
    background: #f8f9fb; padding: 12px 14px;
    border-radius: 8px; border: 1px solid #e8ecf0;
}
.detail-row.full-width { grid-column: 1 / -1; }
.detail-label {
    font-size: 11px; font-weight: 700; color: #4a5568;
    text-transform: uppercase; letter-spacing: 0.5px;
    display: block; margin-bottom: 6px;
}
.detail-value {
    color: #1a202c; font-weight: 500; font-size: 15px;
    line-height: 1.4; word-wrap: break-word;
}
.detail-value b { font-weight: 600; }

/* ====================================================================== */
/* **** ITO ANG IDINAGDAG KO (REQUEST #4) **** */
/* ====================================================================== */
/* Fine-tuning para sa Maliliit na Screens (e.g., iPhone SE) */
@media (max-width: 420px) {
    .welcome-text h1 { 
        font-size: 22px; /* Liiitan ang "Welcome back" */
    }
    .card h2 { 
        font-size: 28px; /* Liiitan ang numero sa stats (e.g., "2") */
    }
    .card p {
        font-size: 12px;
    }
    .dashboard, .container, footer {
        padding: 15px; /* Bawasan pa ang padding */
    }
    .filter-group {
        flex-direction: column; /* I-stack ang filter buttons */
        align-items: stretch; /* I-full-width sila */
    }
    .filter-group > div {
        width: 100%;
    }
    .filter-btn {
        width: 100%;
        text-align: center;
    }
    .top-controls {
        width: 100%;
    }
    .right-section, .bottom-section, .charts-grid {
        gap: 10px; /* Bawasan ang gap */
    }
    .card, .chart-box, .recent, .qr-section, .weekly-box {
        padding: 15px; /* Bawasan ang padding sa loob ng cards */
    }
    .qr-code-display {
        width: 120px;
        height: 120px;
    }
}
</style>
</head>
<body>

<div id="page-loader-overlay">
    <div class="loader-spinner-fullpage"></div>
    <p>Loading Dashboard...</p>
</div>


<div id="loader-overlay">
    <div class="loader-content">
        <div class="loader-spinner"></div>
        <p id="loader-text">Loading...</p>
    </div>
</div>

<div id="toast-overlay-global" class="toast-overlay" style="z-index: 10000;">
    </div>


<div class="vertical-bar">
    <div class="circle"></div>
</div>

<div class="popup-overlay" onclick="closePopup()"></div>

<header>
    <div class="logo-section">
        <img src="../photo/LOGO.jpg" alt="Logo"> <strong>EYE MASTER CLINIC</strong>
    </div>
    <button id="menu-toggle" aria-label="Open navigation">☰</button>
    <nav id="main-nav">
        <a href="staff_dashboard.php" class="active">🏠 Dashboard</a>
        <a href="appointment.php">📅 Appointments</a>
        <a href="patient_record.php">📘 Patient Record</a>
        <a href="product.php">💊 Product & Services</a>
        <a href="profile.php">🔍 Profile</a>
    </nav>
</header>

<div class="dashboard">
    <div class="welcome-section">
        <div class="welcome-text">
            <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Staff'); ?></h1>
            <p>Here's what's happening at your clinic today</p>
        </div>
        <div class="top-controls">
            
            <div class="filter-group">
                <div style="position: relative;">
                    <button class="filter-btn" id="yearBtn" onclick="toggleDropdown('yearDropdown')"><?= $filterYear ?></button>
                    <div id="yearDropdown" class="dropdown">
                        <?= $yearDropdownItems ?>
                    </div>
                </div>

                <div style="position: relative;">
                    <button class="filter-btn" id="monthBtn" onclick="toggleDropdown('monthDropdown')"><?= $filterMonth ?></button>
                    <div id="monthDropdown" class="dropdown">
                        <?= $monthDropdownItems ?>
                    </div>
                </div>

                <div style="position: relative;">
                    <button class="filter-btn" id="weekBtn" onclick="toggleDropdown('weekDropdown')"><?= $weekBtnText ?></button>
                    <div id="weekDropdown" class="dropdown">
                        <?= $weekDropdownItems ?>
                    </div>
                </div>
                
                <div style="position: relative;">
                    <button class="filter-btn" id="dayBtn" onclick="toggleDropdown('dayDropdown')"><?= $dayBtnText ?></button>
                    <div id="dayDropdown" class="dropdown">
                        <?= $dayDropdownItems ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="stats">
        <div class="card">
            <p>Total Appointments</p>
            <h2><?= $totalAppointmentsToday ?></h2>
        </div>
        <div class="card">
            <p>Total Patients</p>
            <h2><?= $totalPatients ?></h2>
        </div>
<div class="card">
            <p>Pending Appointments</p>
            <h2><?= $pendingAppointments ?></h2>
        </div>

        <div class="card">
            <p>Missed Appointments</p>
            <h2><?= $missedAppointments ?></h2>
        </div>
        <div class="card">
            <p>Completed Appointments</p>
            <h2><?= $completedToday ?></h2>
        </div>
    </div>

    <div class="charts-grid">
        <div class="chart-box">
            <h3>Appointments Overview</h3>
            <div class="chart-wrapper">
                <canvas id="appointmentsChart"></canvas>
            </div>
        </div>
        
        <div class="chart-box">
            <h3>Appointment Status</h3>
            <div class="chart-wrapper">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>

    <div class="bottom-section">
        <div class="weekly-box">
            <h3>Weekly Appointments Overview</h3>
            <div class="weekly-wrapper">
                <canvas id="weeklyChart"></canvas>
            </div>
        </div>
        
        <div class="right-section">
            <div class="recent">
                <h3>Recent Appointments</h3>
                <?php if (empty($recentAppointments)): ?>
                    <div class="empty-state">No recent appointments</div>
                <?php else: ?>
                    <?php foreach ($recentAppointments as $apt): ?>
                    <div class="recent-item">
                        <div class="recent-item-info">
                            <h4><?= htmlspecialchars($apt['full_name']) ?></h4>
                            <p><?= htmlspecialchars($apt['service_name']) ?> - <?= date('g:i A', strtotime($apt['appointment_date'])) ?></p>
                        </div>
                        <span class="status <?= strtolower($apt['status_name']) ?>">
                            <?= htmlspecialchars($apt['status_name']) ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
              <div class="qr-section">
                <div class="qr-code-display">
    <?php 
        // 1. Find the latest CONFIRMED appointment to show as a test
        $qrTestSql = "SELECT appointment_id FROM appointments 
                      JOIN appointmentstatus s ON appointments.status_id = s.status_id 
                      WHERE s.status_name = 'Confirmed' 
                      ORDER BY appointment_date DESC, appointment_time DESC 
                      LIMIT 1";
        $qrTestResult = $conn->query($qrTestSql);
        
        // Default to a dummy number if no appointments exist yet
        $qrData = "123"; 
        
        if ($qrTestResult && $qrTestResult->num_rows > 0) {
            $qrData = $qrTestResult->fetch_assoc()['appointment_id'];
        }
    ?>
    <!-- 2. Generate QR with ONLY the ID number -->
    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= $qrData ?>" alt="QR Code for Appt #<?= $qrData ?>">
    <p style="font-size:12px; color:#666; margin-top:5px;">Scan to test (ID: <?= $qrData ?>)</p>
</div>
                <button class="scan-btn" onclick="startScan()">Click to Scan</button>
            </div>
        </div>
    </div>
</div>

<div id="popup">
    <div id="popup-header"><h3>Confirmation</h3></div>
    <div id="popup-content"></div>
    <div id="popup-actions" style="display: flex; justify-content: flex-end; gap: 10px;">
        <button id="confirmActionBtn" style="display: none; background: #27ae60;">Confirm</button>
        <button onclick="closePopup()">Close</button>
    </div>
</div>

<!-- ====================================================================== -->
<!-- (Request #2 & #3) IN-UPDATE ANG MODAL NA ITO -->
<!-- ====================================================================== -->
<div class="detail-overlay" id="appointmentDetailModal">
    <div class="detail-card">
        
        <div class="detail-header">
            <!-- REQUEST #3: Idinagdag ang ID na "detail-title" -->
            <div class="detail-title" id="detail-title">
                Appointment Details
            </div>
            <span class="detail-id" id="detail-id">#0</span>
        </div>

        <!-- REQUEST #2: Pinalitan ang laman para maging dynamic -->
        <div id="detailModalBody" style="padding: 24px 28px; max-height: 70vh; overflow-y: auto; font-size: 15px;">
            <!-- Dito ilalagay ng JavaScript ang lahat ng data -->
        </div>

        <div class="detail-actions">
            <input type="hidden" id="modal_appointment_id" value="">
            <button class="btn-small btn-close" onclick="closeAppointmentDetailModal()">Back</button>
            
            <!-- ====================================================================== -->
            <!-- **** REQUEST #1 (CANCEL BUTTON) FIX **** -->
            <!-- Pinalitan ang `updateScannedStatus('Cancel')` -->
            <!-- ====================================================================== -->
            <button class="btn-small btn-cancel" style="background: #dc2626; color: white;" 
                    onclick="promptScannedCancel()">
                Cancel
            </button>
            
            <button class="btn-small btn-accept" style="background: #16a34a; color: white;" 
                    onclick="updateScannedStatus('Completed')">
                Complete
            </button>
        </div>

    </div>
</div>

<div class="qr-modal-overlay" id="qrScannerModal" style="display: none;">
    <div class="qr-modal-content">
        <button class="qr-modal-close" onclick="stopScan()">✕</button>
        <h3>Scan Appointment QR Code</h3>
        <p>Hold the QR code steady in the center of the frame.</p>
        <div id="qr-reader-container">
            <div id="qr-reader"></div>
        </div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- **** REQUEST #1 (CANCEL BUTTON) FIX **** -->
<!-- Idinagdag ang HTML para sa Reason Modal (kinopya mula sa appointment.php) -->
<!-- ====================================================================== -->
<div id="reasonModal" class="confirm-modal" aria-hidden="true" style="z-index: 3001;">
    <div class="confirm-card" role="dialog" aria-modal="true">
        <div class="confirm-header">
            <div class="confirm-icon danger">!</div>
            <div class="confirm-title">Reason for Cancellation</div>
        </div>
        <div class="confirm-msg" id="confirmMsg">
         Please provide a reason for cancelling this appointment. This will be included in the email to the client.
        </div>
        <div id="reasonInputWrapper">
            <textarea id="cancelReasonInput" rows="4" placeholder="Type reason here..."></textarea>
        </div>
        <div class="confirm-actions">
            <button id="reasonBack" class="btn-small btn-close">Back</button>
            <button id="reasonSubmit" class="btn-small btn-cancel">Submit Cancellation</button>
        </div>
    </div>
</div>


<footer>
    © 2025 EyeMaster. All rights reserved.
</footer>

<script>

    // ... (your existing chart code) ...

// ===================================
// === LOADER FUNCTIONS =======
// ===================================
const loaderOverlay = document.getElementById('loader-overlay');
const loaderText = document.getElementById('loader-text');

function showLoader(message = 'Loading...') {
    loaderText.textContent = message;
    loaderOverlay.style.display = 'flex';
}

function hideLoader() {
    loaderOverlay.style.display = 'none';
}

// ===================================
// <-- FIX #5: BAGONG 1-SECOND PAGE LOADER
// ===================================
document.addEventListener('DOMContentLoaded', () => {
    const pageLoader = document.getElementById('page-loader-overlay');
    // Itago ang main content para maiwasan ang "flash"
    const dashboard = document.querySelector('.dashboard');
    if(dashboard) dashboard.style.visibility = 'hidden';

    setTimeout(() => {
        pageLoader.style.opacity = '0'; // Simulan ang fade out
        if(dashboard) dashboard.style.visibility = 'visible'; // Ipakita ang content

        setTimeout(() => {
            pageLoader.style.display = 'none'; // Itago ang loader pagkatapos ng fade
        }, 500); // 0.5s fade duration (tugma sa CSS transition)
    }, 1000); // 1-second delay
});


// Real data from PHP
const dailyData = <?php echo json_encode($dailyData); ?>;
const statusData = <?php echo json_encode($statusData); ?>;
const weeklyData = <?php echo json_encode($weeklyData); ?>;

// Prepare chart data
const dailyLabels = dailyData.length > 0 ? dailyData.map(d => {
    const date = new Date(d.date);
    return (date.getMonth() + 1) + '/' + date.getDate();
}) : ['No Data'];
const dailyValues = dailyData.length > 0 ? dailyData.map(d => parseInt(d.count)) : [0];

const statusLabels = statusData.length > 0 ? statusData.map(s => s.status_name) : ['No Data'];
const statusValues = statusData.length > 0 ? statusData.map(s => parseInt(s.count)) : [1];
const statusColors = statusData.length > 0 ? statusData.map(s => {
    const status = s.status_name.toLowerCase(); 
    if (status.includes('completed') || status.includes('approved')) return '#27ae60';
    if (status.includes('pending')) return '#f39c12';
    if (status.includes('cancel') || status.includes('missed')) return '#e74c3c'; // Idinagdag ang 'missed'
    return '#95a5a6';
}) : ['#e0e0e0'];

const dayMap = {
    'Monday': 'Mon', 'Tuesday': 'Tue', 'Wednesday': 'Wed',
    'Thursday': 'Thu', 'Friday': 'Fri', 'Saturday': 'Sat', 'Sunday': 'Sun'
};

const fixedDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
const weeklyMap = {};
weeklyData.forEach(item => {
    weeklyMap[item.day] = parseInt(item.count);
});

const weeklyLabels = fixedDays.map(day => dayMap[day]);
const weeklyValues = fixedDays.map(day => weeklyMap[day] || 0);

const weeklyColors = weeklyValues.map((val, idx) => idx % 2 === 0 ? '#e74c3c' : '#27ae60');

// Line Chart (Appointments Overview)
const ctx1 = document.getElementById('appointmentsChart').getContext('2d');
new Chart(ctx1, {
    type: 'line',
    data: {
        labels: dailyLabels,
        datasets: [{
            label: 'Appointments',
            data: dailyValues,
            borderColor: '#e74c3c',
            backgroundColor: 'rgba(231, 76, 60, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointBackgroundColor: '#e74c3c'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: '#f0f0f0' }, ticks: { font: { size: 11 } } },
            x: { grid: { display: false }, ticks: { font: { size: 11 } } }
        }
    }
});

// Doughnut Chart (Status)
const ctx2 = document.getElementById('statusChart').getContext('2d');
new Chart(ctx2, {
    type: 'doughnut',
    data: {
        labels: statusLabels,
        datasets: [{
            data: statusValues,
            backgroundColor: statusColors,
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { padding: 15, font: { size: 11 }, usePointStyle: true, pointStyle: 'circle' }
            }
        }
    }
});

// Bar Chart (Weekly Data)
const ctx3 = document.getElementById('weeklyChart').getContext('2d');
new Chart(ctx3, {
    type: 'bar',
    data: {
        labels: weeklyLabels,
        datasets: [{
            label: 'Appointments',
            data: weeklyValues,
            backgroundColor: weeklyColors,
            borderRadius: 6,
            barThickness: 35
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: '#f0f0f0' }, ticks: { font: { size: 10 } } },
            x: { grid: { display: false }, ticks: { font: { size: 10 } } }
        }
    }
});


// Global references
const popup = document.getElementById('popup');
const popupHeader = document.getElementById('popup-header');
const popupContent = document.getElementById('popup-content');
const popupOverlay = document.querySelector('.popup-overlay');
const confirmBtn = document.getElementById('confirmActionBtn');
let html5QrCode = null;

// ===================================
// BAGO: Dalawang klase ng Toast
// ===================================

// Ito yung lilitaw sa gitna ng screen, sa ibabaw ng LAHAT
function showGlobalToast(msg, type = 'success') {
    // 1. Hanapin muna kung may existing overlay
    let overlay = document.getElementById('toast-overlay-global');
    if (!overlay) {
        // Kung wala, create
        overlay = document.createElement('div');
        overlay.id = 'toast-overlay-global';
        overlay.className = 'toast-overlay';
        document.body.appendChild(overlay);
    }
    
    // 2. Create toast box
    const toast = document.createElement('div');
    toast.className = `toast ${type}`; 
    toast.innerHTML = `
        <div class="toast-icon">${type === 'success' ? '✓' : '✕'}</div>
        <div class="toast-message">${msg}</div>
    `;
    
    // 3. Append to overlay
    overlay.innerHTML = ''; // Linisin muna kung may luma
    overlay.appendChild(toast);
    overlay.classList.add('show');
    
    // 4. Auto-remove after 2.5 seconds
    const timer = setTimeout(() => {
        if(overlay) overlay.classList.remove('show');
    }, 2500);
    
    // 5. Allow click-to-close
    overlay.addEventListener('click', () => {
        clearTimeout(timer); // Stop auto-remove if clicked
        if(overlay) overlay.classList.remove('show');
    }, { once: true });
}

// Popup Functions
function openPopup(header, content, isConfirmation = false, callback = null) {
    popupHeader.innerHTML = `<h3>${header}</h3>`;
    popupContent.innerHTML = content;
    
    if (isConfirmation) {
        confirmBtn.style.display = 'block';
        confirmBtn.onclick = () => {
            if (callback) callback();
            closePopup();
        };
    } else {
        confirmBtn.style.display = 'none';
        confirmBtn.onclick = null;
    }

    popup.classList.add('active');
    popupOverlay.classList.add('active');
}

function closePopup() { 
    popup.classList.remove('active');
    popupOverlay.classList.remove('active');
    
    const qrModal = document.getElementById('qrScannerModal');
    if (qrModal && qrModal.style.display !== 'none') {
         stopScan();
    }
}

// Dropdown functionality
function toggleDropdown(id) {
    const dropdown = document.getElementById(id);
    const allDropdowns = document.querySelectorAll('.dropdown');
    
    allDropdowns.forEach(d => {
        if (d.id !== id) d.classList.remove('active');
    });
    
    dropdown.classList.toggle('active');
}

function applyFilter(type, value) {
    showLoader('Applying filter...');
    const url = new URL(window.location);
    url.searchParams.set(type, value);
    
    if (type === 'year') {
        url.searchParams.delete('month');
        url.searchParams.delete('week');
        url.searchParams.delete('day');
    } else if (type === 'month') {
        url.searchParams.delete('week');
        url.searchParams.delete('day');
    } else if (type === 'week') {
        url.searchParams.delete('day');
    }
    
    window.location.href = url.toString();
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.filter-btn') && !e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown').forEach(d => d.classList.remove('active'));
    }
});

function openCloseStoreConfirm() {
    openPopup(
        'Confirm Store Closure',
        '<p style="font-size: 14px;">Are you sure you want to close the store? This action will log you out and mark the store as closed in the system.</p>',
        true,
        closeStore
    );
}

function closeStore() {
    showLoader('Closing store...');
    fetch('close_store.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ action: 'close_store' })
    })
    .then(response => response.json())
    .then(data => {
        hideLoader(); 
        if (data.success) {
            showGlobalToast('Store closed successfully. You will be logged out now.', 'success');
            setTimeout(() => { window.location.href = 'logout.php'; }, 1500);
        } else {
            showGlobalToast(data.message || 'Unknown error', 'error'); 
        }
    })
    .catch(error => {
        hideLoader();
        console.error('Error:', error);
        showGlobalToast('An error occurred while closing the store.', 'error'); 
    });
}

function stopScan() {
    const qrModal = document.getElementById('qrScannerModal');
    if (qrModal) {
        qrModal.style.display = 'none'; // Itago ang modal
    }

    if (html5QrCode) {
        try {
             html5QrCode.stop().then(() => {
                console.log("QR Scanner stopped.");
                const qrReader = document.getElementById('qr-reader');
                if(qrReader) qrReader.innerHTML = '';
             }).catch(err => {
                console.warn('QR scanner stop failed, likely already stopped.', err);
                const qrReader = document.getElementById('qr-reader');
                if(qrReader) qrReader.innerHTML = '';
             });
        } catch (e) {
            console.warn('Error trying to stop QR scanner:', e);
            const qrReader = document.getElementById('qr-reader');
            if(qrReader) qrReader.innerHTML = '';
        }
    }
}


function processScannedData(qrCodeMessage) {
    stopScan(); 
    showLoader('Verifying QR Code...');
    
    fetch('verify_qr.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ qr_code: qrCodeMessage })
    })
    .then(response => response.json())
    .then(data => {
        hideLoader();
        if (data.success && data.data) {
            openAppointmentDetailModal(data); 
        } else {
            showGlobalToast(data.message || 'Patient or appointment not found', 'error');
        }
    })
    .catch(error => {
        hideLoader();
        console.error('Fetch Error:', error);
        showGlobalToast('Error verifying QR code.', 'error');
    });
}


function startScan() {
    const qrModal = document.getElementById('qrScannerModal');
    if (!qrModal) {
        console.error('QR Scanner Modal not found!');
        return;
    }
    qrModal.style.display = 'flex'; 

    const qrReaderId = "qr-reader";
    html5QrCode = new Html5Qrcode(qrReaderId);

    html5QrCode.start(
        { facingMode: "environment" }, 
        { fps: 10, qrbox: { width: 250, height: 250 } }, 
        
        qrCodeMessage => {
            processScannedData(qrCodeMessage);
        },
        errorMessage => {
            // silent
        }
    ).catch(err => {
        console.error('Unable to start scanner:', err);
        stopScan(); 
        showGlobalToast('Unable to access camera. Please check permissions or select the correct one.', 'error');
    });
}


// ======================================================================
// (Request #2 & #3) IN-UPDATE ANG FUNCTION NA ITO
// ======================================================================
function openAppointmentDetailModal(payload) {
    console.log("Opening detail modal for:", payload);
    
    const d = payload.data; 
    const preformatted = payload; 

    // --- 1. Set the hidden ID para sa "Complete" / "Cancel" buttons ---
    document.getElementById('modal_appointment_id').value = d.appointment_id; 

    // --- 2. Fill in header ---
    // REQUEST #3: I-set ang title
    document.getElementById('detail-title').textContent = d.service_name || 'Appointment Details';
    document.getElementById('detail-id').textContent = '#' + d.appointment_id;
    
    // --- 3. Build HTML Body ---
    const modalBody = document.getElementById('detailModalBody');
    modalBody.innerHTML = ''; // Linisin muna

    // Ito 'yung listahan ng lahat ng posibleng data
    const labels = {
        'full_name': 'Patient Name', 'status_name': 'Status', 'service_name': 'Service',
        'staff_name': 'Staff Assigned', 'appointment_date': 'Date', 'appointment_time': 'Time',
        'age': 'Age', 'gender': 'Gender', 'phone_number': 'Phone Number',
        'occupation': 'Occupation', 'suffix': 'Suffix', 'symptoms': 'Symptoms',
        'concern': 'Concern', 'wear_glasses': 'Wears Glasses', 'notes': 'Notes',
        'certificate_purpose': 'Certificate Purpose', 'certificate_other': 'Other Certificate',
        'ishihara_test_type': 'Ishihara Test Type', 'ishihara_purpose': 'Ishihara Purpose',
        'color_issues': 'Color Issues', 'previous_color_issues': 'Previous Color Issues',
        'ishihara_notes': 'Ishihara Notes', 'ishihara_reason': 'Ishihara Reason',
        'consent_info': 'Consent (Info)', 'consent_reminders': 'Consent (Reminders)', 'consent_terms': 'Consent (Terms)',
    };
    
    // Ito ang order kung paano sila lalabas
    const displayOrder = [
        'full_name', 'status_name', 'service_name', 'staff_name',
        'appointment_date', 'appointment_time', 'age', 'gender', 'phone_number',
        'occupation', 'suffix', 'symptoms', 'concern', 'wear_glasses', 'notes',
        'certificate_purpose', 'certificate_other', 'ishihara_test_type',
        'ishihara_purpose', 'color_issues', 'previous_color_issues', 'ishihara_notes', 'ishihara_reason',
        'consent_info', 'consent_reminders', 'consent_terms'
    ];
    
    let contentHtml = '<div class="detail-grid">';
    
    for (const key of displayOrder) {
        // Ipakita lang kung may laman ang data
        if (d.hasOwnProperty(key) && d[key] !== null && d[key] !== '' && d[key] !== '0') {
            let value = d[key];
            const label = labels[key] || key;
            let rowClass = 'detail-row';
            
            // Gawing full-width ang mahahabang text
            if (['notes', 'symptoms', 'concern', 'ishihara_notes'].includes(key)) {
                rowClass += ' full-width';
            }
            
            // I-format ang itsura ng data
            if (key === 'appointment_date') {
                value = preformatted.date; // Gamitin ang pre-formatted galing sa verify_qr
            } else if (key === 'appointment_time') {
                value = preformatted.time; // Gamitin ang pre-formatted galing sa verify_qr
            } else if (key === 'consent_info' || key === 'consent_reminders' || key === 'consent_terms') {
                value = value == 1 ? 'Yes' : 'No';
            } else if (key === 'status_name') {
                value = `<span class="badge ${value.toLowerCase()}">${value}</span>`;
            } else {
                value = `<b>${value}</b>`;
            }
            
            // Idagdag sa HTML
            contentHtml += `
                <div class="${rowClass}">
                    <span class="detail-label">${label}</span>
                    <div class="detail-value">${value}</div>
                </div>
            `;
        }
    }
    contentHtml += '</div>';
    
    modalBody.innerHTML = contentHtml;
    
    // --- 4. Show the modal ---
    document.getElementById('appointmentDetailModal').classList.add('show');
}


function closeAppointmentDetailModal() {
    document.getElementById('appointmentDetailModal').classList.remove('show');
}

// ======================================================================
// (Request #1 & #2) IN-UPDATE ANG FUNCTION NA ITO
// ======================================================================
function updateScannedStatus(newStatus, reason = null) { // BAGO: Tumatanggap na ng 'reason'
    const appointmentId = document.getElementById('modal_appointment_id').value;
    
    if (!appointmentId) {
        showGlobalToast('Error: No Appointment ID found.', 'error');
        return;
    }

    showLoader(`Update Status to ${newStatus}...`);

    const bodyParams = {
        action: 'updateStatus',
        id: appointmentId,
        status_name: newStatus 
    };

    // ======================================================================
    // **** REQUEST #1 (CANCEL BUTTON) FIX ****
    // Gagamitin ang 'reason' galing sa prompt, o ang default
    // ======================================================================
    if (newStatus === 'Cancel') { 
        bodyParams.reason = reason || 'Cancelled by Staff via QR Scan.';
    }

    fetch('appointment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams(bodyParams)
    })
    .then(res => res.json())
    .then(data => {
        hideLoader();
        if (data.success) {
            closeAppointmentDetailModal();
            
            // ======================================================================
            // **** REQUEST #2 (AUTO-UPDATE) FIX ****
            // ======================================================================
            showGlobalToast(`Appointment marked as ${newStatus}. Dashboard will refresh.`, 'success');
            setTimeout(() => {
                location.reload(); 
            }, 2000); // 2 second delay para mabasa ang toast

        } else {
            showGlobalToast(data.message || 'Failed to update status.', 'error');
        }
    })
    .catch(err => {
        hideLoader();
        console.error('Update Error:', err);
        showGlobalToast('Network error. Could not update status.', 'error');
    });
}

// ======================================================================
// **** REQUEST #1 (CANCEL BUTTON) FIX ****
// Idinagdag ang function na ito para tawagin ang reason modal
// ======================================================================
function promptScannedCancel() {
    const modal = document.getElementById('reasonModal');
    const reasonInput = document.getElementById('cancelReasonInput');
    const submitBtn = document.getElementById('reasonSubmit');
    const backBtn = document.getElementById('reasonBack');
    const appointmentId = document.getElementById('modal_appointment_id').value;

    if (!appointmentId) {
        showGlobalToast('Error: No Appointment ID found.', 'error');
        return;
    }

    reasonInput.value = ''; 
    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');

    // TANGGALIN ang lumang listeners para iwas double-click
    // Gagamit tayo ng .onclick para sigurado
    let onKey; 

    function cleanUp() {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        document.removeEventListener('keydown', onKey);
    }

    submitBtn.onclick = () => {
        const reason = reasonInput.value.trim();
        if (reason === '') {
            showGlobalToast('A reason is required to cancel.', 'error');
            return;
        }
        cleanUp();
        // Tawagin ang ating main update function, na may kasamang reason
        updateScannedStatus('Cancel', reason); 
    };

    backBtn.onclick = () => cleanUp();
    
    onKey = (e) => { 
        if (e.key === 'Escape') {
            cleanUp();
            // Tanggalin ang listener
            document.removeEventListener('keydown', onKey);
        }
    };
    // Magdagdag ng bagong listener
    document.addEventListener('keydown', onKey);
}


// ======================================================================
// (Keyboard Scanner Listener)
// ======================================================================
(function() {
    let qrCodeChars = [];
    let lastKeystrokeTime = new Date();

    document.addEventListener('keydown', function(e) {
        
        const activeEl = document.activeElement;
        // BAGO: I-check din ang reason input
        if (activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA' || activeEl.id === 'cancelReasonInput')) {
            return; 
        }
        
        const now = new Date();
        if (now - lastKeystrokeTime > 100) {
            qrCodeChars = []; 
        }
        lastKeystrokeTime = now;

        if (e.key === 'Enter' || e.keyCode === 13) {
            if (qrCodeChars.length > 0) { 
                e.preventDefault(); 
                const qrString = qrCodeChars.join('');
                
                console.log('Handheld Scanner Data:', qrString);
                
                processScannedData(qrString); 
            }
            qrCodeChars = []; 
        } else {
            if (e.key && e.key.length === 1) {
                qrCodeChars.push(e.key);
            }
        }
    });
    
    console.log('Handheld QR scanner listener is active.');
})();

</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const menuToggle = document.getElementById('menu-toggle');
  const mainNav = document.getElementById('main-nav');

  if (menuToggle && mainNav) {
    menuToggle.addEventListener('click', function() {
      mainNav.classList.toggle('show');
      
      if (mainNav.classList.contains('show')) {
        this.innerHTML = '✕'; 
        this.setAttribute('aria-label', 'Close navigation');
      } else {
        this.innerHTML = '☰';
        this.setAttribute('aria-label', 'Open navigation');
      }
    });

    mainNav.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', function() {
        mainNav.classList.remove('show');
        menuToggle.innerHTML = '☰';
        menuToggle.setAttribute('aria-label', 'Open navigation');
      });
    });
  }
});
</script>
<script>
    history.replaceState(null, null, location.href);
    history.pushState(null, null, location.href);
    window.onpopstate = function () {
        history.go(1);
    };
</script>

</body>
</html>