<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Appointment Success</title>
  <link rel="stylesheet" href="../assets/success.css">
</head>
<body>

<div class="success-container">
  <div class="checkmark-wrapper">
    <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
      <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
      <path class="checkmark-check" fill="none" d="M14 27l7 7 16-16"/>
    </svg>
  </div>

  <h1>Your appointment is done!</h1>
  <p>Thank you for booking with EyeMaster. You’ll receive a confirmation soon.</p>

  <div class="button-group">
    <a href="home.php" class="btn home-btn">🏠 Go to Homepage</a>
    <a href="appointments.php" class="btn view-btn">📅 View My Appointments</a>
  </div>  
</div>

</body>
</html>
