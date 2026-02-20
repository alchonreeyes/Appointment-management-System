<?php
// =======================================================
// UPDATED: Admin Profile with Fixed Duplicate Logic
// =======================================================
session_start();
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../../config/encryption_util.php';

// =======================================================
// 1. SECURITY CHECK
// =======================================================
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? null;

if (!$user_id || $user_role !== 'admin') {
    if (isset($_POST['action'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    } else {
        header('Location: ../../public/login.php');
    }
    exit;
}

// =======================================================
// 2. SERVER-SIDE ACTION HANDLING
// =======================================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    if ($action === 'updateProfile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validation
        if (!$name || !$email) {
            echo json_encode(['success' => false, 'message' => 'Please fill in Name and Email fields.']);
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
            exit;
        }
        if (!empty($password) && strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters long.']);
            exit;
        }

        try {
            // ======================================================
            // FIXED: DUPLICATE CHECK (PHP LOOP METHOD)
            // ======================================================
            // We fetch ALL admin emails (except current user) and check decryption in PHP
            $checkStmt = $conn->prepare("SELECT id, email FROM admin WHERE id != ?");
            $checkStmt->bind_param("i", $user_id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            while ($row = $checkResult->fetch_assoc()) {
                // Decrypt database email and compare with input
                if (decrypt_data($row['email']) === $email) {
                    echo json_encode(['success' => false, 'message' => 'This email is already in use by another admin.']);
                    exit;
                }
            }

            // Proceed to Update
            $encryptedName = encrypt_data($name);
            $encryptedEmail = encrypt_data($email);

            // Handle password update
            if (!empty($password)) {
                // Hash password (Standard Security)
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE admin SET name=?, email=?, password=? WHERE id=?");
                $stmt->bind_param("sssi", $encryptedName, $encryptedEmail, $hashedPassword, $user_id);
            } else {
                // Update info only
                $stmt = $conn->prepare("UPDATE admin SET name=?, email=? WHERE id=?");
                $stmt->bind_param("ssi", $encryptedName, $encryptedEmail, $user_id);
            }
            
            if ($stmt->execute()) {
                // Update session name immediately so the header updates too
                $_SESSION['full_name'] = $name; 
                echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'No changes made or database error.']);
            }

        } catch (Exception $e) {
            error_log("UpdateProfile error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Server error occurred.']);
        }
        exit;
    }

    if ($action === 'logout') {
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
        exit;
    }
}

// =======================================================
// 3. FETCH USER DATA & DECRYPT
// =======================================================
$user = null;
try {
    $stmt = $conn->prepare("SELECT id, name, email, role FROM admin WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        session_destroy();
        header('Location: ../../public/login.php');
        exit;
    }

    // Decrypt fields for display
    $user['name'] = decrypt_data($user['name']);
    $user['email'] = decrypt_data($user['email']);

} catch (Exception $e) {
    error_log("Admin Profile fetch error: " . $e->getMessage());
    die("Error loading profile data.");
}

