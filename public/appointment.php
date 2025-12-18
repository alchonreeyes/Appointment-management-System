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
             <input type="hidden" name="service_id" value="11">
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
        <!-- Step 3: Symptoms -->
       <div class="form-step">
    <h2 style="color: #004aad; margin-top: 0; font-size: 1.4rem;">Eye Health Information</h2>
    <p style="color: #666; margin-bottom: 10px; font-size: 0.9rem;">Please provide your current eye history.</p>

    <h5 style="margin: 5px 0; font-size: 13px;">Do you currently wear Eye Glasses?</h5>
    <div class="radio-group-horizontal">
        <label><input type="radio" name="wear_glasses" value="Yes" required> Yes</label>
        <label><input type="radio" name="wear_glasses" value="No" required> No</label>
    </div>

    <h5 style="margin: 10px 0 5px 0; font-size: 13px;">Do you currently wear Contact Lenses?</h5>
    <div class="radio-group-horizontal">
        <label><input type="radio" name="wear_contact_lenses" value="Yes" required> Yes</label>
        <label><input type="radio" name="wear_contact_lenses" value="No" required> No</label>
    </div>
    
    <h5 style="margin: 10px 0 5px 0; font-size: 13px;">Are you experiencing any eye discomfort?</h5>
    <div class="radio-group-horizontal">
        <label><input type="checkbox" name="symptoms[]" value="Blurred Vision"> Blurred Vision</label>
        <label><input type="checkbox" name="symptoms[]" value="Headache"> Headache</label>
        <label><input type="checkbox" name="symptoms[]" value="Redness"> Redness</label>
        <label><input type="checkbox" name="symptoms[]" value="Itchiness"> Itchiness</label>
        <label><input type="checkbox" name="symptoms[]" value="Other" id="otherSymptom"> Other</label>
    </div>

    <input type="text" name="concern" id="concernInput" placeholder="Please describe your concern..." 
           style="display: none; margin-top: 5px;" class="compact-input">

    <div style="margin-top: 15px;">
        <button type="button" class="prev-btn">Back</button>
        <button type="button" class="next-btn">Next</button>
    </div>
</div>

<div class="form-step">
    <h2>Select Frames to Try On</h2>
    <p style="color: #666;">Select from our top picks below, or click "See More" to view the full collection.</p>

    <?php 
        // 1. Separate products into "Top 3" and "The Rest"
        $top_picks = array_slice($available_products, 0, 3);
        $more_products = array_slice($available_products, 3);
    ?>

    <div class="preference-grid">
        <?php foreach ($top_picks as $product): ?>
            <?php 
                $imgSrc = $product['image_path'];
                if (strpos($imgSrc, '../photo/') !== false) {
                    $imgSrc = str_replace('../photo/', '../mod/photo/', $imgSrc);
                }
                if (empty($imgSrc)) $imgSrc = 'https://placehold.co/150x80?text=No+Image';
            ?>
            <label class="preference-option-image product-card-select">
                <input type="checkbox" name="selected_products[]" value="<?= htmlspecialchars($product['product_name'] . ' (' . $product['brand'] . ')') ?>">
                <div class="shape-image-container" style="height: 100px;">
                    <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($product['product_name']) ?>" style="max-height: 100%; max-width: 100%; object-fit: contain;">
                </div>
                <div style="text-align: center; margin-top: 5px;">
                    <span class="shape-label-text" style="display:block; font-weight:bold; font-size: 14px;">
                        <?= htmlspecialchars($product['product_name']) ?>
                    </span>
                    <small style="color: #666; font-size: 12px;">
                        <?= htmlspecialchars($product['brand']) ?>
                    </small>
                </div>
            </label>
        <?php endforeach; ?>
    </div>

    <?php if (count($more_products) > 0): ?>
        <div class="see-more-btn-container">
            <button type="button" class="btn-see-more" onclick="openProductModal()">+ See More Frames</button>
            <p style="font-size: 12px; color: #888; margin-top: 5px;">
                (<?= count($more_products) ?> more styles available)
            </p>
        </div>
    <?php endif; ?>

    <div id="productModal" class="product-modal-overlay">
        <div class="product-modal-content">
            
            <div class="product-modal-header">
                <h3 style="margin:0; color:#004aad;">Full Frame Collection</h3>
                <button type="button" class="product-modal-close" onclick="closeProductModal()">&times;</button>
            </div>

            <div class="product-modal-body">
                <div class="preference-grid">
                    <?php foreach ($more_products as $product): ?>
                        <?php 
                            $imgSrc = $product['image_path'];
                            if (strpos($imgSrc, '../photo/') !== false) {
                                $imgSrc = str_replace('../photo/', '../mod/photo/', $imgSrc);
                            }
                            if (empty($imgSrc)) $imgSrc = 'https://placehold.co/150x80?text=No+Image';
                        ?>
                        <label class="preference-option-image product-card-select">
                            <input type="checkbox" name="selected_products[]" value="<?= htmlspecialchars($product['product_name'] . ' (' . $product['brand'] . ')') ?>">
                            <div class="shape-image-container" style="height: 100px;">
                                <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($product['product_name']) ?>" style="max-height: 100%; max-width: 100%; object-fit: contain;">
                            </div>
                            <div style="text-align: center; margin-top: 5px;">
                                <span class="shape-label-text" style="display:block; font-weight:bold; font-size: 14px;">
                                    <?= htmlspecialchars($product['product_name']) ?>
                                </span>
                                <small style="color: #666; font-size: 12px;">
                                    <?= htmlspecialchars($product['brand']) ?>
                                </small>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="text-align: right; padding-top: 15px; border-top: 1px solid #eee;">
                <button type="button" class="btn-done" onclick="closeProductModal()">Done Selecting</button>
            </div>

        </div>
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
        <script>
    // Function to Open Modal
    function openProductModal() {
        document.getElementById('productModal').style.display = 'flex';
    }

    // Function to Close Modal
    function closeProductModal() {
        document.getElementById('productModal').style.display = 'none';
        
        // Optional: Update summary count on the main page if you want
        // But for now, keeping it simple is best.
    }

    // Close modal if user clicks outside the white box
    window.onclick = function(event) {
        const modal = document.getElementById('productModal');
        if (event.target === modal) {
            modal.style.display = "none";
        }
    }
