<?php
session_start();
// This path assumes 'database.php' is in the 'EYE MASTER' folder,
// and this file is in 'EYE MASTER/admin/'
require_once __DIR__ . '/../database.php'; 

// ======================================================================
// <-- FIX #1: Ginamit ang tamang Session variables galing sa login.php
// ======================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php'); // Itatapon pabalik sa login kung hindi admin
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

// Format display text
$today_vs_yesterday = $percentChange >= 0 ? "+{$percentChange}% from yesterday" : "{$percentChange}% from yesterday";
$total_patients_vs_last_month = $patientPercentChange >= 0 ? "+{$patientPercentChange}% this month" : "{$patientPercentChange}% this month";
$pending_vs_yesterday = $pendingChange >= 0 ? "+{$pendingChange} from yesterday" : "{$pendingChange} from yesterday";
$completed_rate = "+{$completionRate}% completion rate";
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Eye Master Clinic - Admin Dashboard</title>
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
    height: calc(100vh - 120px);
    overflow-y: auto;
}
.welcome-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
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
    grid-template-columns: repeat(5, 1fr); /* <-- NEW */
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
.cancel, .cancelled { 
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
#qr-reader { 
    width: 100%;
    max-width: 350px;
    margin: 15px auto;
    display: none;
}
#popup {
    display: none;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    z-index: 2000;
    min-width: 350px;
}
#popup h3 {
    margin-bottom: 15px;
    color: #2c3e50;
    font-size: 18px;
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
.closure-calendar-container {
    display: grid;
    grid-template-columns: 1fr;
    gap: 15px;
}
.closure-form-container {
    padding: 10px 0;
}
.closure-form-container input[type="time"],
.closure-form-container input[type="text"] {
    width: 100%;
    padding: 8px;
    margin-bottom: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-sizing: border-box;
}
.closure-form-container label {
    display: block;
    margin-bottom: 5px;
    font-size: 13px;
    font-weight: 600;
}
.closure-list {
    max-height: 150px;
    overflow-y: auto;
    border-top: 1px solid #eee;
    padding-top: 10px;
}
.closure-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 13px;
}
.closure-item-info {
    flex-grow: 1;
    text-align: left;
}
.closure-item-info b {
    font-size: 14px;
}
.closure-item button {
    background: #2196f3;
    margin-left: 5px;
    padding: 4px 8px;
    font-size: 11px;
    border-radius: 4px;
}
.closure-item button.delete {
    background: #e74c3c;
}
.closure-calendar {
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 10px;
    text-align: center;
    background: #fcfcfc;
}
.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    font-weight: 600;
    font-size: 15px;
}
.calendar-header button {
    background: none;
    border: none;
    font-size: 18px;
    cursor: pointer;
    padding: 5px;
}
.calendar-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 5px;
}
.calendar-day-header, .calendar-day {
    font-size: 11px;
    padding: 6px 0;
    line-height: 1;
}
.calendar-day-header {
    color: #7f8c8d;
}
.calendar-day {
    cursor: pointer;
    border-radius: 50%;
    width: 25px;
    height: 25px;
    line-height: 25px;
    margin: 0 auto;
    transition: all 0.2s;
    font-weight: 500;
}
.calendar-day:hover:not(.empty):not(.closed) {
    background: #f0f0f0;
}
.calendar-day.today {
    border: 2px solid #2980b9;
}
.calendar-day.selected {
    background: #dc3545;
    color: white;
}
.calendar-day.closed {
    background: #e74c3c;
    color: white;
    pointer-events: none; 
}
.calendar-day.open {
    background: #d4edda;
    color: #155724;
    pointer-events: none;
}
.calendar-day.partial-closed {
    background: #f39c12;
    color: white;
}
.calendar-day.empty {
    opacity: 0.3;
    cursor: default;
}
.container { padding:20px 20px 40px 75px; max-width:1400px; margin:0 auto; }
.header-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; gap:12px; }
.header-row h2 { font-size:20px; color:#2c3e50; }
.filters { display:flex; gap:10px; align-items:center; margin-bottom:16px; flex-wrap:wrap; }
select, input[type="date"], input[type="text"] { padding:9px 10px; border:1px solid #dde3ea; border-radius:8px; background:#fff; }
button.btn { padding:9px 12px; border-radius:8px; border:none; cursor:pointer; font-weight:700; }
.stats { display:grid; grid-template-columns:repeat(5,1fr); gap:12px; margin-bottom:18px; }
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
.detail-card { width: 700px; } 
.confirm-card { width: 440px; padding: 24px; } 
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
.detail-actions, .confirm-actions { padding: 20px 28px; background: #f8f9fb; border-radius: 0 0 16px 16px; display: flex; gap: 10px; justify-content: flex-end; border-top: 1px solid #e8ecf0; }
.btn-small { padding: 10px 18px; border-radius: 8px; border: none; cursor: pointer; font-weight: 700; font-size: 14px; transition: all .2s; }
.btn-small:hover { transform: translateY(-1px); }
.btn-close { background: #fff; color: #4a5568; border: 2px solid #e2e8f0; }
.btn-accept { background: linear-gradient(135deg, #16a34a, #15803d); color: #fff; box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3); }
.btn-cancel { background: linear-gradient(135deg, #dc2626, #b91c1c); color: #fff; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3); }
.btn-edit { background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3); }
.confirm-header { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
.confirm-icon { width: 56px; height: 56px; border-radius: 12px; background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 28px; flex: 0 0 56px; }
.confirm-title { font-weight: 800; color: #1a202c; font-size: 20px; }
.confirm-msg { color: #4a5568; font-size: 15px; line-height: 1.6; margin-bottom: 20px; }
#editModal .detail-title:before { content: '✏️'; }
#editModal .detail-card { width: 500px; }
#editModal .detail-content { padding: 28px; display: block; }
#editModal .detail-row { margin-bottom: 20px; }
#editModal select { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px; font-weight: 600; margin-top: 10px; }
.toast { position: fixed; bottom: 30px; right: 30px; background: #1a202c; color: #fff; padding: 14px 20px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); z-index: 9999; display: flex; align-items: center; gap: 12px; font-weight: 600; animation: slideIn .3s ease; transition: opacity .3s ease; }
@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
.toast.success { background: linear-gradient(135deg, #16a34a, #15803d); }
.toast.error { background: linear-gradient(135deg, #dc2626, #b91c1c); }
@media (max-width: 900px) { .stats { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); } .detail-content { grid-template-columns: 1fr; } }
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


<div class="vertical-bar">
    <div class="circle"></div>
</div>

<div class="popup-overlay" onclick="closePopup()"></div>

<header>
    <div class="logo-section">
    <img src="/../photo/LOGO.jpg" alt="Logo">
        <strong>EYE MASTER CLINIC</strong>
    </div>
    <nav>
        <a href="admin_dashboard.php" class="active">🏠 Dashboard</a>
        <a href="appointment.php">📅 Appointments</a>
                <a href="patient_record.php">📘 Patient Record</a>
        <a href="product.php">💊 Product & Services</a>
        <a href="account.php">👤 Account</a>
        <a href="profile.php">🔍 Profile</a>
    </nav>
</header>

<div class="dashboard">
    <div class="welcome-section">
        <div class="welcome-text">
            <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></h1>
            <p>Here's what's happening at your clinic today</p>
        </div>
        <div class="top-controls">
<button class="close-btn" onclick="openClosureModal()">🗓️ Set Closure Schedule</button>
            
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
            <span class="change <?= $percentChange < 0 ? 'negative' : '' ?>">
                <?= $today_vs_yesterday ?>
            </span>
        </div>
        <div class="card">
            <p>Total Patients</p>
            <h2><?= $totalPatients ?></h2>
            <span class="change <?= $patientPercentChange < 0 ? 'negative' : '' ?>">
                <?= $total_patients_vs_last_month ?>
            </span>
        </div>
<div class="card">
            <p>Pending Appointments</p>
            <h2><?= $pendingAppointments ?></h2>
            <span class="change <?= $pendingChange < 0 ? 'negative' : '' ?>">
                <?= $pending_vs_yesterday ?>
            </span>
        </div>

        <div class="card">
            <p>Missed Appointments</p>
            <h2><?= $missedAppointments ?></h2>
            <span class="change negative">
                <?= $missedAppointments ?> missed
            </span>
        </div>
        <div class="card">
            <p>Completed Appointments</p>
            <h2><?= $completedToday ?></h2>
            <span class="change">+<?= $completionRate ?>% completion rate</span>
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
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=EYEMASTER_CLINIC_<?= time() ?>" alt="QR Code">
                </div>
                <button class="scan-btn" onclick="startScan()">Click to Scan</button>
                <div id="qr-reader"></div>
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

<div class="detail-overlay" id="appointmentDetailModal">
    <div class="detail-card">
        
        <div class="detail-header">
            <div class="detail-title">
                Appointment Details
                <span class="detail-id" id="detail-id">#0</span>
            </div>
        </div>

        <div class="detail-content">
            
            <div class="detail-section">
                <div class="detail-row">
                    <span class="detail-label">Patient Name</span>
                    <span class="detail-value" id="detail-patient-name">---</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Service Type</span>
                    <span class="detail-value" id="detail-service-type">---</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status</span>
                    <span class="detail-value">
                        <span class="badge" id="detail-status">---</span>
                    </span>
                </div>
            </div>

            <div class="detail-section">
                <div class="detail-row">
                    <span class="detail-label">Appointment Date</span>
                    <span class="detail-value" id="detail-date">---</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Appointment Time</span>
                    <span class="detail-value" id="detail-time">---</span>
                </div>
            </div>

            <div class="detail-notes" id="detail-notes-container">
                <span class="detail-label">Additional Notes</span>
                <span id="detail-notes" style="font-weight: 500;">---</span>
            </div>

        </div>

  <div class="detail-actions">
    <input type="hidden" id="modal_appointment_id" value="">

    <button class="btn-small btn-close" onclick="closeAppointmentDetailModal()">Back</button>
    
    <button class="btn-small btn-cancel" style="background: #dc2626; color: white;" 
            onclick="updateScannedStatus('Cancelled')">
        Cancel
    </button>
    
    <button class="btn-small btn-accept" style="background: #16a34a; color: white;" 
            onclick="updateScannedStatus('Completed')">
        Complete
    </button>
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
// <-- FIX #5: BAGONG 3-SECOND PAGE LOADER
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
        }, 1000); // 0.5s fade duration (tugma sa CSS transition)
    }, 1000); // 3-second delay
});


// --- NEW CLOSURE SCHEDULING LOGIC ---
const closureData = []; // To store fetched closures

function formatTime(time24) {
    const [hours, minutes] = time24.split(':');
    const date = new Date();
    date.setHours(parseInt(hours), parseInt(minutes));
    return date.toLocaleString('en-US', { hour: 'numeric', minute: 'numeric', hour12: true });
}

function openClosureModal(id = null, date = null) {
    document.getElementById('qr-reader').style.display = 'none';

    openPopup(
        id ? 'Edit Closure Schedule' : 'Set Closure Schedule',
        `
        <div class="closure-calendar-container">
            <div id="closureCalendar" class="closure-calendar"></div>
            <div class="closure-form-container">
                <input type="hidden" id="closureId" value="${id || ''}">
                <label for="closureDate">Date:</label>
                <input type="text" id="closureDate" value="${date || ''}" readonly placeholder="Select a date from the calendar" style="font-weight: 600;">
                
                <label for="startTime">Start Time (e.g., 08:00):</label>
                <input type="time" id="startTime" value="" required>
                
                <label for="endTime">End Time (e.g., 17:00):</label>
                <input type="time" id="endTime" value="" required>

                <label for="reason">Reason:</label>
                <input type="text" id="reason" value="" placeholder="e.g., Staff Training" required>
                
                <p id="timeError" style="color: #e74c3c; font-size: 12px; margin-top: 5px; display: none;">End time must be after start time, and time must span AM/PM.</p>
                <button onclick="saveClosure()" style="width: 100%; background: #27ae60; margin-top: 15px;">Save Schedule</button>
            </div>
        </div>
        <h4 style="margin-top: 15px; font-size: 14px; color: #2c3e50;">Current Closures:</h4>
        <div class="closure-list" id="currentClosuresList">Loading...</div>
        `,
        false 
    );

    const today = new Date();
    const initialYear = today.getFullYear();
    const initialMonth = today.getMonth() + 1;
    
    renderCalendar(initialYear, initialMonth);
    fetchAndDisplayClosures();
    
    if (id) {
        fetchClosureDetails(id);
    } else {
        const todayDateStr = today.toISOString().slice(0, 10);
        setTimeout(() => {
            const todayElement = document.querySelector(`.calendar-day[data-date="${todayDateStr}"]`);
            if (todayElement && !todayElement.classList.contains('empty')) {
                selectDate(todayElement, today.getDate());
            }
        }, 0);
    }
}

// Global state for calendar month/year
let currentCalYear = parseInt('<?= $filterYear ?>');
let currentCalMonth = parseInt('<?= $monthNum ?>'); // Month number (1-12)

function changeMonth(delta) {
    currentCalMonth += delta;
    if (currentCalMonth > 12) {
        currentCalMonth = 1;
        currentCalYear++;
    } else if (currentCalMonth < 1) {
        currentCalMonth = 12;
        currentCalYear--;
    }
    renderCalendar(currentCalYear, currentCalMonth);
    fetchAndDisplayClosures(); // Fetch new data for the new month
}

function renderCalendar(year, month) {
    const calendarEl = document.getElementById('closureCalendar');
    if (!calendarEl) return;

    const date = new Date(year, month - 1, 1);
    const monthName = date.toLocaleString('en-US', { month: 'long' });
    const daysInMonth = new Date(year, month, 0).getDate();
    const firstDayOfWeek = date.getDay();
    
    const todayDate = new Date().toISOString().slice(0, 10);

    let html = `<div class="calendar-header">
        <button onclick="changeMonth(-1)">&lt;</button>
        <span>${monthName} ${year}</span>
        <button onclick="changeMonth(1)">&gt;</button>
    </div>
    <div class="calendar-days">
        <div class="calendar-day-header">Sun</div>
        <div class="calendar-day-header">Mon</div>
        <div class="calendar-day-header">Tue</div>
        <div class="calendar-day-header">Wed</div>
        <div class="calendar-day-header">Thu</div>
        <div class="calendar-day-header">Fri</div>
        <div class="calendar-day-header">Sat</div>`;

    let startDay = firstDayOfWeek;
    for (let i = 0; i < startDay; i++) {
        html += '<div class="calendar-day empty"></div>';
    }

    for (let d = 1; d <= daysInMonth; d++) {
        const dayPadded = String(d).padStart(2, '0');
        const monthPadded = String(month).padStart(2, '0');
        const fullDate = `${year}-${monthPadded}-${dayPadded}`;
        
        let classList = 'calendar-day';
        let clickHandler = `onclick="selectDate(this, ${d})"`; 

        const closuresForDay = closureData.filter(c => c.closure_date === fullDate);
        
        if (fullDate < todayDate) {
            classList = 'calendar-day empty';
            clickHandler = '';
        } else {
            if (fullDate === todayDate) classList += ' today';
            
            if (closuresForDay.length > 0) {
                const isFullDay = closuresForDay.some(c => c.start_time === '00:00:00' && c.end_time === '23:59:00');
                classList += isFullDay ? ' closed' : ' partial-closed';
            }
        }

        html += `<div class="${classList}" data-date="${fullDate}" ${clickHandler}>${d}</div>`;
    }

    calendarEl.innerHTML = html + '</div>';
    
    currentCalYear = year;
    currentCalMonth = month;
}

function selectDate(element, day) {
    if (element.classList.contains('empty')) {
        return;
    }
    
    document.querySelectorAll('.calendar-day').forEach(d => d.classList.remove('selected'));
    element.classList.add('selected');
    
    const fullDate = element.getAttribute('data-date');
    document.getElementById('closureDate').value = fullDate;

    document.getElementById('closureId').value = '';
    document.getElementById('startTime').value = '';
    document.getElementById('endTime').value = '';
    document.getElementById('reason').value = '';
    document.getElementById('timeError').style.display = 'none';
}

function fetchAndDisplayClosures() {
    const listEl = document.getElementById('currentClosuresList');
    if (!listEl) return;
    listEl.innerHTML = 'Fetching closures...';

    showLoader('Fetching closures...');

    fetch(`store_closure_handler.php?action=fetch_closures&year=${currentCalYear}&month_num=${String(currentCalMonth).padStart(2, '0')}`)
        .then(res => res.json())
        .then(data => {
            hideLoader(); 
            if (data.success) {
                closureData.length = 0; 
                closureData.push(...data.closures);
                renderCalendar(currentCalYear, currentCalMonth); 
                
                if (data.closures.length === 0) {
                    listEl.innerHTML = '<div class="empty-state">No closures scheduled for this month.</div>';
                    return;
                }
                listEl.innerHTML = data.closures.map(c => `
                    <div class="closure-item">
                        <div class="closure-item-info">
                            <b>${c.closure_date}</b> (${formatTime(c.start_time)} - ${formatTime(c.end_time)})
                            <br>${c.reason}
                        </div>
                        <button onclick="editClosure(${c.id})">Edit</button>
                        <button class="delete" onclick="deleteClosureConfirm(${c.id}, '${c.closure_date}')">Delete</button>
                    </div>
                `).join('');
            } else {
                listEl.innerHTML = '<div class="empty-state" style="color: #e74c3c;">Failed to load closures.</div>';
            }
        })
        .catch(err => {
            hideLoader();
            console.error('Fetch error:', err);
            listEl.innerHTML = '<div class="empty-state" style="color: #e74c3c;">Network error fetching closures.</div>';
        });
}

function fetchClosureDetails(id) {
    showLoader('Loading details...');
    fetch('store_closure_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'fetch_closure_details', id: id })
    })
    .then(res => res.json())
    .then(data => {
        hideLoader(); 
        if (data.success) {
            const c = data.closure;
            document.getElementById('closureId').value = c.id;
            document.getElementById('closureDate').value = c.closure_date;
            document.getElementById('startTime').value = c.start_time.substring(0, 5);
            document.getElementById('endTime').value = c.end_time.substring(0, 5);
            document.getElementById('reason').value = c.reason;
            
            document.querySelectorAll('.calendar-day').forEach(d => d.classList.remove('selected'));
            const dayElement = document.querySelector(`.calendar-day[data-date="${c.closure_date}"]`);
            if (dayElement) dayElement.classList.add('selected');

            document.querySelector('#popup h3').textContent = 'Edit Closure Schedule';

        } else {
            showToast('Failed to load closure details.', 'error'); // FIX: Ginamit ang showToast
        }
    })
    .catch(err => {
        hideLoader(); 
        console.error('Fetch error:', err);
        showToast('Failed to load closure details.', 'error'); // FIX: Ginamit ang showToast
    });
}

function editClosure(id) {
    openClosureModal(id); 
}

function deleteClosureConfirm(id, date) {
    openPopup(
        'Confirm Deletion',
        `<p style="font-size: 14px;">Are you sure you want to delete the closure scheduled for <b>${date}</b>?</p>`,
        true,
        () => deleteClosure(id)
    );
}

function deleteClosure(id) {
    showLoader('Deleting schedule...');
    fetch('store_closure_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete_closure', id: id })
    })
    .then(res => res.json())
    .then(data => {
        hideLoader(); 
        if (data.success) {
            showToast(data.message, 'success'); // FIX: Ginamit ang showToast
            fetchAndDisplayClosures(); 
        } else {
            showToast(data.message, 'error'); // FIX: Ginamit ang showToast
        }
    })
    .catch(err => {
        hideLoader(); 
        console.error('Fetch error:', err);
        showToast('Network error while deleting closure.', 'error'); // FIX: Ginamit ang showToast
    });
}

function saveClosure() {
    const id = document.getElementById('closureId').value;
    const date = document.getElementById('closureDate').value;
    const startTime = document.getElementById('startTime').value;
    const endTime = document.getElementById('endTime').value;
    const reason = document.getElementById('reason').value;
    const timeErrorEl = document.getElementById('timeError');

    timeErrorEl.style.display = 'none';

    if (!date || !startTime || !endTime || !reason) {
        showToast('All fields (Date, Start Time, End Time, Reason) are required.', 'error'); // FIX: Ginamit ang showToast
        return;
    }
    
    const startHour = parseInt(startTime.split(':')[0]);
    const endHour = parseInt(endTime.split(':')[0]);
    const startPeriod = startHour < 12 ? 'AM' : 'PM';
    const endPeriod = endHour < 12 ? 'AM' : 'PM';
    
    if (startPeriod === endPeriod) {
        timeErrorEl.textContent = 'Closure must span across morning and afternoon (e.g., AM to PM).';
        timeErrorEl.style.display = 'block';
        return;
    }
    if (startTime >= endTime) {
        timeErrorEl.textContent = 'End Time must be strictly after Start Time.';
        timeErrorEl.style.display = 'block';
        return;
    }

    showLoader('Saving schedule...');

    fetch('store_closure_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'save_closure',
            id: id,
            date: date,
            start_time: startTime + ':00',
            end_time: endTime + ':00', 
            reason: reason
        })
    })
    .then(res => res.json())
    .then(data => {
        hideLoader(); 
        if (data.success) {
            // Isara ang popup, at buksan ulit pagkatapos
            closePopup();
            setTimeout(() => {
                showToast(data.message, 'success');
                openClosureModal(); // I-refresh ang modal
            }, 500); 
        } else {
            showToast(data.message, 'error'); // FIX: Ginamit ang showToast
        }
    })
    .catch(err => {
        hideLoader(); 
        console.error('Fetch error:', err);
        showToast('Network error while saving closure.', 'error'); // FIX: Ginamit ang showToast
    });
}
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
// <-- FIX #5: Pinalitan ang showToast
// ===================================
function showToast(msg, type = 'success') {
    const title = (type === 'success') ? 'Success' : 'Error';
    // Gagamitin ang existing popup system para sa centered message
    openPopup(title, `<p style="font-size: 16px; line-height: 1.5;">${msg}</p>`);
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
    
    const qrReader = document.getElementById('qr-reader');
    if (html5QrCode && qrReader.style.display === 'block') {
        html5QrCode.stop().then(() => {
            qrReader.style.display = 'none';
        }).catch(err => {
            console.error('Error stopping scanner:', err);
        });
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
            showToast('Store closed successfully. You will be logged out now.', 'success'); // FIX: Ginamit ang showToast
            setTimeout(() => { window.location.href = 'logout.php'; }, 1500);
        } else {
            showToast(data.message || 'Unknown error', 'error'); // FIX: Ginamit ang showToast
        }
    })
    .catch(error => {
        hideLoader();
        console.error('Error:', error);
        showToast('An error occurred while closing the store.', 'error'); // FIX: Ginamit ang showToast
    });
}

