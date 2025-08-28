<?php 


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>appointment</title>
    <link rel="stylesheet" href="../assets/appointment.css">
</head>
<body>
    <?php include '../includes/navbar.php' ?>

    <div class="header">
        <div class="left-side">
            <h4>Eye glasses Exam Form</h4>
        </div>
        
        <div class="right-side">
            <h4>Request an Appointment</h4>
        </div>
</div>
    <div class="appointment-wrapper">
        
        <form action="../actions/appointment-action.php" name="appointment" class="appointment-form">
             <!-- Step 1 -->
  <div class="form-step active">
    <h2>Patient Information</h2>
    <input type="text" name="full_name" placeholder="Enter your Name" required>
    <input type="number" name="age" placeholder="Enter Age" required>
    <input type="tel" name="phone" placeholder="Contact Number" required>
    <button type="button" class="next-btn">Next</button>
  </div>

  <!-- Step 2 -->
  <div class="form-step">
    <h2>Choose Provider & Time</h2>
    <input type="date" name="appointment_date" required>
    <select name="time_slot" required>
      <option value="">-- Select Time --</option>
      <option value="10:00">10:00 AM</option>
      <option value="11:00">11:00 AM</option>
      <option value="13:00">1:00 PM</option>
    </select>
    <button type="button" class="prev-btn">Back</button>
    <button type="button" class="next-btn">Next</button>
  </div>

  <!-- Step 3 -->
  <div class="form-step">
    <h2>Eye Health Information</h2>
    <label><input type="checkbox" name="symptom[]" value="Blurred Vision"> Blurred Vision</label>
    <label><input type="checkbox" name="symptom[]" value="Headache"> Headache</label>
    <label><input type="checkbox" name="symptom[]" value="Other"> Other</label>
    <textarea name="concerns" placeholder="Write a concern..."></textarea>
    <button type="button" class="prev-btn">Back</button>
    <button type="submit" class="submit-btn">Submit</button>
  </div>

        </form>
    </div>



    <?php include '../includes/footer.php' ?>
</body>
</html>