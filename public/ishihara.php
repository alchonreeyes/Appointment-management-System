
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
         <select name="suffix" id="suffix">
          <option value="">Suffix (Optional)</option>
          <option value="Jr">Jr</option>
          <option value="Sr">Sr</option>
          <option value="Other" id="suffix_other">Other</option>
        </select>
        <input type="text" style="display: none;" id="suffix_concern" placeholder="Enter your suffix...">
      </div>
  

        <div class="form-row three-cols">
          <select name="gender" required>
            <option value="">Select Gender...</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
          </select>

          <input type="number" name="age" placeholder="Enter your Age..." required>
        <p id="ageWarning" style="color: red; display: none; font-size: 14px;">Please enter a valid age (18-120)</p>

        <input type="text" name="contact_number" placeholder="0912 345 678" maxlength="13" required>
        <p id="phoneWarning" style="color: red; display: none; font-size: 14px;">Please enter a valid phone number (0912 345 678)</p>

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
    
        <script>
          const ageInput = document.querySelector('input[name="age"]');
          const phoneInput = document.querySelector('input[name="contact_number"]');
          const ageWarning = document.getElementById('ageWarning');
          const phoneWarning = document.getElementById('phoneWarning');

          ageInput.addEventListener('blur', function() {
            const age = parseInt(this.value);
            if (isNaN(age) || age < 18 || age > 120) {
              ageWarning.style.display = 'block';
              this.value = '';
            } else {
              ageWarning.style.display = 'none';
            }
          });

          phoneInput.addEventListener('input', function() {
            let value = this.value.replace(/\s/g, '');
            if (value.length > 11) {
              value = value.slice(0, 11);
            }
            if (value.length > 0) {
              value = value.replace(/(\d{4})(\d{3})(\d{4})/, '$1 $2 $3');
            }
            this.value = value;
          });

          phoneInput.addEventListener('blur', function() {
            const phone = this.value.replace(/\s/g, '');
            const phoneRegex = /^09\d{9}$/;
            if (!phoneRegex.test(phone)) {
              phoneWarning.style.display = 'block';
              this.value = '';
            } else {
              phoneWarning.style.display = 'none';
            }
          });
        </script>
</body>
</html>
