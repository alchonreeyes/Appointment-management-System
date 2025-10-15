<?php
session_start();
include '../config/db.php';

// // Optional: only allow admin role
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
//     header("Location: ../public/login.php");
// //     exit;
// }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="../assets/admin.css">
</head>
<body>

<?php include '../includes/admin-navbar.php'; ?>

<div class="admin-filter">
  <div class="header">
    <h1>Welcome, Admin</h1>
    <p><?= date('l, F j, Y') ?></p>
  </div>

  <div class="filter">
    <select id="filterType">
      <option value="day">Day</option>
      <option value="week">Week</option>
      <option value="month">Month</option>
      <option value="year">Year</option>
    </select>

    <select id="filterValue">
      <!-- This will dynamically change based on filterType -->
    </select>

    <button id="applyFilter">Apply Filter</button>
  </div>
</div>

<div class="dashboard-containers" id="dashboardData">
  <!-- Filtered results will appear here -->
</div>

<script src="../actions/admin-dashboard.js"></script>

</body>
</html>
