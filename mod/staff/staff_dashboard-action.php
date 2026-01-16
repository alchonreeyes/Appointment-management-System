<?php
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../../config/encryption_util.php';

// ======================================================================
// 1. SESSION CHECK
// ======================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'staff') {
    header('Location: ../../public/login.php'); 
    exit;
}

// ======================================================================
// 2. HELPER FUNCTIONS
// ======================================================================
function checkDataExists($conn_db, $table, $whereClause) {
    if (!$whereClause) return false;
    $sql = "SELECT 1 FROM {$table} a WHERE {$whereClause} LIMIT 1";
    $result = $conn_db->query($sql);
    if (!$result) return false;
    return $result && $result->num_rows > 0;
}

// ======================================================================
// 3. FILTER PARAMETERS SETUP
// ======================================================================
$realCurrentYear = date('Y');
$realCurrentMonth = date('F'); 
$realCurrentDay = date('j');   

$isFirstLoad = !isset($_GET['year']) && !isset($_GET['month']) && !isset($_GET['week']) && !isset($_GET['day']);

if ($isFirstLoad) {
    // Default to "Today" view on first load
    $filterYear = $realCurrentYear;
    $filterMonth = $realCurrentMonth;
    $filterWeek = 'All'; // Default week to All so Day takes precedence
    $filterDay = $realCurrentDay; 
} else {
    // Get parameters or default to 'All' if missing/empty
    $filterYear = isset($_GET['year']) && $_GET['year'] !== '' ? $_GET['year'] : 'All';
    $filterMonth = isset($_GET['month']) && $_GET['month'] !== '' ? $_GET['month'] : 'All';
    $filterWeek = isset($_GET['week']) && $_GET['week'] !== '' ? $_GET['week'] : 'All';
    $filterDay = isset($_GET['day']) && $_GET['day'] !== '' ? $_GET['day'] : 'All';
}

// Helper: Convert Month Name to Number (if not 'All')
$monthNum = ($filterMonth !== 'All') ? date('m', strtotime("1 {$filterMonth} 2024")) : null;

// ======================================================================
// 4. DROPDOWN GENERATION LOGIC
// ======================================================================

// --- A. YEAR DROPDOWN ---
$yearDropdownItems = "<div class=\"dropdown-item " . ($filterYear === 'All' ? 'active' : '') . "\" onclick=\"applyFilter('year', 'All')\">All Years</div>";

// Get Max Year from DB
$sqlMaxYear = "SELECT MAX(YEAR(appointment_date)) as max_year FROM appointments";
$resMax = $conn->query($sqlMaxYear);
$dbMaxYear = ($resMax && $resMax->num_rows > 0) ? intval($resMax->fetch_assoc()['max_year']) : $realCurrentYear;

$startYear = max(intval($realCurrentYear) + 1, $dbMaxYear); 
$endYear = 2023; 
$availableYears = range($startYear, $endYear); 

foreach ($availableYears as $y) {
    $where = "YEAR(a.appointment_date) = '{$y}'";
    $hasData = checkDataExists($conn, 'appointments', $where);
    $dataClass = $hasData ? 'has-data' : '';
    $activeClass = ($filterYear == $y) ? 'active' : '';
    $yearDropdownItems .= "<div class=\"dropdown-item {$activeClass} {$dataClass}\" onclick=\"applyFilter('year', '{$y}')\">{$y}</div>";
}

// --- B. MONTH DROPDOWN ---
$monthDropdownItems = "<div class=\"dropdown-item " . ($filterMonth === 'All' ? 'active' : '') . "\" onclick=\"applyFilter('month', 'All')\">All Months</div>";
$months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

if ($filterYear !== 'All') {
    foreach ($months as $m) {
        $mNumTemp = date('m', strtotime("1 {$m} 2024"));
        $where = "YEAR(a.appointment_date) = '{$filterYear}' AND MONTH(a.appointment_date) = '{$mNumTemp}'";
        $hasData = checkDataExists($conn, 'appointments', $where);
        $dataClass = $hasData ? 'has-data' : '';
        $activeClass = ($filterMonth == $m) ? 'active' : '';
        $monthDropdownItems .= "<div class=\"dropdown-item {$activeClass} {$dataClass}\" onclick=\"applyFilter('month', '{$m}')\">{$m}</div>";
    }
} else {
    // If Year is All, just list months without checking specific data or disable them visually
    foreach ($months as $m) {
        $activeClass = ($filterMonth == $m) ? 'active' : '';
        $monthDropdownItems .= "<div class=\"dropdown-item {$activeClass}\" onclick=\"applyFilter('month', '{$m}')\">{$m}</div>";
    }
}

// --- C. PREPARE DATE OBJECTS (Only if Year & Month are selected) ---
$daysInMonth = 31;
$startDayLoop = 1;
$endDayLoop = 31;
$weekDropdownItems = "<div class=\"dropdown-item " . ($filterWeek === 'All' ? 'active' : '') . "\" onclick=\"applyFilter('week', 'All')\">All Weeks</div>";

