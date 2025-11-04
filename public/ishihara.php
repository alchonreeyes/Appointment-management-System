
<?php
session_start();
// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
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
  <title>Ishihara Test Appointment</title>
  <link rel="stylesheet" href="../assets/appointment.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="form-container">
  <div class="header">
    <h4>Ishihara Test Appointment</h4>
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
      <div class="progress-step">4</div>
    </div>

    <form action="../actions/appointment-action.php" method="POST" id="appointmentForm">
      <input type="hidden" name="service_id" value="8">

      <!-- Step 1: Patient Info -->
      <div class="form-step active">
        <h2>Let's get you scheduled</h2>
        <p style="color:black;">Fill in your details to proceed with the Ishihara color vision test.</p>

        <div class="form-row name-row">
          <input type="text" placeholder="Enter Your Name..." name="full_name" required>
          <select name="suffix">
            <option value="">Suffix (Optional)</option>
            <option value="Jr">Jr</option>
            <option value="Sr">Sr</option>
          </select>
        </div>

        <div class="form-row three-cols">
          <select name="gender" required>
            <option value="">Select Gender...</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
          </select>

          <input type="number" name="age" placeholder="Enter your Age..." required>
          <input type="text" name="contact_number" placeholder="ex: 63+" required>
        </div>

        <div class="form-row single">
          <input type="text" name="occupation" placeholder="Enter your Occupation..." required>
        </div>

        <button type="button" class="next-btn">Next</button>
      </div>

      <!-- Step 2: Ishihara Test Purpose -->
      <div class="form-step">
        <h3 style="color: blue;">Ishihara Test Purpose</h3>
        <p style="color: black;">Select the type of Ishihara test and provide any related details.</p>

        <h5>Ishihara Test Type</h5>
        <label><input type="radio" name="ishihara_test_type" value="Basic Screening" required> Basic Screening</label>
        <label><input type="radio" name="ishihara_test_type" value="Complete Assessment"> Complete Assessment</label>
        <label><input type="radio" name="ishihara_test_type" value="Follow-up"> Follow-up</label>

        <input type="text" name="ishihara_reason" placeholder="Reason for taking the test (optional)" style="width: 90%;">

        <label>Previous color vision issues?</label>
        <select name="previous_color_issues" required>
          <option value="Unknown">Unknown</option>
          <option value="Yes">Yes</option>
          <option value="No">No</option>
        </select>

        <input type="text" name="ishihara_notes" placeholder="Additional notes (optional)" style="width: 90%;">

        <button type="button" class="prev-btn">Back</button>
        <button type="button" class="next-btn">Next</button>
      </div>

      <!-- Step 3: Choose Provider & Time -->
      <div class="form-step">
        <h2 style="color: blue; font-size:30px;">Choose your provider & time</h2>
        <p style="color: black;">Select a suitable date and time for your Ishihara test.</p>

        <label>Select Date</label>
        <input type="date" id="nativeDate">

        <div class="date-strip"></div>

        <div class="time-slots">
          <button type="button" data-time="10:00 AM">10:00 AM</button>
          <button type="button" data-time="11:00 AM">11:00 AM</button>
          <button type="button" data-time="1:30 PM">1:30 PM</button>
          <button type="button" data-time="2:30 PM">2:30 PM</button>
          <button type="button" data-time="3:30 PM">3:30 PM</button>
          <button type="button" data-time="4:30 PM">4:30 PM</button>
          <button type="button" data-time="5:30 PM">5:30 PM</button>
        </div>

        <div class="next-available" id="nextAvailable">Next Available: —</div>

        <input type="hidden" name="appointment_date" id="appointmentDate">
        <input type="hidden" name="appointment_time" id="appointmentTime">

        <button type="button" class="prev-btn">Back</button>
        <button type="button" class="next-btn">Next</button>
      </div>

      <!-- Step 4: Consent -->
      <div class="form-step">
        <h3>Consent & Confirmation</h3>
        <label><input type="checkbox" name="consent_info" value="1" required> I certify that the above information is correct.</label>
        <label><input type="checkbox" name="consent_reminders" value="1"> I consent to receive reminders via SMS or email.</label>
        <label><input type="checkbox" name="consent_terms" value="1" required> I agree to terms & privacy policy.</label>

        <button type="button" class="prev-btn">Back</button>
        <button type="submit" name="submit">Make Appointment</button>
      </div>

    </form>
  </div>
</div>

<script src="../actions/appointment.js"></script>
<?php include '../includes/footer.php'; ?>
</body>
</html>
