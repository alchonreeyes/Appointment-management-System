<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
  // Not logged in → redirect back to login
  header("Location: login.php");
  exit;
}


?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment</title>
    <link rel="stylesheet" href="../assets//appointment.css">
    <link rel="stylesheet" href="../assets/popup.css">

</head>
<body>
  
  <?php include '../includes/navbar.php'; ?>
<div class="form-container">
     <div class="header">
            <h4>eye glasses exam form</h4>
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
            <!-- Step 1: Patient Info -->
             <input type="hidden" name="service_id" value="6">

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
        
<!-- STEP 2: Choose Provider & Time -->
<div class="form-step">
  <h2>Choose provider & time</h2>

  <label>Select 3 Appointment Dates</label>
  <input type="date" id="multiDate1" name="appointment_dates[]" required>
  <input type="date" id="multiDate2" name="appointment_dates[]" required>
  <input type="date" id="multiDate3" name="appointment_dates[]" required>

  <div class="time-slots">
    <button type="button" data-time="10:00">10:00 AM</button>
    <button type="button" data-time="11:00">11:00 AM</button>
    <button type="button" data-time="13:30">1:30 PM</button>
    <!-- ... -->
  </div>

  <div id="nextAvailable">Please select 3 dates and a time.</div>

  <input type="hidden" id="appointment_dates_json" name="appointment_dates_json">
  <input type="hidden" id="appointmentTime" name="appointment_time">

  <button type="button" class="prev-btn">Back</button>
  <button type="button" class="next-btn">Next</button>
</div>



        
        <!-- Step 3: Symptoms -->
        <div class="form-step">
            <h2 style="color: blue; font-size:30px;">Choose your provider & time</h2>
  <p style="color: black;">Browse through the list of providers and check their upcoming appointment availability with just a glance. If you need more details, like specific time slots, just click on their name in the table.</p>

            <h3>Eye Health Information</h3>
            <p>Do you currently wear Eye Glasses or Contact Lenses?</p>
            <label><input type="radio" name="wear_glasses" value="Yes"> Yes</label>
            <label><input type="radio" name="wear_glasses" value="No"> No</label>
            
            <p>Are you experiencing any eye discomfort?</p>
            <label><input type="checkbox" name="symptoms[]" value="Blurred Vision"> Blurred Vision</label>
            <label><input type="checkbox" name="symptoms[]" value="Headache"> Headache</label>
            <label><input type="checkbox" name="symptoms[]" value="Redness"> Eye Redness</label>
            <label><input type="checkbox" name="symptoms[]" value="Itchiness"> Itchiness</label>
            <label><input type="checkbox" name="symptoms[]" value="Other"> Other</label>
            <input type="text" name="concern" placeholder="Write a concern...">

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
            <button type="submit" name="submit">Make an Appointment</button>
        </div>

    </form>
</div>
</div>
<!-- Booking Popup Message -->


<script src="../actions/appointment.js"></script>
<?php include '../includes/footer.php'; ?>

</body>
</html>