</script>
<div id="modal-certify" class="legal-modal-overlay">
    <div class="legal-modal-content">
        <div class="legal-modal-header">
            <h3>Information Certification</h3>
            <button type="button" class="legal-close" onclick="closeLegalModal('modal-certify')">&times;</button>
        </div>
        <div class="legal-modal-body">
            <p><strong>Accuracy of Information:</strong></p>
            <p>By checking this box, you declare that all personal details, medical history, and contact information provided in this form are accurate and up-to-date to the best of your knowledge.</p>
            <p>Providing false information may affect the quality of the eye examination and medical advice given by our specialists.</p>
        </div>
    </div>
</div>

<div id="modal-comm" class="legal-modal-overlay">
    <div class="legal-modal-content">
        <div class="legal-modal-header">
            <h3>Communication Consent</h3>
            <button type="button" class="legal-close" onclick="closeLegalModal('modal-comm')">&times;</button>
        </div>
        <div class="legal-modal-body">
            <p><strong>How we contact you:</strong></p>
            <p>We value your privacy. Your phone number and email will strictly be used for:</p>
            <ul>
                <li>Appointment Confirmations & Reminders</li>
                <li>Notification when your glasses/results are ready</li>
                <li>Emergency schedule changes</li>
            </ul>
            <p>We will <strong>never</strong> sell your contact info to third-party advertisers.</p>
        </div>
    </div>
</div>

<div id="modal-terms" class="legal-modal-overlay">
    <div class="legal-modal-content">
        <div class="legal-modal-header">
            <h3>Terms & Privacy Policy</h3>
            <button type="button" class="legal-close" onclick="closeLegalModal('modal-terms')">&times;</button>
        </div>
        <div class="legal-modal-body">
            <p><strong>1. Data Privacy Act</strong></p>
            <p>Eye Master Clinic complies with the Data Privacy Act of 2012. Your medical records are stored securely and encrypted.</p>
            
            <p><strong>2. Appointment Cancellation</strong></p>
            <p>Please notify us at least 24 hours in advance if you need to cancel or reschedule.</p>
            
            <p><strong>3. Clinic Rights</strong></p>
            <p>The clinic reserves the right to refuse service to patients who provide fraudulent information or display abusive behavior towards staff.</p>
        </div>
    </div>
    <script>
    // Open specific modal
    function openLegalModal(modalId) {
        // Prevent the checkbox from toggling when clicking the text link
        event.preventDefault(); 
        document.getElementById(modalId).style.display = 'flex';
    }

    // Close specific modal
    function closeLegalModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('legal-modal-overlay')) {
            event.target.style.display = 'none';
        }
    });
</script>
</div> 
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
        <h3 style="margin-top:0; color:#004aad;">Terms & Privacy Policy</h3>
        <div style="max-height: 300px; overflow-y: auto; color:#444; line-height:1.6;">
            <p><strong>1. Data Privacy</strong><br>
            Your records are kept strictly confidential in compliance with the Data Privacy Act of 2012.</p>
            
            <p><strong>2. Cancellations</strong><br>
            Please notify us at least 24 hours in advance for cancellations.</p>
            
            <p><strong>3. Accuracy</strong><br>
            You certify that the information provided is accurate to ensure proper medical assessment.</p>
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