function startScan() {
        const qrReader = document.getElementById('qr-reader');
        qrReader.style.display = 'block';
        
        if (html5QrCode) {
            html5QrCode.stop().catch(err => console.error('Error stopping previous scanner:', err));
        }
        html5QrCode = new Html5Qrcode("qr-reader");

        html5QrCode.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            qrCodeMessage => {
                html5QrCode.stop().then(() => {
                    qrReader.style.display = 'none';
                    showLoader('Verifying QR Code...');
                    fetch('verify_qr.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ qr_code: qrCodeMessage })
                    })
                    .then(response => response.json())
                    .then(data => {
                        hideLoader();
                        if (data.success && data.id) {
                            openAppointmentDetailModal(data);
                        } else {
                            showToast(data.message || 'Patient or appointment not found', 'error'); // FIX: Ginamit ang showToast
                        }
                    })
                    .catch(error => {
                        hideLoader();
                        console.error('Fetch Error:', error);
                        showToast('Error verifying QR code.', 'error'); // FIX: Ginamit ang showToast
                    });
                }).catch(err => {
                    console.error('Error stopping scanner:', err);
                    qrReader.style.display = 'none';
                });
            },
            errorMessage => {
                // Ignore scanning errors
            }
        ).catch(err => {
            console.error('Unable to start scanner:', err);
            qrReader.style.display = 'none';
            showToast('Unable to access camera. Please check permissions.', 'error'); // FIX: Ginamit ang showToast
        });
    }

