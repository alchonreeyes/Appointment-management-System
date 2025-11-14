<?php
session_start();
// This path assumes 'database.php' is in the 'EYE MASTER' folder,
// and this file is in 'EYE MASTER/admin/'


require_once __DIR__ . '/../database.php';




// ======================================================================
// <-- FIX #1: Ginamit ang tamang Session variables galing sa login.php
// ======================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'staff') {
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
    /* === BAGONG KULAY (BLUE) === */
    border-top: 6px solid #1d4ed8; /* Theme Blue */
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
/* === BAGONG KULAY (BLUE) === */
.vertical-bar {
    position: fixed;
    left: 0;
    top: 0;
    width: 55px;
    height: 100vh;
    background: linear-gradient(180deg, #1d4ed8 0%, #1e40af 100%);
    z-index: 1000;
}
.vertical-bar .circle {
    width: 70px;
    height: 70px;
    background: #2563eb; /* Bright Blue */
    border-radius: 50%;
    position: absolute;
    left: -8px;
    top: 45%;
    transform: translateY(-50%);
    border: 4px solid #1e3a8a; /* Dark Blue */
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
/* === BAGONG KULAY (BLUE) === */
nav a.active {
    background: #2563eb;
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
/* === BAGONG KULAY (BLUE) === */
.close-btn { 
    background: #2563eb; /* Theme Blue */
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
    background: #1e40af; /* Darker Blue */
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
/* === BAGONG KULAY (BLUE) === */
.dropdown-item.active {
    background: #2563eb;
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
    color: #e74c3c; /* Iniwan (Semantic Red) */
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
.cancel, .cancelled, .missed { /* Iniwan (Semantic Red) */
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
/* === BAGONG KULAY (BLUE) === */
.scan-btn:hover {
    color: #1d4ed8;
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
/* === BAGONG KULAY (NEUTRAL) === */
#popup button {
    background: #6c757d; /* Neutral Grey */
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

/* BAGO: CSS PARA SA 3-STEP CLOSURE FORM */
.closure-stepper {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}
.step-item {
    font-size: 13px;
    color: #95a5a6;
    font-weight: 600;
    flex: 1;
    text-align: center;
    padding: 5px;
    border-bottom: 3px solid transparent;
}
/* === BAGONG KULAY (BLUE) === */
.step-item.active {
    color: #1d4ed8;
    border-bottom-color: #1d4ed8;
}
.step-item b {
    font-size: 16px;
    display: block;
}
.closure-form-container {
    padding: 0; /* Inalis ang padding dito */
}
.form-step {
    padding: 0; /* Ang step 1 (calendar) ay may sariling padding */
}
.form-step[data-step="2"],
.form-step[data-step="3"] {
    padding: 15px 5px 5px 5px; /* BAGO: Dinagdag na padding */
}
.closure-nav {
    display: flex;
    justify-content: space-between;
    padding: 15px 5px 0 5px; /* BAGO: Dinagdag na padding */
    border-top: 1px solid #eee;
}
/* Inalis ang lumang .closure-calendar-container */

.closure-form-container input[type="time"],
.closure-form-container input[type="text"] {
    width: 100%;
    padding: 8px 10px; /* Inayos ang padding */
    margin-bottom: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-sizing: border-box;
    font-size: 14px;
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
    padding: 10px 5px 0 5px; /* BAGO: Dinagdag na padding */
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
    margin-right: 10px; /* BAGO: Nagdagdag ng space */
}
.closure-item-info b {
    font-size: 14px;
}
/* BAGO: Ginawang btn-small ang buttons */
.closure-item button {
    margin-left: 5px;
    padding: 4px 8px;
    font-size: 11px;
}
.closure-item .btn-edit { background: #f59e0b; color: #fff; border:none; }
.closure-item .btn-danger { background: #dc3545; color: #fff; border:none; } /* Iniwan (Semantic Red) */
.closure-item .btn-view { background: #1d4ed8; color: #fff; border:none; } /* === BAGONG KULAY (BLUE) === */


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
/* === BAGONG KULAY (BLUE) === */
.calendar-day.selected {
    background: #2563eb;
    color: white;
}
/* BAGO: Inayos ang style ng disabled dates */
.calendar-day.closed, .calendar-day.partial-closed {
    background: #f8d7da; /* Iniwan (Semantic Red) */
    color: #721c24;
    cursor: not-allowed; 
    opacity: 0.7;
    text-decoration: line-through;
}
.calendar-day.open {
    background: #d4edda;
    color: #155724;
    pointer-events: none;
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
.stats { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:12px; margin-bottom:18px; } /* Inayos na 'to */
.stat-card { background:#fff; border:1px solid #e6e9ee; border-radius:10px; padding:14px; text-align:center; }
.stat-card h3 { margin-bottom:6px; font-size:22px; color:#21303a; }
.stat-card p { color:#6b7f86; font-size:13px; }
.action-btn { padding:8px 12px; border-radius:8px; border:none; color:#fff; font-weight:700; cursor:pointer; font-size:13px; transition:all .2s; }
.action-btn:hover { transform:translateY(-1px); box-shadow:0 4px 8px rgba(0,0,0,0.15); }
.accept { background:#16a34a; }
.cancel { background:#dc2626; } /* Iniwan (Semantic Red) */
.view { background:#1d4ed8; } /* === BAGONG KULAY (BLUE) === */
.edit { background:#f59e0b; }
.detail-overlay, .confirm-modal { display: none; position: fixed; inset: 0; background: rgba(2, 12, 20, 0.6); z-index: 3000; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px); }
.detail-overlay.show, .confirm-modal.show { display: flex; animation: fadeIn .2s ease; }
@keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
.detail-card, .confirm-card { max-width: 96%; background: #fff; border-radius: 16px; padding: 0; box-shadow: 0 20px 60px rgba(8, 15, 30, 0.25); animation: slideUp .3s ease; }
.detail-card { width: 700px; max-width: 96%; } /* Inayos para sa mobile */
.confirm-card { width: 440px; max-width: 96%; padding: 24px; } /* Inayos para sa mobile */
@keyframes slideUp { from { transform:translateY(20px); opacity:0; } to { transform:translateY(0); opacity:1; } }
/* === BAGONG KULAY (BLUE) === */
.detail-header { background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%); padding: 24px 28px; border-radius: 16px 16px 0 0; display: flex; justify-content: space-between; align-items: center; }
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
.badge.cancelled { background: #fee; color: #dc2626; border: 2px solid #fca5a5; } /* Iniwan (Semantic Red) */
.badge.completed { background: #e0e7ff; color: #4f46e5; border: 2px solid #a5b4fc; } /* Iniwan (Semantic Blue) */
.detail-actions, .confirm-actions { padding: 20px 28px; background: #f8f9fb; border-radius: 0 0 16px 16px; display: flex; gap: 10px; justify-content: flex-end; border-top: 1px solid #e8ecf0; }
.btn-small { padding: 10px 18px; border-radius: 8px; border: none; cursor: pointer; font-weight: 700; font-size: 14px; transition: all .2s; }
.btn-small:hover { transform: translateY(-1px); }
.btn-close { background: #fff; color: #4a5568; border: 2px solid #e2e8f0; }
.btn-accept { background: linear-gradient(135deg, #16a34a, #15803d); color: #fff; box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3); }
.btn-cancel { background: linear-gradient(135deg, #dc2626, #b91c1c); color: #fff; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3); } /* Iniwan (Semantic Red) */
.btn-edit { background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3); }
.btn-save { background: #28a745; color: #fff; }
.btn-save:hover { background: #218838; }
.confirm-header { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
.confirm-icon { width: 56px; height: 56px; border-radius: 12px; background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 28px; flex: 0 0 56px; }
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
    border-top: 4px solid #dc2626; /* Iniwan (Semantic Red) */
}
.toast.error .toast-icon {
    background: #dc2626; /* Iniwan (Semantic Red) */
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
    /* === BAGONG KULAY (BLUE) === */
    border-top: 5px solid #1d4ed8; 
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
  
  /* === BAGONG KULAY (BLUE) === */
  nav#main-nav a.active {
    background: none; /* Alisin ang background sa mobile view */
    color: #60a5fa; /* Light Blue para kitang-kita */
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
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=EYEMASTER_CLINIC_<?= time() ?>" alt="QR Code">
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

<div class="detail-overlay" id="closureDetailModal">
    <div class="detail-card" style="width: 500px; max-width: 96%;">
        <div class="detail-header">
            <div class="detail-title" id="closureDetailTitle">Closure Details</div>
        </div>
        <div id="closureDetailToastContainer" style="display: none; padding: 20px 28px 0 28px;"></div>
        <div class="detail-content" style="display: block; padding: 28px;">
            <input type="hidden" id="closureDetailId">
            
            <div class="detail-row" style="margin-bottom: 15px;">
                <span class="detail-label">Date</span>
                <input type="text" id="closureDetailDate" readonly disabled style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px; font-weight: 600; background: #eee;">
            </div>
            <div class="detail-row" style="margin-bottom: 15px;">
                <span class="detail-label">Start Time</span>
                <input type="time" id="closureDetailStartTime" readonly style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px; font-weight: 600;">
            </div>
            <div class="detail-row" style="margin-bottom: 15px;">
                <span class="detail-label">End Time</span>
                <input type="time" id="closureDetailEndTime" readonly style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px; font-weight: 600;">
            </div>
            <div class="detail-row">
                <span class="detail-label">Reason</span>
                <input type="text" id="closureDetailReason" readonly style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px; font-weight: 600;">
            </div>
        </div>
        <div class="detail-actions">
            <button id="closureDetailDeleteBtn" class="btn-small btn-danger" onclick="deleteClosureFromDetail()">Remove</button>
            <button id="closureDetailSaveBtn" class="btn-small btn-save" onclick="saveClosureFromDetail()">Save Changes</button>
            <button class="btn-small btn-close" onclick="closeClosureDetailModal()">Close</button>
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


// --- BAGO: CLOSURE SCHEDULING LOGIC (3-STEP) ---
const closureData = []; // To store fetched closures
let currentClosureStep = 1;

function formatTime(time24) {
    if (!time24) return 'N/A';
    const [hours, minutes] = time24.split(':');
    const date = new Date();
    date.setHours(parseInt(hours), parseInt(minutes));
    return date.toLocaleString('en-US', { hour: 'numeric', minute: 'numeric', hour12: true });
}

// BAGO: Function to control steps
function showClosureStep(step) {
    currentClosureStep = step;
    
    // Hide all steps
    document.querySelectorAll('.form-step').forEach(el => el.style.display = 'none');
    // Show current step
    const currentStepEl = document.querySelector(`.form-step[data-step="${step}"]`);
    if(currentStepEl) currentStepEl.style.display = 'block';

    // Update stepper UI
    document.querySelectorAll('.step-item').forEach(el => {
        if (parseInt(el.dataset.step) === step) {
            el.classList.add('active');
        } else {
            el.classList.remove('active');
        }
    });

    // Update navigation buttons
    const backBtn = document.getElementById('closureBackBtn');
    const nextBtn = document.getElementById('closureNextBtn');
    const saveBtn = document.getElementById('closureSaveBtn');

    if (backBtn) backBtn.style.display = (step > 1) ? 'inline-flex' : 'none';
    if (nextBtn) nextBtn.style.display = (step < 3) ? 'inline-flex' : 'none';
    if (saveBtn) saveBtn.style.display = (step === 3) ? 'inline-flex' : 'none';

    // BAGO: I-disable ang Next button sa Step 1 kung walang date
    if (step === 1 && nextBtn) {
        nextBtn.disabled = !document.getElementById('closureDate').value;
    }
}

// BAGO: Navigation function
function navigateClosureStep(direction) {
    // Basic validation before going next
    if (direction > 0) {
        if (currentClosureStep === 1) {
            if (!document.getElementById('closureDate').value) {
                // Gagamitin na natin ang bagong toast
                showToastInPopup('Please select a date from the calendar first.', 'error');
                return;
            }
        }
        if (currentClosureStep === 2) {
             const startTime = document.getElementById('startTime').value;
             const endTime = document.getElementById('endTime').value;
             const timeErrorEl = document.getElementById('timeError');
             if (timeErrorEl) timeErrorEl.style.display = 'none';

             if (!startTime || !endTime) {
                 showToastInPopup('Please set a Start Time and End Time.', 'error');
                 return;
             }
             if (startTime >= endTime) {
                 if(timeErrorEl) {
                     timeErrorEl.textContent = 'End Time must be after Start Time.';
                     timeErrorEl.style.display = 'block';
                 }
                 return;
             }
        }
    }
    showClosureStep(currentClosureStep + direction);
}


function openClosureModal(id = null, date = null) {
    
    // BAGO: Itago ang modal ng scanner kung nakabukas
    const qrModal = document.getElementById('qrScannerModal');
    if (qrModal && qrModal.style.display !== 'none') {
        stopScan(); // Gamitin ang stopScan para maayos na maisara
    }

    // BAGO: 3-step HTML structure
    openPopup(
        id ? 'Edit Closure Schedule' : 'Set Closure Schedule',
        `
        <div id="closureToastContainer" style="display: none; margin-bottom: 15px;"></div>

        <div class="closure-stepper">
            <div class="step-item active" data-step="1"><b>1.</b> Select Date</div>
            <div class="step-item" data-step="2"><b>2.</b> Set Time</div>
            <div class="step-item" data-step="3"><b>3.</b> Add Reason</div>
        </div>

        <div class="closure-form-container">
            <div class="form-step" data-step="1">
                <div id="closureCalendar" class="closure-calendar"></div>
                <input type="hidden" id="closureDate"> </div>
            
            <div class="form-step" data-step="2" style="display: none;">
                <label for="startTime">Start Time (e.g., 08:00):</label>
                <input type="time" id="startTime" required>
                
                <label for="endTime">End Time (e.g., 17:00):</label>
                <input type="time" id="endTime" required>

                <p id="timeError" style="color: #e74c3c; font-size: 12px; margin-top: 5px; display: none;"></p>
            </div>

            <div class="form-step" data-step="3" style="display: none;">
                <label for="reason">Reason:</label>
                <input type="text" id="reason" placeholder="e.g., Staff Training" required>
            </div>
        </div>

        <div class="closure-nav">
            <button id="closureBackBtn" class="btn-small btn-close" onclick="navigateClosureStep(-1)" style="display: none;">Back</button>
            <button id="closureNextBtn" class="btn-small btn-accept" onclick="navigateClosureStep(1)" style="display: none;" disabled>Next</button>
            <button id="closureSaveBtn" class="btn-small btn-save" onclick="saveClosure()" style="display: none;">Save Schedule</button>
        </div>
        
        <input type="hidden" id="closureId" value="${id || ''}">
        
        <h4 style="margin-top: 15px; font-size: 14px; color: #2c3e50;">Current Closures:</h4>
        <div class="closure-list" id="currentClosuresList">Loading...</div>
        `,
        false 
    );

    const today = new Date();
    const initialYear = today.getFullYear();
    const initialMonth = today.getMonth() + 1;
    
    // BAGO: Ginamit ang .then() para siguradong loaded na ang data
    fetchAndDisplayClosures().then(() => {
        renderCalendar(initialYear, initialMonth); // I-render ulit ang calendar na may data
        
        if (id) {
            // --- Simula ng Add Logic (walang ID) ---
            currentClosureStep = 1;
            showClosureStep(1);
            
            const todayDateStr = today.toISOString().slice(0, 10);
            const todayElement = document.querySelector(`.calendar-day[data-date="${todayDateStr}"]`);
            
            // Check kung 'yung araw ngayon ay available
            if (todayElement && !todayElement.classList.contains('empty') && !todayElement.classList.contains('closed') && !todayElement.classList.contains('partial-closed')) {
                // Pre-select today kung available
                todayElement.classList.add('selected');
                document.getElementById('closureDate').value = todayDateStr;
                document.getElementById('closureNextBtn').disabled = false; // Enable ang next button
            }
            // --- Katapusan ng Add Logic ---
            
        } else {
             // Set default state for adding
            currentClosureStep = 1;
            showClosureStep(1);
            
            const todayDateStr = today.toISOString().slice(0, 10);
            const todayElement = document.querySelector(`.calendar-day[data-date="${todayDateStr}"]`);
            
            // Check kung 'yung araw ngayon ay available
            if (todayElement && !todayElement.classList.contains('empty') && !todayElement.classList.contains('closed') && !todayElement.classList.contains('partial-closed')) {
                // Pre-select today kung available
                todayElement.classList.add('selected');
                document.getElementById('closureDate').value = todayDateStr;
                document.getElementById('closureNextBtn').disabled = false; // Enable ang next button
            }
        }
    });
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
    // BAGO: I-re-render na lang sa loob ng fetchAndDisplayClosures
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
            classList += ' empty'; // BAGO: pinalitan ng +=
            clickHandler = '';
        } else {
            if (fullDate === todayDate) classList += ' today';
            
            // BAGO: Inayos ang logic para sa pag-disable ng click
            if (closuresForDay.length > 0) {
                const isFullDay = closuresForDay.some(c => c.start_time === '00:00:00' && c.end_time === '23:59:00');
                if (isFullDay) {
                    classList += ' closed';
                } else {
                    classList += ' partial-closed';
                }
                // Bawal na i-click kapag may closure na
                clickHandler = ''; 
            }
        }

        html += `<div class="${classList}" data-date="${fullDate}" ${clickHandler}>${d}</div>`;
    }

    calendarEl.innerHTML = html + '</div>';
    
    currentCalYear = year;
    currentCalMonth = month;
}

// BAGO: Inayos ang selectDate para mag-check ng class at mag-enable ng button
function selectDate(element, day) {
    // Bawal piliin kung 'empty' or may 'closed' or 'partial-closed' class
    if (element.classList.contains('empty') || element.classList.contains('closed') || element.classList.contains('partial-closed')) {
        return;
    }
    
    document.querySelectorAll('.calendar-day').forEach(d => d.classList.remove('selected'));
    element.classList.add('selected');
    
    const fullDate = element.getAttribute('data-date');
    document.getElementById('closureDate').value = fullDate;

    // I-enable ang Next button
    document.getElementById('closureNextBtn').disabled = false;

    // Awtomatikong pumunta sa Step 2
    navigateClosureStep(1);
}

function fetchAndDisplayClosures() {
    const listEl = document.getElementById('currentClosuresList');
    if (!listEl) return;
    listEl.innerHTML = 'Fetching closures...';

    showLoader('Fetching closures...');

    // BAGO: Nag-return ng Promise para malaman kung kailan tapos
    return fetch(`store_closure_handler.php?action=fetch_closures&year=${currentCalYear}&month_num=${String(currentCalMonth).padStart(2, '0')}`)
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
                
                // BAGO: Logika para sa View/Edit/Remove buttons
                const today = new Date().toISOString().slice(0, 10);
                
                listEl.innerHTML = data.closures.map(c => {
                    let buttonsHTML = '';
                    
                    if (c.closure_date < today) {
                        // Nakalipas na: View button lang
                        buttonsHTML = `<button class="btn-small view btn-view" onclick='openClosureDetailModal(${c.id}, true)'>View</button>`;
                    } else {
                        // Ngayon o sa future: Edit at Remove buttons
                        buttonsHTML = `
                            <button class="btn-small btn-edit" style="background: #f59e0b;" onclick="openClosureDetailModal(${c.id}, false)">Edit</button>
                            <button class="btn-small btn-danger" onclick="deleteClosureConfirm(${c.id}, '${c.closure_date}')">Remove</button>
                        `;
                    }

                    return `
                        <div class="closure-item">
                            <div class="closure-item-info">
                                <b>${c.closure_date}</b> (${formatTime(c.start_time)} - ${formatTime(c.end_time)})
                                <br>${c.reason}
                            </div>
                            <div style="display: flex; gap: 5px;">
                                ${buttonsHTML}
                            </div>
                        </div>
                    `;
                }).join('');
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

// BAGO: Pinalitan ang function na 'to. Gagamitin na ang openClosureDetailModal
function editClosure(id) {
    openClosureDetailModal(id, false); // false = not read-only
}

function deleteClosureConfirm(id, date) {
    openPopup(
        'Confirm Deletion',
        `<p style="font-size: 14px;">Are you sure you want to remove the closure scheduled for <b>${date}</b>?</p>`,
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
            // BAGO: Gagamitin ang global toast
            showGlobalToast(data.message, 'success'); 
            
            // I-refresh ang listahan sa *kabilang* modal (Add/Set modal)
            if (popup.classList.contains('active') && document.getElementById('closureCalendar')) {
                fetchAndDisplayClosures(); 
            }
            // Isara ang 'Edit' modal kung nakabukas
            if (document.getElementById('closureDetailModal').classList.contains('show')) {
                closeClosureDetailModal();
            }
        } else {
            showGlobalToast(data.message, 'error'); 
        }
    })
    .catch(err => {
        hideLoader(); 
        console.error('Fetch error:', err);
        showGlobalToast('Network error while deleting closure.', 'error'); 
    });
}

// BAGO: Inayos para kunin ang data mula sa lahat ng steps
function saveClosure() {
    const id = document.getElementById('closureId').value;
    const date = document.getElementById('closureDate').value;
    const startTime = document.getElementById('startTime').value;
    const endTime = document.getElementById('endTime').value;
    const reason = document.getElementById('reason').value;
    const timeErrorEl = document.getElementById('timeError'); // Ang error message ay nasa step 2 na

    if(timeErrorEl) timeErrorEl.style.display = 'none';

    if (!date || !startTime || !endTime || !reason) {
        showToastInPopup('All fields (Date, Start Time, End Time, Reason) are required.', 'error'); 
        
        // Bumalik sa step kung saan may kulang
        if (!date) showClosureStep(1);
        else if (!startTime || !endTime) showClosureStep(2);
        
        return;
    }
    
    const startHour = parseInt(startTime.split(':')[0]);
    const endHour = parseInt(endTime.split(':')[0]);
    const startPeriod = startHour < 12 ? 'AM' : 'PM';
    const endPeriod = endHour < 12 ? 'AM' : 'PM';
    
    // BAGO: Inayos ang logic para sa AM/PM span
    if (startPeriod === endPeriod && (startHour < 12 && endHour < 12) ) { // Parehong AM
        showClosureStep(2);
        if(timeErrorEl) {
            timeErrorEl.textContent = 'Closure must span across morning and afternoon (e.g., AM to PM).';
            timeErrorEl.style.display = 'block';
        }
        return;
    }
     if (startPeriod === endPeriod && (startHour > 12 && endHour > 12) ) { // Parehong PM
        showClosureStep(2);
        if(timeErrorEl) {
            timeErrorEl.textContent = 'Closure must span across morning and afternoon (e.g., AM to PM).';
            timeErrorEl.style.display = 'block';
        }
        return;
    }
    
    if (startTime >= endTime) {
        showClosureStep(2);
        if(timeErrorEl) {
            timeErrorEl.textContent = 'End Time must be strictly after Start Time.';
            timeErrorEl.style.display = 'block';
        }
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
            // BAGO: Hindi na mag-ko-close ang popup
            showToastInPopup(data.message, 'success');
            
            // I-reset ang form fields
            document.getElementById('closureId').value = '';
            document.getElementById('startTime').value = '';
            document.getElementById('endTime').value = '';
            document.getElementById('reason').value = '';
            document.getElementById('closureDate').value = ''; // I-clear din ang date
            document.getElementById('closureNextBtn').disabled = true;
            
            // I-refresh ang calendar at list
            fetchAndDisplayClosures().then(() => {
                // Bumalik sa step 1
                showClosureStep(1);
            });
            
        } else {
            showToastInPopup(data.message, 'error'); 
        }
    })
    .catch(err => {
        hideLoader(); 
        console.error('Fetch error:', err);
        showToastInPopup('Network error while saving closure.', 'error'); 
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

// === BAGONG KULAY (BLUE) ===
const weeklyColors = weeklyValues.map((val, idx) => idx % 2 === 0 ? '#2563eb' : '#27ae60');

// Line Chart (Appointments Overview)
const ctx1 = document.getElementById('appointmentsChart').getContext('2d');
new Chart(ctx1, {
    type: 'line',
    data: {
        labels: dailyLabels,
        datasets: [{
            label: 'Appointments',
            data: dailyValues,
            // === BAGONG KULAY (BLUE) ===
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointBackgroundColor: '#2563eb'
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

// BAGO: Ito yung lilitaw sa loob MISMO ng closure modal
function showToastInPopup(msg, type = 'success') {
    const container = document.getElementById('closureToastContainer');
    if (!container) {
        // Fallback kung sakaling wala yung container
        showGlobalToast(msg, type);
        return;
    }

    // Gagawa ng toast-like message sa loob ng container
    container.innerHTML = `
        <div class_name="toast-message" style="
            padding: 10px 15px; 
            border-radius: 8px; 
            background: ${type === 'success' ? '#d4edda' : '#f8d7da'}; 
            color: ${type === 'success' ? '#155724' : '#721c24'}; 
            border: 1px solid ${type === 'success' ? '#c3e6cb' : '#f5c6cb'};
            font-weight: 600;
            font-size: 14px;
        ">
            ${type === 'success' ? '✓' : '✕'} ${msg}
        </div>
    `;
    container.style.display = 'block';
    
    // Auto-hide after 3 seconds
    setTimeout(() => {
        if(container) container.style.display = 'none';
    }, 3000);
}


// Ito ang gagamitin ng lahat ng function na HINDI related sa closure modal
function showToast(msg, type = 'success') {
    // Check kung ang closure modal ay nakabukas
    if (popup.classList.contains('active') && document.getElementById('closureCalendar')) {
        // Kung nakabukas, gamitin ang special toast sa loob ng modal
        showToastInPopup(msg, type);
    } else {
        // BAGO: Gamitin na rin ang global toast para consistent
        showGlobalToast(msg, type);
    }
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
    
    // BAGO: Isinama ang pag-stop ng scanner dito
    // para kung i-click man ang overlay, hihinto rin ang scanner
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

// ===================================
// <-- BAGO: Function para isara ang QR Modal
// ===================================
function stopScan() {
    const qrModal = document.getElementById('qrScannerModal');
    if (qrModal) {
        qrModal.style.display = 'none'; // Itago ang modal
    }

    if (html5QrCode) {
        html5QrCode.stop().then(() => {
            console.log("QR Scanner stopped.");
            // Linisin ang laman ng qr-reader para sa susunod na scan
            const qrReader = document.getElementById('qr-reader');
            if(qrReader) qrReader.innerHTML = '';
        }).catch(err => {
            console.error('Error stopping scanner:', err);
            // Kahit nag-error, linisin pa rin
            const qrReader = document.getElementById('qr-reader');
            if(qrReader) qrReader.innerHTML = '';
        });
    }
}

// ===================================
// <-- PALITAN: Inayos ang startScan() para gumamit ng Modal
// ===================================
function startScan() {
    // 1. Ipakita ang bagong modal
    const qrModal = document.getElementById('qrScannerModal');
    if (!qrModal) {
        console.error('QR Scanner Modal not found!');
        return;
    }
    qrModal.style.display = 'flex'; // Ipakita ang overlay

    // 2. Ito 'yung ID ng div sa loob ng modal
    const qrReaderId = "qr-reader";
    
    // 3. Siguraduhin na huminto ang dating instance kung meron man
    if (html5QrCode) {
        html5QrCode.stop().catch(err => console.error('Error stopping previous scanner:', err));
    }
    
    // 4. Gumawa ng bagong instance
    html5QrCode = new Html5Qrcode(qrReaderId);

    // 5. Simulan ang scanner
    html5QrCode.start(
        { facingMode: "environment" }, // Mas prefer ang rear camera
        { fps: 10, qrbox: { width: 250, height: 250 } }, // 'qrbox' ay para sa area, hindi sa actual video size
        
        // --- onSuccess ---
        qrCodeMessage => {
            // 6. Natagpuan ang QR! Itigil ang scanner at isara ang modal
            stopScan(); 
            
            // 7. Ipagpatuloy ang dating logic (verification)
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
                    showGlobalToast(data.message || 'Patient or appointment not found', 'error');
                }
            })
            .catch(error => {
                hideLoader();
                console.error('Fetch Error:', error);
                showGlobalToast('Error verifying QR code.', 'error');
            });
        },
        
        // --- onScanFailure ---
        errorMessage => {
            // Huwag mag-log ng error, para tahimik lang habang naghahanap
        }
    ).catch(err => {
        // 8. Nag-fail simulan (e.g., walang camera, walang permission)
        console.error('Unable to start scanner:', err);
        stopScan(); // Isara ang modal kahit nag-fail
        showGlobalToast('Unable to access camera. Please check permissions.', 'error');
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
        showGlobalToast('Error: No Appointment ID found.', 'error');
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
            showGlobalToast(`Appointment marked as ${newStatus}.`, 'success');
            closeAppointmentDetailModal();
            // You might want to reload the dashboard data here
            // location.reload(); 
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
// <-- END: QR CODE FIX
// ======================================================================

// BAGO: Functions para sa View/Edit Closure Modal
function closeClosureDetailModal() {
    document.getElementById('closureDetailModal').classList.remove('show');
}

function openClosureDetailModal(id, isReadOnly = false) {
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
            
            // Populate data
            document.getElementById('closureDetailId').value = c.id;
            document.getElementById('closureDetailDate').value = c.closure_date;
            document.getElementById('closureDetailStartTime').value = c.start_time.substring(0, 5);
            document.getElementById('closureDetailEndTime').value = c.end_time.substring(0, 5);
            document.getElementById('closureDetailReason').value = c.reason;

            // Get elements
            const startTimeInput = document.getElementById('closureDetailStartTime');
            const endTimeInput = document.getElementById('closureDetailEndTime');
            const reasonInput = document.getElementById('closureDetailReason');
            const saveBtn = document.getElementById('closureDetailSaveBtn');
            const deleteBtn = document.getElementById('closureDetailDeleteBtn');
            const title = document.getElementById('closureDetailTitle');
            const toastContainer = document.getElementById('closureDetailToastContainer');

            toastContainer.style.display = 'none'; // Itago ang toast
            
            if (isReadOnly) {
                // --- VIEW MODE (PAST) ---
                title.textContent = 'View Past Closure';
                startTimeInput.readOnly = true;
                endTimeInput.readOnly = true;
                reasonInput.readOnly = true;
                
                // I-disable ang fields
                startTimeInput.style.background = '#eee';
                endTimeInput.style.background = '#eee';
                reasonInput.style.background = '#eee';

                // Itago ang buttons
                saveBtn.style.display = 'none';
                deleteBtn.style.display = 'none';
            } else {
                // --- EDIT MODE (FUTURE) ---
                title.textContent = 'Edit Closure Schedule';
                startTimeInput.readOnly = false;
                endTimeInput.readOnly = false;
                reasonInput.readOnly = false;

                // I-enable ang fields
                startTimeInput.style.background = '#fff';
                endTimeInput.style.background = '#fff';
                reasonInput.style.background = '#fff';

                // Ipakita ang buttons
                saveBtn.style.display = 'inline-flex';
                deleteBtn.style.display = 'inline-flex';
            }
            
            document.getElementById('closureDetailModal').classList.add('show');
        } else {
            showGlobalToast(data.message || 'Failed to load details.', 'error');
        }
    })
    .catch(err => {
        hideLoader();
        console.error('Fetch error:', err);
        showGlobalToast('Network error. Could not load details.', 'error');
    });
}

// BAGO: Function para mag-save galing sa detail modal
function saveClosureFromDetail() {
    const id = document.getElementById('closureDetailId').value;
    const date = document.getElementById('closureDetailDate').value; // Ito ay readonly
    const startTime = document.getElementById('closureDetailStartTime').value;
    const endTime = document.getElementById('closureDetailEndTime').value;
    const reason = document.getElementById('closureDetailReason').value;

    // 1. Check for empty fields
    if (!startTime || !endTime || !reason) {
        showToastInDetailModal('All fields are required.', 'error');
        return;
    }

    // 2. Check if End Time is after Start Time
    if (startTime >= endTime) {
        showToastInDetailModal('End Time must be after Start Time.', 'error');
        return;
    }
    
    // 3. Check for AM/PM span
    const startHour = parseInt(startTime.split(':')[0]);
    const endHour = parseInt(endTime.split(':')[0]);
    const startPeriod = startHour < 12 ? 'AM' : 'PM';
    const endPeriod = endHour < 12 ? 'AM' : 'PM';
    
    if (startPeriod === endPeriod && (startHour < 12 && endHour < 12) ) { // Parehong AM
         showToastInDetailModal('Closure must span across morning and afternoon (e.g., AM to PM).', 'error');
         return;
    }
    if (startPeriod === endPeriod && (startHour > 12 && endHour > 12) ) { // Parehong PM (13:00 to 23:00)
        showToastInDetailModal('Closure must span across morning and afternoon (e.g., AM to PM).', 'error');
        return;
    }
    
    showLoader('Saving changes...');
    
    fetch('store_closure_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'save_closure', // Same action, different source
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
            showToastInDetailModal(data.message, 'success');
            // I-refresh ang listahan sa *kabilang* modal (Add/Set modal)
            if (popup.classList.contains('active') && document.getElementById('closureCalendar')) {
                fetchAndDisplayClosures();
            }
        } else {
            showToastInDetailModal(data.message, 'error');
        }
    })
    .catch(err => {
        hideLoader();
        console.error('Fetch error:', err);
        showToastInDetailModal('Network error while saving.', 'error');
    });
}

// BAGO: Function para mag-delete galing sa detail modal
function deleteClosureFromDetail() {
    const id = document.getElementById('closureDetailId').value;
    const date = document.getElementById('closureDetailDate').value;
    
    // Isara muna ang detail modal
    closeClosureDetailModal();
    
    // Buksan ang confirmation modal
    openPopup(
        'Confirm Deletion',
        `<p style="font-size: 14px;">Are you sure you want to remove the closure scheduled for <b>${date}</b>?</p>`,
        true,
        () => deleteClosure(id) // Ang deleteClosure() ay mag-h-handle ng global toast at refresh
    );
}

// BAGO: Function para sa toast sa loob ng edit/view modal
function showToastInDetailModal(msg, type = 'success') {
    const container = document.getElementById('closureDetailToastContainer');
    if (!container) return;

    container.innerHTML = `
        <div class_name="toast-message" style="
            padding: 10px 15px; 
            border-radius: 8px; 
            background: ${type === 'success' ? '#d4edda' : '#f8d7da'}; 
            color: ${type === 'success' ? '#155724' : '#721c24'}; 
            border: 1px solid ${type === 'success' ? '#c3e6cb' : '#f5c6cb'};
            font-weight: 600;
            font-size: 14px;
        ">
            ${type === 'success' ? '✓' : '✕'} ${msg}
        </div>
    `;
    container.style.display = 'block';
    
    setTimeout(() => {
        if(container) container.style.display = 'none';
    }, 3000);
}


</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const menuToggle = document.getElementById('menu-toggle');
  const mainNav = document.getElementById('main-nav');

  if (menuToggle && mainNav) {
    menuToggle.addEventListener('click', function() {
      mainNav.classList.toggle('show');
      
      // Palitan ang icon ng button
      if (mainNav.classList.contains('show')) {
        this.innerHTML = '✕'; // Close icon
        this.setAttribute('aria-label', 'Close navigation');
      } else {
        this.innerHTML = '☰'; // Hamburger icon
        this.setAttribute('aria-label', 'Open navigation');
      }
    });

    // Isara ang menu kapag pinindot ang isang link
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

</body>
</html>