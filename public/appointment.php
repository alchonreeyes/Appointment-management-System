<?php
session_start();
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

<div class="appointment-container">
  <div class="header">
    <h4>eye glasses exam form</h4>
    <h4>Request an Appointment</h4>
  </div>
  <div class="gray-line"></div>
  <form action="../actions/appointment-action.php" method="POST" id="appointmentForm">

        <!-- Step 1: Patient Info -->
        <div class="form-step active">
            <h3>Patient’s Information</h3>
            <div class="row">
                <input type="text" name="full_name" placeholder="Enter Your Name" required>
                <input type="text" name="suffix" placeholder="Suffix (Optional)">
            </div>
            <div class="row">
                <select name="gender" required>
                    <option value="">Select Gender</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
                <input type="number" name="age" placeholder="Enter Your Age" required>
            </div>
            <div class="row">
                <input type="text" name="phone_number" placeholder="Contact Number" required>
                <input type="text" name="occupation" placeholder="Occupation">
            </div>
            <button type="button" class="next-btn">Next</button>
        </div>

        <!-- Step 2: Choose Provider & Time -->
        <div class="form-step">
            <h3>Choose Provider & Time</h3>
            <label>Date</label>
            <input type="date" name="appointment_date" required>
            <label>Time</label>
            <input type="time" name="appointment_time" required>
            <button type="button" class="prev-btn">Back</button>
            <button type="button" class="next-btn">Next</button>
        </div>

        <!-- Step 3: Symptoms -->
        <div class="form-step">
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

<script src="../actions/appointment.js"></script>
<?php include '../includes/footer.php'; ?>
</body>
</html>