// ======================================================================
// <-- START: QR CODE FIX 
//     I have removed the duplicate 'openAppointmentDetailModal' 
//     and created one single, correct function.
// ======================================================================

function closeAppointmentDetailModal() {
    document.getElementById('appointmentDetailModal').classList.remove('show');
}

// This is the one, correct function that handles opening the modal.
function openAppointmentDetailModal(data) {
    console.log("Opening detail modal for:", data);

    // --- 1. Set the hidden ID for the "Complete" / "Cancel" buttons ---
    document.getElementById('modal_appointment_id').value = data.id; 

    // --- 2. Fill in all the visible modal details ---
    document.getElementById('detail-id').textContent = '#' + data.id;
    document.getElementById('detail-patient-name').textContent = data.patient_name;
    document.getElementById('detail-service-type').textContent = data.service_type;
    document.getElementById('detail-date').textContent = data.date;
    document.getElementById('detail-time').textContent = data.time;
    
    // Set the status badge
    const statusBadge = document.getElementById('detail-status');
    statusBadge.textContent = data.status;
    // Clear old status classes (like 'pending', 'completed') and add the new one
    statusBadge.className = 'badge ' + data.status.toLowerCase(); 

    // Handle the notes
    const notesContainer = document.getElementById('detail-notes-container');
    const notesEl = document.getElementById('detail-notes');
    if (data.notes && data.notes.trim() !== '') {
        notesEl.textContent = data.notes;
        notesContainer.style.display = 'block';
    } else {
        // Hide the notes section if there are no notes
        notesEl.textContent = '---';
        notesContainer.style.display = 'none';
    }
    
    // --- 3. Show the modal ---
    document.getElementById('appointmentDetailModal').classList.add('show');
}


// --- THIS IS THE NEW FUNCTION FOR THE BUTTONS ---
function updateScannedStatus(newStatus) {
    const appointmentId = document.getElementById('modal_appointment_id').value;
    
    if (!appointmentId) {
        showToast('Error: No Appointment ID found.', 'error');
        return;
    }

    showLoader(`Setting status to ${newStatus}...`);

    // We send this to appointment.php because that file already handles status updates
    fetch('appointment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
            action: 'updateStatus', // Assumes your appointment.php has this action
            id: appointmentId,
            status_name: newStatus 
        })
    })
    .then(res => res.json())
    .then(data => {
        hideLoader();
        if (data.success) {
            showToast(`Appointment marked as ${newStatus}.`, 'success');
            closeAppointmentDetailModal();
            // You might want to reload the dashboard data here
            // location.reload(); 
        } else {
            showToast(data.message || 'Failed to update status.', 'error');
        }
    })
    .catch(err => {
        hideLoader();
        console.error('Update Error:', err);
        showToast('Network error. Could not update status.', 'error');
    });
}
// ======================================================================
// <-- END: QR CODE FIX
// ======================================================================
</script>
</body>
</html>