if ($filterYear !== 'All' && $filterMonth !== 'All') {
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, intval($monthNum), intval($filterYear));
    $firstDayOfMonth = new DateTime("{$filterYear}-{$monthNum}-01");
    $lastDayOfMonth = new DateTime("{$filterYear}-{$monthNum}-{$daysInMonth}");

    // --- D. WEEK DROPDOWN ---
    $weekCounter = 1;
    $tempDate = clone $firstDayOfMonth;

    while ($tempDate <= $lastDayOfMonth) {
        $startOfWeek = clone $tempDate;
        $endOfWeek = (clone $startOfWeek)->modify('+6 days');
        if ($endOfWeek > $lastDayOfMonth) $endOfWeek = $lastDayOfMonth;

        $strStart = $startOfWeek->format('Y-m-d');
        $strEnd = $endOfWeek->format('Y-m-d');

        // Check data
        $where = "DATE(a.appointment_date) BETWEEN '{$strStart}' AND '{$strEnd}'";
        $hasData = checkDataExists($conn, 'appointments', $where);
        $dataClass = $hasData ? 'has-data' : '';
        $activeClass = ($filterWeek == $weekCounter) ? 'active' : '';

        $weekDropdownItems .= "<div class=\"dropdown-item {$activeClass} {$dataClass}\" onclick=\"applyFilter('week', '{$weekCounter}')\">Week {$weekCounter}</div>";

        // *** LOGIC FOR FILTERING DAYS BASED ON WEEK ***
        // If the user selected THIS specific week, we capture the start/end days for the Day loop
        if ($filterWeek == $weekCounter) {
            $startDayLoop = (int)$startOfWeek->format('j');
            $endDayLoop = (int)$endOfWeek->format('j');
        }

        $tempDate->modify('+1 week');
        $weekCounter++;
        if ($weekCounter > 6) break;
    }
    
    // If "All" weeks is selected, show full month range
    if ($filterWeek === 'All') {
        $startDayLoop = 1;
        $endDayLoop = $daysInMonth;
    }
} else {
    // If Year or Month is "All", simpler week view (or empty)
    $weekDropdownItems = "<div class=\"dropdown-item active\">All Weeks</div>"; 
}

// --- E. DAY DROPDOWN (Dynamic based on Week) ---
$dayDropdownItems = "<div class=\"dropdown-item " . ($filterDay === 'All' ? 'active' : '') . "\" onclick=\"applyFilter('day', 'All')\">All Days</div>";

if ($filterYear !== 'All' && $filterMonth !== 'All') {
    for ($d = $startDayLoop; $d <= $endDayLoop; $d++) {
        $padDay = str_pad($d, 2, '0', STR_PAD_LEFT);
        $checkDate = "{$filterYear}-{$monthNum}-{$padDay}";
        
        $where = "DATE(a.appointment_date) = '{$checkDate}'";
        $hasData = checkDataExists($conn, 'appointments', $where);
        $dataClass = $hasData ? 'has-data' : '';
        $activeClass = ($filterDay == $d) ? 'active' : '';

        $dayDropdownItems .= "<div class=\"dropdown-item {$activeClass} {$dataClass}\" onclick=\"applyFilter('day', '{$d}')\">Day {$d}</div>";
    }
}

// Button Texts
$weekBtnText = ($filterWeek === 'All') ? 'All Weeks' : "Week {$filterWeek}";
$dayBtnText = ($filterDay === 'All') ? 'All Days' : "Day {$filterDay}";


// ======================================================================
// 5. BUILD THE SQL FILTER (Dynamic "All" Logic)
// ======================================================================

$conditions = [];

// 1. Year
if ($filterYear !== 'All') {
    $conditions[] = "YEAR(a.appointment_date) = '{$filterYear}'";
}

// 2. Month
if ($filterMonth !== 'All' && $monthNum) {
    $conditions[] = "MONTH(a.appointment_date) = '{$monthNum}'";
}

// 3. Day (Specific Day overrides Week)
if ($filterDay !== 'All' && $filterMonth !== 'All' && $filterYear !== 'All') {
    $padDay = str_pad($filterDay, 2, '0', STR_PAD_LEFT);
    $selectedDate = "{$filterYear}-{$monthNum}-{$padDay}";
    $conditions[] = "DATE(a.appointment_date) = '{$selectedDate}'";
}
// 4. Week (Only if Day is 'All' and Week is Specific)
elseif ($filterWeek !== 'All' && $filterMonth !== 'All' && $filterYear !== 'All') {
    // Re-calculate the specific week range for SQL
    $wNum = intval($filterWeek);
    $wStart = (clone $firstDayOfMonth);
    if ($wNum > 1) {
        $wStart->modify('+' . ($wNum - 1) . ' weeks');
    }
    $wEnd = (clone $wStart)->modify('+6 days');
    if ($wEnd > $lastDayOfMonth) {
        $wEnd = $lastDayOfMonth;
    }
    $strStart = $wStart->format('Y-m-d');
    $strEnd = $wEnd->format('Y-m-d');
    
    $conditions[] = "DATE(a.appointment_date) BETWEEN '{$strStart}' AND '{$strEnd}'";
}

