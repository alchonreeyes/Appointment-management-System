
<?php
session_start();

// 1. UNAHIN ANG MGA REQUIRES (Dapat nandito ang mga "tools" mo)
require_once '../config/db.php';
require_once '../config/encryption_util.php'; // <--- SIGURADUHIN NA TAMA ANG PATH NA ITO

// 2. CHECK SESSION
if (!isset($_SESSION['client_id'])) {
    header("Location: login.php");
    exit;
}

$db = new Database();
$pdo = $db->getConnection();

// 3. FETCH DATA
$client_profile_data = [];
if (isset($_SESSION['client_id'])) {
    $stmt = $pdo->prepare("
        SELECT u.full_name, u.phone_number, c.age, c.gender, c.occupation, c.suffix
        FROM users u
        JOIN clients c ON u.id = c.user_id
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['client_id']]);
    $encrypted_row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($encrypted_row) {
        // I-copy lahat ng data (kasama ang plain text fields gaya ng age)
        $client_profile_data = $encrypted_row;

        // Debug container
        $decrypt_debug = [
            'function_exists' => function_exists('decrypt_data'),
            'raw' => [
                'full_name' => $encrypted_row['full_name'] ?? null,
                'phone_number' => $encrypted_row['phone_number'] ?? null,
            ],
            'results' => [],
            'errors' => [],
        ];

        // I-decrypt ang mga kailangang i-decrypt with try/catch so we can log issues
        if (function_exists('decrypt_data')) {
            try {
                $client_profile_data['full_name'] = decrypt_data($encrypted_row['full_name']);
                $decrypt_debug['results']['full_name'] = $client_profile_data['full_name'];
            } catch (Throwable $e) {
                $decrypt_debug['errors']['full_name'] = $e->getMessage();
                // fallback to raw value to avoid breaking UI
                $client_profile_data['full_name'] = $encrypted_row['full_name'];
            }

            try {
                $client_profile_data['phone_number'] = decrypt_data($encrypted_row['phone_number']);
                $decrypt_debug['results']['phone_number'] = $client_profile_data['phone_number'];
            } catch (Throwable $e) {
                $decrypt_debug['errors']['phone_number'] = $e->getMessage();
                $client_profile_data['phone_number'] = $encrypted_row['phone_number'];
            }
            try {
                $client_profile_data['occupation'] = decrypt_data($encrypted_row['occupation']);
                $decrypt_debug['results']['occupation'] = $client_profile_data['occupation'];
            } catch (Throwable $e) {
                $decrypt_debug['errors']['occupation'] = $e->getMessage();
                $client_profile_data['occupation'] = $encrypted_row['occupation'];
            }
        } else {
            $decrypt_debug['errors']['decrypt_function'] = 'decrypt_data() not found';
        }

        // Send debug to browser console (safe JSON encode)
        echo '<script>console.log("decrypt debug: ", ' . json_encode($decrypt_debug, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) . ');</script>';

        // Also log to PHP error log in case browser console isn't available
        error_log('decrypt debug: ' . json_encode($decrypt_debug));
    }
}

// 4. FETCH PRODUCTS (Dito na ang ibang query mo...)
$productStmt = $pdo->prepare("SELECT product_id, product_name, brand, image_path, frame_type FROM products ORDER BY brand ASC, product_name ASC");
$productStmt->execute();
$available_products = $productStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ishihara Test Appointment</title>
  <link rel="stylesheet" href="../assets/appointment.css">
      <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
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
            
      <input type="hidden" name="service_id" value="13">
      <!-- Step 1: Patient Info -->
      <div class="form-step active">
        <h2>Let's get you scheduled</h2>
        <p style="color:black;">Fill in your details to proceed with the Ishihara color vision test.</p>

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

      <!-- Step 2: Ishihara Test Purpose -->
      <div class="form-step">
        <h3 style="color: #004aad;">Ishihara Test Purpose</h3>
        <p style="color: #666; margin-bottom: 10px;">Select test details.</p>

        <h5>Ishihara Test Type</h5>
        <div class="radio-group-horizontal">
            <label><input type="radio" name="ishihara_test_type" value="Basic Screening"> Basic Screening</label>
            <label><input type="radio" name="ishihara_test_type" value="Complete Assessment"> Complete Assessment</label>
            <label><input type="radio" name="ishihara_test_type" value="Follow-up"> Follow-up</label>
        </div>

        <input type="text" name="ishihara_reason" placeholder="Reason for taking the test (optional)" class="compact-input">

        <h5>Previous color vision issues?</h5>
        <div class="radio-group-horizontal">
             <label><input type="radio" name="previous_color_issues" value="Yes"> Yes</label>
             <label><input type="radio" name="previous_color_issues" value="No"> No</label>
             <label style="display: none;"><input type="radio" name="previous_color_issues" value="Unknown"  checked> Unknown</label>
        </div>

        <input type="text" name="ishihara_notes" placeholder="Additional notes (optional)" class="compact-input">

        <div style="margin-top: 15px;">
            <button type="button" class="prev-btn">Back</button>
            <button type="button" class="next-btn">Next</button>
        </div>
      </div>
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
             <input type="text" class="date-input" data-index="0" placeholder="Select date..." readonly>
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
          <input type="text" class="date-input" data-index="1" placeholder="Select date..." readonly>
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
          <input type="text" class="date-input" data-index="2" placeholder="Select date..." readonly>
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