// Calculate initials
$nameToUse = $user['name'] ?? 'Admin';
$nameParts = explode(' ', trim($nameToUse));
if (count($nameParts) > 1) {
    $initials = strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1));
} else {
    $initials = strtoupper(substr($nameToUse, 0, 1));
    if (strlen($nameToUse) > 1) { 
        $initials .= strtoupper(substr($nameToUse, 1, 1)); 
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Profile - Eye Master Clinic</title>
<style>
/* Core Styles */
* { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { background:#f8f9fa; color:#223; }

/* Sidebar & Header */
.vertical-bar { position:fixed; left:0; top:0; width:55px; height:100vh; background:linear-gradient(180deg,#991010 0%,#6b1010 100%); z-index:1000; }
header { display:flex; align-items:center; background:#fff; padding:12px 20px 12px 75px; box-shadow:0 2px 4px rgba(0,0,0,0.05); position:relative; z-index:100; }
.logo-section { display:flex; align-items:center; gap:10px; margin-right:auto; }
.logo-section img { height:32px; border-radius:4px; object-fit:cover; }
nav { display:flex; gap:8px; align-items:center; }
nav a { text-decoration:none; padding:8px 12px; color:#5a6c7d; border-radius:6px; font-weight:600; font-size:14px; }
nav a.active { background:#dc3545; color:#fff; }

/* Main Container */
.container { padding:30px 20px 40px 75px; max-width:1000px; margin:0 auto; }
.profile-card { background:#fff; border:1px solid #e6e9ee; border-radius:16px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.05); animation: slideUp 0.4s ease; }

@keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

/* Profile Header */
.profile-header { background:linear-gradient(135deg, #991010 0%, #6b1010 100%); padding:35px 40px; display:flex; align-items:center; gap:25px; }
.profile-avatar { width:100px; height:100px; border-radius:50%; background:rgba(255,255,255,0.2); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:36px; border:4px solid rgba(255,255,255,0.3); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
.profile-info { flex:1; }
.profile-name { font-size:28px; font-weight:800; color:#fff; margin-bottom:8px; text-shadow: 0 2px 4px rgba(0,0,0,0.2); }
.badge { display:inline-block; padding:6px 14px; border-radius:20px; font-weight:700; font-size:12px; text-transform:uppercase; }
.badge.admin-role { background:rgba(255,255,255,0.9); color:#991010; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
.badge.admin-id { background:rgba(255,255,255,0.2); color:#fff; border:1px solid rgba(255,255,255,0.4); margin-left: 8px; }

/* Profile Body */
.profile-body { padding:40px; }
.section-title { font-size:20px; font-weight:700; color:#2c3e50; margin-bottom:24px; display:flex; align-items:center; gap:10px; }
.section-title:before { content:'🔒'; font-size:20px; }

.security-notice { background:#fff8e1; border-left:4px solid #ffc107; padding:15px; border-radius:4px; margin-bottom:30px; font-size:14px; color:#5d4037; display:flex; gap:10px; align-items:start; }
.security-notice strong { color:#e65100; }

.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:25px; margin-bottom:24px; }
.form-group { display:flex; flex-direction:column; }
.form-group label { font-weight:700; color:#4a5568; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px; }
.form-group input { padding:12px 14px; border:1px solid #dde3ea; border-radius:8px; font-size:15px; background:#fff; transition:all .2s; }
.form-group input:focus { outline:none; border-color:#991010; box-shadow:0 0 0 3px rgba(153,16,16,0.1); }
.form-group input:disabled { background:#f9fafb; color:#6b7f86; cursor:not-allowed; border-color: #e2e8f0; }

.password-wrapper { position:relative; }
.password-wrapper input { padding-right:45px; width: 100%; }
.password-wrapper button { position:absolute; right:0; top:0; bottom:0; width:40px; background:transparent; border:none; cursor:pointer; font-size:18px; color:#555; display: flex; align-items: center; justify-content: center; z-index: 10; opacity: 0.6; transition: opacity 0.2s; }
.password-wrapper button:hover { opacity: 1; }

.form-actions { display:flex; gap:12px; justify-content:flex-end; padding-top:25px; border-top:1px solid #f1f5f9; }
.btn { padding:12px 24px; border-radius:8px; border:none; cursor:pointer; font-weight:700; font-size:14px; transition:all .2s; display:flex; align-items:center; gap:8px; }
.btn:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
.btn-edit { background:#fff; color:#223; border: 1px solid #dde3ea; }
.btn-edit:hover { background: #f8f9fa; border-color: #cacedb; }
.btn-save { background:#16a34a; color:#fff; }
.btn-save:hover { background:#15803d; }
.btn-cancel { background:#f1f5f9; color:#475569; }
.btn-cancel:hover { background:#e2e8f0; }
.btn-logout { background:#fee2e2; color:#dc2626; margin-left: auto; }
.btn-logout:hover { background:#fecaca; }

/* Success Modal */
.success-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.4); z-index: 4000; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
.success-modal-overlay.show { display: flex; animation: fadeIn 0.3s ease; }
.success-modal-card { background: #fff; padding: 25px 35px; border-radius: 12px; box-shadow: 0 15px 40px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 20px; max-width: 90%; animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
@keyframes popIn { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
.success-icon-circle { width: 50px; height: 50px; background-color: #28a745; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.success-icon-circle svg { width: 28px; height: 28px; fill: none; stroke: #fff; stroke-width: 3.5; stroke-linecap: round; stroke-linejoin: round; stroke-dasharray: 50; stroke-dashoffset: 50; animation: checkDraw 0.6s ease forwards; }
@keyframes checkDraw { to { stroke-dashoffset: 0; } }
.success-text { font-size: 16px; font-weight: 600; color: #333; }

/* Toast */
.toast-overlay { position: fixed; inset: 0; pointer-events: none; z-index: 9998; display: flex; align-items: flex-end; justify-content: center; padding-bottom: 30px; }
.toast { pointer-events: auto; background: #fff; color: #1a202c; padding: 16px 20px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 14px; font-weight: 600; animation: slideUp .3s ease; border-left: 5px solid #dc2626; }
.toast-icon { font-size: 18px; color: #dc2626; }

/* Mobile */
#menu-toggle { display: none; background: #f1f5f9; border: 2px solid #e2e8f0; font-size: 24px; padding: 5px 12px; border-radius: 8px; cursor: pointer; }
@media (max-width: 1000px) {
    .vertical-bar { display: none; }
    header { padding: 12px 20px; }
    .container { padding: 20px; }
    #menu-toggle { display: block; }
    nav#main-nav { display: none; flex-direction: column; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(20, 0, 0, 0.95); z-index: 2000; padding-top: 80px; backdrop-filter: blur(5px); }
    nav#main-nav.show { display: flex; }
    nav#main-nav a { color: #fff; font-size: 20px; padding: 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
    .form-grid { grid-template-columns: 1fr; }
    .profile-header { flex-direction: column; text-align: center; padding: 30px 20px; }
    .form-actions { flex-wrap: wrap; }
    .btn { flex: 1; justify-content: center; }
}
</style>
</head>
<body>

<div id="successModal" class="success-modal-overlay">
    <div class="success-modal-card">
        <div class="success-icon-circle">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
        </div>
        <div class="success-text" id="successMessageText">Profile updated!</div>
    </div>
</div>

<header>
  <div class="logo-section">
    <img src="../photo/LOGO.jpg" alt="Logo"> <strong>EYE MASTER CLINIC</strong>
  </div>
  <button id="menu-toggle">☰</button>
  <nav id="main-nav">
    <a href="admin_dashboard.php">🏠 Dashboard</a>
    <a href="appointment.php">📅 Appointments</a>
    <a href="patient_record.php">📘 Patient Record</a>
    <a href="product.php">💊 Product & Services</a>
    <a href="account.php">👤 Account</a>
    <a href="profile.php" class="active">🔍 Profile</a>
  </nav>
</header>

<div class="container">
  <div class="profile-card">
    <div class="profile-header">
      <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
      <div class="profile-info">
        <div class="profile-name"><?= htmlspecialchars($user['name']) ?></div>
        <div class="profile-meta">
          <span class="badge admin-role"><?= htmlspecialchars(ucfirst($user['role'] ?? 'Admin')) ?></span>
          <span class="badge admin-id">ID: <?= htmlspecialchars($user['id']) ?></span>
        </div>
      </div>
    </div>

    <div class="profile-body">
      <div class="section-title">Account Information</div>

      <div class="security-notice">
        <span>🔒</span>
        <div>
            <strong>Security Notice:</strong><br>
            Your email is encrypted. Passwords are hashed and never visible. To keep your current password, leave the field blank.
        </div>
      </div>

      <form id="profileForm" onsubmit="return false;">
        <div class="form-grid">
          <div class="form-group">
            <label for="profileName">Full Name *</label>
            <input type="text" id="profileName" value="<?= htmlspecialchars($user['name']) ?>" disabled required>
          </div>
          <div class="form-group">
            <label for="profileEmail">Email Address *</label>
            <input type="email" id="profileEmail" value="<?= htmlspecialchars($user['email']) ?>" disabled required>
          </div>
          <div class="form-group" style="grid-column: 1 / -1;">
            <label for="profilePassword">New Password (optional)</label>
            <div class="password-wrapper">
              <input type="password" id="profilePassword" placeholder="Leave empty to keep current password" disabled>
              <button type="button" onclick="togglePasswordVisibility()">👁️</button>
            </div>
          </div>
        </div>

        <div class="form-actions" id="viewActions">
          <button type="button" class="btn btn-logout" onclick="confirmLogout()">🚪 Logout</button>
          <button type="button" class="btn btn-edit" onclick="enableEdit()">✏️ Edit Details</button>
        </div>

        <div class="form-actions" id="editActions" style="display:none;">
          <button type="button" class="btn btn-cancel" onclick="cancelEdit()">Cancel</button>
          <button type="button" class="btn btn-save" onclick="saveProfile()">💾 Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Store original decrypted data for cancel action
let originalData = {
  name: <?= json_encode($user['name']) ?>,
  email: <?= json_encode($user['email']) ?>
};

function showSuccessModal(msg) {
    const modal = document.getElementById('successModal');
    const text = document.getElementById('successMessageText');
    if(modal && text) {
        text.textContent = msg;
        modal.classList.add('show');
        setTimeout(() => { modal.classList.remove('show'); }, 2000);
    }
}

function showToast(msg) {
    const overlay = document.createElement('div');
    overlay.className = 'toast-overlay';
    overlay.innerHTML = `<div class="toast"><span class="toast-icon">⚠️</span>${msg}</div>`;
    document.body.appendChild(overlay);
    setTimeout(() => {
        overlay.style.opacity = '0';
        setTimeout(() => overlay.remove(), 300);
    }, 3000);
}

function togglePasswordVisibility() {
    const input = document.getElementById('profilePassword');
    input.type = (input.type === "password") ? "text" : "password";
}

function enableEdit() {
  document.getElementById('profileName').disabled = false;
  document.getElementById('profileEmail').disabled = false;
  document.getElementById('profilePassword').disabled = false;
  document.getElementById('profilePassword').placeholder = 'Enter new password (min. 6 characters)';
  document.getElementById('viewActions').style.display = 'none';
  document.getElementById('editActions').style.display = 'flex';
  document.getElementById('profileName').focus();
}

function cancelEdit() {
  document.getElementById('profileName').value = originalData.name;
  document.getElementById('profileEmail').value = originalData.email;
  document.getElementById('profilePassword').value = '';
  document.getElementById('profileName').disabled = true;
  document.getElementById('profileEmail').disabled = true;
  document.getElementById('profilePassword').disabled = true;
  document.getElementById('profilePassword').placeholder = 'Leave empty to keep current password';
  document.getElementById('viewActions').style.display = 'flex';
  document.getElementById('editActions').style.display = 'none';
}

function saveProfile() {
  const name = document.getElementById('profileName').value.trim();
  const email = document.getElementById('profileEmail').value.trim();
  const password = document.getElementById('profilePassword').value;

  if (!name || !email) {
    showToast('Name and Email are required.');
    return;
  }

  const formData = new URLSearchParams({
    action: 'updateProfile',
    name: name,
    email: email,
    password: password
  });

  fetch('profile.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: formData
  })
  .then(res => res.json())
  .then(payload => {
    if (payload.success) {
      showSuccessModal(payload.message);
      setTimeout(() => location.reload(), 1500);
    } else {
      showToast(payload.message);
    }
  })
  .catch(err => {
    console.error(err);
    showToast('Network error. Please try again.');
  });
}

function confirmLogout() {
  if (confirm('Are you sure you want to logout?')) {
    fetch('profile.php', { 
      method: 'POST', 
      headers: {'Content-Type':'application/x-www-form-urlencoded'}, 
      body: new URLSearchParams({action: 'logout'}) 
    }).then(() => location.href = '../../public/login.php');
  }
}

document.getElementById('menu-toggle').onclick = () => document.getElementById('main-nav').classList.toggle('show');
</script>
</body>
</html>