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
  <h2 style="color: blue; font-size:30px;">Choose your provider & time</h2>
  <p style="color: black;">Browse through the list of providers and check their upcoming appointment availability with just a glance. If you need more details, like specific time slots, just click on their name in the table.</p>

  <!-- Native Date Picker (syncs with strip) -->
  <label>Select Date</label>
  <input type="date" id="nativeDate">

  <!-- Date Strip -->
   
  <div class="date-strip">
    <button data-date="2025-09-09">Mon 09 Sep</button>
    <button data-date="2025-09-10">Tue 10 Sep</button>
    <button data-date="2025-09-11">Wed 11 Sep</button>
    <button data-date="2025-09-12">Thu 12 Sep</button>
    <button data-date="2025-09-13">Fri 13 Sep</button>
    <button data-date="2025-09-14">Sat 14 Sep</button>
    <button data-date="2025-09-15">Sun 15 Sep</button>
  </div>

  <!-- Time Slots -->
  <div class="time-slots">
    <button data-time="10:00 AM">10:00 AM</button>
    <button data-time="11:00 AM">11:00 AM</button>
    <button data-time="1:30 PM">1:30 PM</button>
    <button data-time="2:30 PM">2:30 PM</button>
    <button data-time="3:30 PM">3:30 PM</button>
    <button data-time="4:30 PM">4:30 PM</button>
    <button data-time="5:30 PM">5:30 PM</button>
  </div>

  <!-- Next Available -->
  <div class="next-available" id="nextAvailable">
    Next Available: —
  </div>

  <!-- Hidden Inputs for PHP -->
  <input type="hidden" name="appointment_date" id="appointmentDate">
  <input type="hidden" name="appointment_time" id="appointmentTime">

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

<script src="../actions/appointment.js"></script>
<?php include '../includes/footer.php'; ?>
</body>
</html>
