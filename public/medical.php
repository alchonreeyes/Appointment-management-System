<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
  // Not logged in → redirect back to login
  header("Location: login.php");
  exit;
}
// --- ADD THIS PHP BLOCK AFTER THE INCLUDES/SESSION START ---
include '../config/db.php'; // Ensure this is present in appointment.php too
$db = new Database();
$pdo = $db->getConnection();

$client_profile_data = [];
if (isset($_SESSION['user_id'])) {
    // 1. Fetch user data (Name, Phone from users table) and profile data (Age, Gender, Occupation, Suffix from clients table)
    $stmt = $pdo->prepare("
        SELECT u.full_name, u.phone_number, c.age, c.gender, c.occupation, c.suffix
        FROM users u
        JOIN clients c ON u.id = c.user_id
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $client_profile_data = $stmt->fetch(PDO::FETCH_ASSOC);
}
// -----------------------------------------------------------
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
             <input type="hidden" name="service_id" value="12">
        <div class="form-step active">
              <h2>Let's get you scheduled</h2>

      <p style="color:black;">To get started, simply select the type of appointment you need from our list of options</p>

      <div class="form-row name-row">
    <input type="text" placeholder="Enter Your Name..." name="full_name" required
           value="<?= htmlspecialchars($client_profile_data['full_name'] ?? '') ?>">
    
    <select name="suffix" id="suffix">
      <option value="">Suffix (Optional)</option>
      <option value="Jr" <?= ($client_profile_data['suffix'] ?? '') === 'Jr' ? 'selected' : '' ?>>Jr</option>
      <option value="Sr" <?= ($client_profile_data['suffix'] ?? '') === 'Sr' ? 'selected' : '' ?>>Sr</option>
      <option value="Other" id="suffix_other" <?= ($client_profile_data['suffix'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
    </select>
    <input type="text" name="suffix_other_input" style="display: none;" id="suffix_concern" placeholder="Enter your suffix..."> 
</div>

<div class="form-row three-cols">
    <select name="gender" required>
      <option value="">Select Gender...</option>
      <option value="Male" <?= ($client_profile_data['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
      <option value="Female" <?= ($client_profile_data['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
    </select>
    
    <input 
    type="number" 
    name="age" 
    placeholder="Enter your Age..." 
    required
    min="1" 
    max="120"
    value="<?= htmlspecialchars($client_profile_data['age'] ?? '') ?>"
>
<p id="ageWarning" style="color: red; display: none; font-size: 14px;">Please enter a valid age (18-120)</p>


<input 
    type="text" 
    name="contact_number" 
    placeholder="0912 345 678" 
    maxlength="11" 
    required
    value="<?= htmlspecialchars($client_profile_data['phone_number'] ?? '') ?>"
>
<p id="phoneWarning" style="color: red; display: none; font-size: 14px;">Please enter a valid phone number (0912 345 678)</p>
</div>

<div class="form-row single">
    <input type="text" name="occupation" placeholder="Enter your Occupation..." required
           value="<?= htmlspecialchars($client_profile_data['occupation'] ?? '') ?>">
</div>

            <button type="button" class="next-btn">Next</button>
        </div>
        
        <!-- Step 2: medical certificate purpose -->
<div class="form-step">
    <h3 style="color: black; margin-top: 0;">Medical Certificate Purpose</h3>
    
    <div class="radio-group-horizontal">
        <label><input type="radio" name="certificate_purpose" value="Work" required> For Work</label>
        <label><input type="radio" name="certificate_purpose" value="School"> For School</label>
        <label><input type="radio" name="certificate_purpose" value="Travel"> For Travel</label>
        <label><input type="radio" name="certificate_purpose" value="Other" id="cert-other"> Other</label>
    </div>

    <input type="text" name="certificate_other" placeholder="If other, please specify..." 
           style="display: none; margin-top: 10px;" id="certificate_other" class="compact-input">
    
    <div style="margin-top: 20px;">
        <button type="button" class="prev-btn">Back</button>
        <button type="button" class="next-btn">Next</button>
    </div>
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
  <p style="color: black;">Select an appointment date and time.</p>

  <div class="appointment-row" id="row-0">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
      <h4 style="margin: 0; color: #1f2937;">Appointment 1</h4>
      <span class="slot-badge" id="slot-badge-0">Select date & time</span>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
      <div>
        <label style="display: block; margin-bottom: 2px; font-weight: 600; color: #374151; font-size: 13px;">Date:</label>
        <input type="date" class="date-input" data-index="0">
      </div>
      
      <div>
        <label style="display: block; margin-bottom: 2px; font-weight: 600; color: #374151; font-size: 13px;">Time:</label>
        <select class="time-select" data-index="0">
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
    
    <div class="slot-message" id="slot-message-0" style="margin-top: 5px; padding: 5px; border-radius: 4px; font-size: 12px; display: none;"></div>
  </div>

  <div class="appointment-row" id="row-1" style="display: none;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
      <h4 style="margin: 0; color: #1f2937;">Appointment 2</h4>
      <div style="display: flex; align-items: center; gap: 10px;">
          <span class="slot-badge" id="slot-badge-1">Select date & time</span>
          <button type="button" class="remove-btn" onclick="hideRow(1)" style="background:none; color:red; border:none; padding:0; font-size:12px; cursor: pointer;">Remove ✕</button>
      </div>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
      <div>
        <label style="display: block; margin-bottom: 2px; font-weight: 600; color: #374151; font-size: 13px;">Date:</label>
        <input type="date" class="date-input" data-index="1">
      </div>
      
      <div>
        <label style="display: block; margin-bottom: 2px; font-weight: 600; color: #374151; font-size: 13px;">Time:</label>
        <select class="time-select" data-index="1">
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
    
    <div class="slot-message" id="slot-message-1" style="margin-top: 5px; padding: 5px; border-radius: 4px; font-size: 12px; display: none;"></div>
  </div>

  <div class="appointment-row" id="row-2" style="display: none;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
      <h4 style="margin: 0; color: #1f2937;">Appointment 3</h4>
      <div style="display: flex; align-items: center; gap: 10px;">
          <span class="slot-badge" id="slot-badge-2">Select date & time</span>
          <button type="button" class="remove-btn" onclick="hideRow(2)" style="background:none; color:red; border:none; padding:0; font-size:12px; cursor: pointer;">Remove ✕</button>
      </div>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
      <div>
        <label style="display: block; margin-bottom: 2px; font-weight: 600; color: #374151; font-size: 13px;">Date:</label>
        <input type="date" class="date-input" data-index="2">
      </div>
      
      <div>
        <label style="display: block; margin-bottom: 2px; font-weight: 600; color: #374151; font-size: 13px;">Time:</label>
        <select class="time-select" data-index="2">
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
    
    <div class="slot-message" id="slot-message-2" style="margin-top: 5px; padding: 5px; border-radius: 4px; font-size: 12px; display: none;"></div>
  </div>

  <div style="text-align: center; margin-bottom: 15px;">
      <button type="button" id="add-appt-btn" style="background: #f0f9ff; color: #004aad; border: 1px dashed #004aad; width: 100%; padding: 8px; font-size: 13px;">
          + Add Another Appointment
      </button>
  </div>
  <input type="hidden" id="appointment_dates_json" name="appointment_dates_json">
  <div style="margin-top: 20px; overflow: hidden;">
      <button type="button" class="prev-btn">Back</button>
      <button type="button" class="next-btn">Next</button>
  </div>
</div>
        <!-- Step 4: Consent -->
        <div class="form-step">
          <h2>Review Your Details</h2>
    <p style="color: black;">Please review your information below before confirming.</p>

    <div id="finalSummary" class="summary-box">
        </div>
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
