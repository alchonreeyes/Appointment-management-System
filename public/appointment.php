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
    <div class="progress-line"></div>
  <div class="progress-step">5</div>
</div>

        <form action="../actions/appointment-action.php" method="POST" id="appointmentForm">       
            <!-- Step 1: Patient Info -->
             <input type="hidden" name="service_id" value="6">

        <div class="form-step active">
              <h2>Let's get you scheduled</h2>

             <p style="color:black;">To get started, simply select the type of appointment you need from our list of options</p>
            
            <div class="form-row name-row">
        <input type="text" placeholder="Enter Your Name..." name="full_name" required>
        
         <select name="suffix" id="suffix">
          <option value="">Suffix (Optional)</option>
          <option value="Jr">Jr</option>
          <option value="Sr">Sr</option>
          <option value="Other" id="suffix_other">Other</option>
        </select>
        <input type="text" name="suffix"  style="display: none;" id="suffix_concern" placeholder="Enter your suffix...">
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
        
<!-- STEP 2: Choose Provider & Time -->
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

        
        <!-- Step 3: Symptoms -->
        <div class="form-step">
            <h2 style="color: blue; font-size:30px;">Choose your provider & time</h2>
            <p style="color: black;">Browse through the list of providers and check their upcoming appointment availability with just a glance. If you need more details, like specific time slots, just click on their name in the table.</p>

            <h3>Eye Health Information</h3>
            <p>Do you currently wear Eye Glasses?</p>
            <label><input type="radio" name="wear_glasses" value="Yes" required> Yes</label>
            <label><input type="radio" name="wear_glasses" value="No" required> No</label>

            <p>Do you currently wear Contact Lenses?</p>
            <label><input type="radio" name="wear_contact_lenses" value="Yes" required> Yes</label>
            <label><input type="radio" name="wear_contact_lenses" value="No" required> No</label>
            
            <p>Are you experiencing any eye discomfort?</p>
            <label><input type="checkbox" name="symptoms[]" value="Blurred Vision"> Blurred Vision</label>
            <label><input type="checkbox" name="symptoms[]" value="Headache"> Headache</label>
            <label><input type="checkbox" name="symptoms[]" value="Redness"> Eye Redness</label>
            <label><input type="checkbox" name="symptoms[]" value="Itchiness"> Itchiness</label>
            <label><input type="checkbox" name="symptoms[]" value="Other" id="otherSymptom"> Other</label>
            <input type="text" name="concern" id="concernInput" placeholder="Write a concern..." style="display: none;">
            <button type="button" class="prev-btn">Back</button>
            <button type="button" class="next-btn">Next</button>
        
        </div>

        <div class="form-step">
    <h2>Frame & Style Preferences</h2>
    <p style="color: black;">Help us personalize your visit. Select the brands or styles you are interested in trying on (Optional).</p>

    <h4 style="color: #004aad; margin-bottom: 15px;">Preferred Brands</h4>
    <div class="preference-grid">
        <label class="preference-option">
            <input type="checkbox" name="brands[]" value="Ray-Ban">
            <span class="preference-label">Ray-Ban</span>
        </label>
        <label class="preference-option">
            <input type="checkbox" name="brands[]" value="Oakley">
            <span class="preference-label">Oakley</span>
        </label>
        <label class="preference-option">
            <input type="checkbox" name="brands[]" value="Gucci">
            <span class="preference-label">Gucci</span>
        </label>
        <label class="preference-option">
            <input type="checkbox" name="brands[]" value="Prada">
            <span class="preference-label">Prada</span>
        </label>
        <label class="preference-option">
            <input type="checkbox" name="brands[]" value="Coach">
            <span class="preference-label">Coach</span>
        </label>
        <label class="preference-option">
            <input type="checkbox" name="brands[]" value="Others">
            <span class="preference-label">Others</span>
        </label>
    </div>

    <h4 style="color: #004aad; margin-bottom: 15px; margin-top: 20px;">Frame Shape</h4>
    <div class="preference-grid">
        <label class="preference-option">
            <input type="checkbox" name="frame_shape[]" value="Round">
            <span class="preference-label">Round</span>
        </label>
        <label class="preference-option">
            <input type="checkbox" name="frame_shape[]" value="Square">
            <span class="preference-label">Square</span>
        </label>
        <label class="preference-option">
            <input type="checkbox" name="frame_shape[]" value="Aviator">
            <span class="preference-label">Aviator</span>
        </label>
        <label class="preference-option">
            <input type="checkbox" name="frame_shape[]" value="Cat Eye">
            <span class="preference-label">Cat Eye</span>
        </label>
    </div>

    <div style="margin-top: 30px;">
        <button type="button" class="prev-btn">Back</button>
        <button type="button" class="next-btn">Next</button>
    </div>
</div>

        <!-- Step 5: Consent -->
      <div class="form-step">
    <h2>Review Your Details</h2>
    <p style="color: black;">Please review your information below before confirming.</p>

    <div id="finalSummary" class="summary-box">
        </div>

    <h3>Consent & Confirmation</h3>
    <label><input type="checkbox" name="consent_info" value="1" required> I certify that the above information is correct.</label>
    <label><input type="checkbox" name="consent_reminders" value="1"> I consent to receive reminders via SMS or email.</label>
    <label><input type="checkbox" name="consent_terms" value="1" required> I agree to terms & privacy policy.</label>

    <div style="margin-top: 20px;">
        <button type="button" class="prev-btn">Back</button>
        <button type="submit" name="submit">Make an Appointment</button>
    </div>
</div>

    </form>
</div>
</div>
<!-- Booking Popup Message -->


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
        
        <script>
            document.getElementById('otherSymptom').addEventListener('change', function() {
          const concernInput = document.getElementById('concernInput');
          if (this.checked) {
              concernInput.style.display = 'block';
              concernInput.setAttribute('required', 'required');
          } else {
              concernInput.style.display = 'none';
              concernInput.removeAttribute('required');
          }
            });
        </script>
        
</body>
</html>