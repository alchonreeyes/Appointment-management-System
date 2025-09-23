<?php

session_start();
// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
  // Not logged in → redirect back to login
  header("Location: login.php");
  exit;
}

include '../config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment</title>
    <link rel="stylesheet" href="../assets//appointment.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="form-container">
     <div class="header">
            <h4>Medical Appointment</h4>
            <h4>Request an Appointment</h4>
        </div>
        <div class="gray-line"></div>
    <div class="appointment-container">
     <div class="progress-container">
  <div class="progress-step active">1</div>
  <div class="progress-line"></div>
  <div class="progress-step">2</div>
  <div class="progress-line"></div>
  <div class="progress-step">3</div>
  <div class="progress-line"></div>
</div>

        <form action="../actions/appointment-action.php" method="POST" id="appointmentForm">       
            <!-- Step 1: Patient Info -->
        <div class="form-step active">
              <h2>Let's get you scheduled</h2>

       <p style="color:black;">To get started, simply select the type of appointment you need from our list of options</p>
            
            <div class="form-row name-row">
  <input type="text" placeholder="Enter Your Name..." name="full_name">
  <select name="suffix">
    <option value="">Suffix (Optional)</option>
    <option value="Jr">Jr</option>
    <option value="Sr">Sr</option>
  </select>
</div>

            <div class="form-row three-cols">
  <select name="gender">
    <option value="">Select Gender...</option>
    <option value="Male">Male</option>
    <option value="Female">Female</option>
  </select>

  <input type="number" name="age" placeholder="Enter your Age...">
  <input type="text" name="contact_number" placeholder="ex: 63+">
</div>
<div class="form-row single">
  <input type="text" name="occupation" placeholder="Enter your Occupation...">
</div>

            <button type="button" class="next-btn">Next</button>
        </div>
        
  <!-- Step 2: Choose Provider & Time -->
<div class="form-step">
    <h3>Medical Certificate Purpose</h3>
    <label><input type="radio" name="certificate_purpose" value="Work" required> For Work</label>
    <label><input type="radio" name="certificate_purpose" value="School"> For School</label>
    <label><input type="radio" name="certificate_purpose" value="Travel"> For Travel</label>
    <label><input type="radio" name="certificate_purpose" value="Other"> Other</label>
    <input type="text" name="certificate_other" placeholder="If other, please specify...">
    
    <button type="button" class="prev-btn">Back</button>
    <button type="button" class="next-btn">Next</button>
</div>


        <!-- Step 3: Consent -->
        <div class="form-step">
             <h3>Consent & Confirmation</h3>
    <label><input type="checkbox" name="consent_info" value="1"> I certify that the above information is correct.</label>
    <label><input type="checkbox" name="consent_reminders" value="1"> I consent to receive reminders via SMS or email.</label>
    <label><input type="checkbox" name="consent_terms" value="1" required> I agree to terms & privacy policy.</label><label><input type="checkbox" name="consent_terms" value="1" required> I agree to terms & privacy policy.</label>

            <button type="button" class="prev-btn">Back</button>
            <button type="submit" name="submit">Make a Appointment</button>
        </div>

    </form>
</div>
</div>

<script src="../actions/appointment.js"></script>
<?php include '../includes/footer.php'; ?>
</body>

</html>