<div style="margin-bottom: 12px;">
    <label style="display:flex; gap:10px; align-items:center; cursor: pointer;">
        <input type="checkbox" name="consent_info" value="1" required> 
        <span>I certify that the above information is correct.</span>
    </label>
</div>

<div style="margin-bottom: 12px;">
    <label style="display:flex; gap:10px; align-items:center; cursor: pointer;">
        <input type="checkbox" name="consent_reminders" value="1"> 
        <span>I consent to receive reminders <span class="legal-link" onclick="openLegalModal(event, 'modal-sms')">via SMS or email.</span></span>
    </label>
</div>

<div style="margin-bottom: 12px;">
    <label style="display:flex; gap:10px; align-items:center; cursor: pointer;">
        <input type="checkbox" name="consent_terms" value="1" required> 
        <span>I agree to <span class="legal-link" onclick="openLegalModal(event, 'modal-terms')">terms & privacy policy.</span></span>
    </label>
</div>
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
        <div id="modal-sms" class="legal-modal">
    <div class="legal-modal-content">
        <button type="button" class="close-legal" onclick="closeLegalModal('modal-sms')">&times;</button>
        <h3 style="margin-top:0; color:#004aad;">Communication Policy</h3>
        <p style="color:#444; line-height:1.6;">
            We respect your inbox. By consenting to this, you agree to receive:
        </p>
        <ul style="color:#444; line-height:1.6; padding-left:20px;">
            <li>Appointment confirmation details.</li>
            <li>Reminders 24 hours before your visit.</li>
            <li>Notifications when your glasses or results are ready.</li>
        </ul>
        <p style="color:#444; font-size:13px; margin-top:15px;">
            We will never send spam or sell your contact number to third parties.
        </p>
    </div>
</div>
<div id="modal-terms" class="legal-modal">
    <div class="legal-modal-content">
        <button type="button" class="close-legal" onclick="closeLegalModal('modal-terms')">&times;</button>
        <h3 style="margin-top:0; color:#004aad;">Ishihara Test Disclaimer</h3>
        <div style="max-height: 300px; overflow-y: auto; color:#444; line-height:1.6; text-align: left;">
            <p><strong>1. Screen Calibration Disclaimer</strong><br>
            Online color blindness screening may vary based on your device's screen brightness, blue light filter, or color settings.</p>
            
            <p><strong>2. Not a Final Diagnosis</strong><br>
            The results of any online interaction are preliminary. A final, official diagnosis of color vision deficiency must be conducted in-clinic using physical Ishihara plates.</p>
            
            <p><strong>3. Clinic Visit</strong><br>
            By booking this service, you agree to undergo a controlled lighting examination at Eye Master Optical for accurate results.</p>
        </div>
    </div>
</div>
<script>
    function openLegalModal(event, modalId) {
        // THIS LINE STOPS THE CHECKBOX FROM CHECKING WHEN CLICKING THE LINK
        event.preventDefault(); 
        document.getElementById(modalId).style.display = 'flex';
    }

    function closeLegalModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Close modal if clicking outside the white box
    window.onclick = function(event) {
        if (event.target.classList.contains('legal-modal')) {
            event.target.style.display = "none";
        }
    }
</script>
</body>
</html>