// Combine conditions. If empty, 1=1 (Show Everything)
$statFilter = count($conditions) > 0 ? implode(' AND ', $conditions) : '1=1';


// ======================================================================
// 6. STATISTICS QUERIES
// ======================================================================

// 1. Total Appointments
$sql1 = "SELECT COUNT(a.appointment_id) FROM appointments a WHERE {$statFilter}";
$totalAppointmentsToday = $conn->query($sql1)->fetch_array()[0] ?? 0;

// 2. Total Patients
$sql3 = "SELECT COUNT(DISTINCT a.full_name) FROM appointments a WHERE {$statFilter}";
$totalPatients = $conn->query($sql3)->fetch_array()[0] ?? 0;

// 3. Pending
$sql5 = "SELECT COUNT(a.appointment_id) 
         FROM appointments a
         JOIN appointmentstatus s ON a.status_id = s.status_id
         WHERE s.status_name = 'Pending' AND {$statFilter}";
$pendingAppointments = $conn->query($sql5)->fetch_array()[0] ?? 0;

// 4. Completed
$sql7 = "SELECT COUNT(a.appointment_id) 
         FROM appointments a
         JOIN appointmentstatus s ON a.status_id = s.status_id
         WHERE (s.status_name = 'Completed' OR s.status_name = 'Approved') AND {$statFilter}";
$completedToday = $conn->query($sql7)->fetch_array()[0] ?? 0;

// 5. Confirmed (REPLACED MISSED WITH CONFIRMED)
$sql_confirmed = "SELECT COUNT(a.appointment_id) 
                FROM appointments a
                JOIN appointmentstatus s ON a.status_id = s.status_id
                WHERE s.status_name = 'Confirmed' AND {$statFilter}";
$confirmedAppointments = $conn->query($sql_confirmed)->fetch_array()[0] ?? 0;

// ======================================================================
// 7. CHART & LIST QUERIES
// ======================================================================

// Daily Chart
$sql_daily = "SELECT DATE(a.appointment_date) AS date, COUNT(a.appointment_id) AS count
              FROM appointments a
              WHERE {$statFilter} 
              GROUP BY DATE(a.appointment_date)
              ORDER BY DATE(a.appointment_date)";
$result_daily = $conn->query($sql_daily);
$dailyData = $result_daily ? $result_daily->fetch_all(MYSQLI_ASSOC) : [];

// Status Chart
$sql_status = "SELECT s.status_name, COUNT(a.appointment_id) AS count 
               FROM appointments a
               JOIN appointmentstatus s ON a.status_id = s.status_id
               WHERE {$statFilter} 
               GROUP BY s.status_name";
$result_status = $conn->query($sql_status);
$statusData = $result_status ? $result_status->fetch_all(MYSQLI_ASSOC) : [];

// Weekly Bar Chart
$sql_weekly = "SELECT DAYNAME(a.appointment_date) AS day, COUNT(a.appointment_id) AS count
               FROM appointments a
               WHERE {$statFilter}
               GROUP BY DAYNAME(a.appointment_date), DAYOFWEEK(a.appointment_date)
               ORDER BY DAYOFWEEK(a.appointment_date)";
$result_weekly = $conn->query($sql_weekly);
$weeklyData = $result_weekly ? $result_weekly->fetch_all(MYSQLI_ASSOC) : [];

// Recent List
$sql_recent = "SELECT a.full_name, ser.service_name, a.appointment_date, a.appointment_time, s.status_name
               FROM appointments a
               LEFT JOIN services ser ON a.service_id = ser.service_id
               LEFT JOIN appointmentstatus s ON a.status_id = s.status_id
               WHERE {$statFilter}
               ORDER BY a.appointment_date DESC, a.appointment_time DESC
               LIMIT 5"; 
$result_recent = $conn->query($sql_recent);
$recentAppointments = $result_recent ? $result_recent->fetch_all(MYSQLI_ASSOC) : [];

// ======================================================================
// 8. FALLBACK DATA
// ======================================================================
if (empty($dailyData)) {
    // If specific date selected, use that. If "All", use today.
    $fallbackDate = ($filterDay !== 'All' && $filterYear !== 'All' && $filterMonth !== 'All') 
        ? "{$filterYear}-{$monthNum}-" . str_pad($filterDay,2,'0',STR_PAD_LEFT) 
        : date('Y-m-d');
        
    $dailyData = [['date' => $fallbackDate, 'count' => 0]];
}
if (empty($statusData)) {
    $statusData = [['status_name' => 'No Data', 'count' => 1]];
}
if (empty($weeklyData)) {
    $weeklyData = [['day' => 'No Data', 'count' => 0]];
}
?>