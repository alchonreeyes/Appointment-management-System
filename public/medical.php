
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
  <div class="progress-step">4</div>
</div>
  
        <form action="../actions/appointment-action.php" method="POST" id="appointmentForm">       
            <!-- Step 1: Patient Info -->
             <input type="hidden" name="service_id" value="7">
        <div class="form-step active">
              <h2>Let's get you scheduled</h2>

      <p style="color:black;">To get started, simply select the type of appointment you need from our list of options</p>

      <div class="form-row name-row">
        <input type="text" placeholder="Enter Your Name..." name="full_name">
        <select name="suffix" id="suffix">
          <option value="">Suffix (Optional)</option>
          <option value="Jr">Jr</option>
          <option value="Sr">Sr</option>
          <option value="Other" id="suffix_other">Other</option>
        </select>
        <input type="text" name="suffix" style="display: none;" id="suffix_concern" placeholder="Enter your suffix...">
      </div>
      <script>
        document.getElementById("suffix").addEventListener('change', function() {
          const suffixConcern = document.getElementById("suffix_concern");
          if (this.value === "Other") {
            suffixConcern.style.display = "block";
          } else {
            suffixConcern.style.display = "none";
          }
        });
      </script>
    

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
        
        <!-- Step 2: medical certificate purpose -->
<div class="form-step">
    <h3 style="color: black;">Medical Certificate Purpose</h3>
    <label><input type="radio" name="certificate_purpose" value="Work" required> For Work</label>
    <label><input type="radio" name="certificate_purpose" value="School"> For School</label>
    <label><input type="radio" name="certificate_purpose" value="Travel"> For Travel</label>
    <label><input type="radio" name="certificate_purpose" value="Other" id="cert-other"> Other</label>
    <input type="text" name="certificate_purpose"value="medical purpose" style="display:none;">
    <input type="text" name="certificate_other" placeholder="If other, please specify..." style="display: none;" id="certificate_other">
    
    <button type="button" class="prev-btn">Back</button>
    <button type="button" class="next-btn">Next</button>
</div>
<script>
  document.getElementById("cert-other").addEventListener('change', function() {
    const certificateOther = document.getElementById("certificate_other");

    if(this.checked){
      certificateOther.style.display = "block";
    }
    else{
      certificateOther.style.display = "none";
    }
  })
</script>

  <!-- Step 3: Choose Provider & Time -->
<div class="form-step">
  <h2>Choose provider & time</h2>
  <p style="color: black;">Select up to 3 appointment dates and their corresponding time slots. Each slot can accommodate maximum 3 clients.</p>

  <!-- Appointment 1 -->
  <div class="appointment-row" style="margin-bottom: 20px; padding: 15px; border: 2px solid #e5e7eb; border-radius: 12px; background: #f9fafb;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
      <h4 style="margin: 0; color: #1f2937;">Appointment 1</h4>
      <span class="slot-badge" id="slot-badge-0" style="padding: 4px 12px; background: #e5e7eb; color: #6b7280; border-radius: 20px; font-size: 13px; font-weight: 600;">Select date & time</span>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
      <div>
        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Date:</label>
        <input type="date" class="date-input" data-index="0" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;">
      </div>
      
      <div>
        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Time:</label>
        <select class="time-select" data-index="0" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;">
          <option value="">Select Time</option>
          <option value="10:00">10:00 AM</option>
          <option value="11:00">11:00 AM</option>
          <option value="13:30">1:30 PM</option>
          <option value="14:30">2:30 PM</option>
          <option value="15:30">3:30 PM</option>
          <option value="16:30">4:30 PM</option>
        </select>
      </div>
    </div>
    
    <div class="slot-message" id="slot-message-0" style="margin-top: 10px; padding: 8px; border-radius: 6px; font-size: 14px; display: none;"></div>
  </div>

  <!-- Appointment 2 -->
  <div class="appointment-row" style="margin-bottom: 20px; padding: 15px; border: 2px solid #e5e7eb; border-radius: 12px; background: #f9fafb;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
      <h4 style="margin: 0; color: #1f2937;">Appointment 2</h4>
      <span class="slot-badge" id="slot-badge-1" style="padding: 4px 12px; background: #e5e7eb; color: #6b7280; border-radius: 20px; font-size: 13px; font-weight: 600;">Select date & time</span>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
      <div>
        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Date:</label>
        <input type="date" class="date-input" data-index="1" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;">
      </div>
      
      <div>
        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Time:</label>
        <select class="time-select" data-index="1" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;">
          <option value="">Select Time</option>
          <option value="10:00">10:00 AM</option>
          <option value="11:00">11:00 AM</option>
          <option value="13:30">1:30 PM</option>
          <option value="14:30">2:30 PM</option>
          <option value="15:30">3:30 PM</option>
          <option value="16:30">4:30 PM</option>
        </select>
      </div>
    </div>
    
    <div class="slot-message" id="slot-message-1" style="margin-top: 10px; padding: 8px; border-radius: 6px; font-size: 14px; display: none;"></div>
  </div>

  <!-- Appointment 3 -->
  <div class="appointment-row" style="margin-bottom: 20px; padding: 15px; border: 2px solid #e5e7eb; border-radius: 12px; background: #f9fafb;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
      <h4 style="margin: 0; color: #1f2937;">Appointment 3</h4>
      <span class="slot-badge" id="slot-badge-2" style="padding: 4px 12px; background: #e5e7eb; color: #6b7280; border-radius: 20px; font-size: 13px; font-weight: 600;">Select date & time</span>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
      <div>
        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Date:</label>
        <input type="date" class="date-input" data-index="2" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;">
      </div>
      
      <div>
        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #374151;">Time:</label>
        <select class="time-select" data-index="2" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;">
          <option value="">Select Time</option>
          <option value="10:00">10:00 AM</option>
          <option value="11:00">11:00 AM</option>
          <option value="13:30">1:30 PM</option>
          <option value="14:30">2:30 PM</option>
          <option value="15:30">3:30 PM</option>
          <option value="16:30">4:30 PM</option>
        </select>
      </div>
    </div>
    
    <div class="slot-message" id="slot-message-2" style="margin-top: 10px; padding: 8px; border-radius: 6px; font-size: 14px; display: none;"></div>
  </div>

  <!-- Summary Display -->
  <div id="appointmentSummary" style="margin-top: 20px; padding: 15px; background: #f0f9ff; border-left: 4px solid #3b82f6; border-radius: 8px; display: none;">
    <h4 style="margin: 0 0 10px 0; color: #1e40af;">📋 Appointment Summary</h4>
    <div id="summaryContent"></div>
  </div>

  <!-- Hidden field to store JSON data -->
  <input type="hidden" id="appointment_dates_json" name="appointment_dates_json">

  <button type="button" class="prev-btn">Back</button>
  <button type="button" class="next-btn">Next</button>
</div>

        <!-- Step 4: Consent -->
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
