<?php
// admin/profile.php - SECURE VERSION with Encryption + Password Hashing
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
// 2. SERVER-SIDE ACTION HANDLING (SECURE VERSION)
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
            // ✅ Encrypt email for duplicate check
            $encryptedEmailCheck = encrypt_data($email);
            
            // Check for duplicate email in admin table (excluding current user)
            $stmt_email = $conn->prepare("SELECT id FROM admin WHERE email = ? AND id != ?");
            $stmt_email->bind_param("si", $encryptedEmailCheck, $user_id);
            $stmt_email->execute();
            
            if ($stmt_email->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'This email is already in use by another admin.']);
                exit;
            }

            // ✅ Encrypt name and email
            $encryptedName = encrypt_data($name);
            $encryptedEmail = encrypt_data($email);

            // Handle password update
            if (!empty($password)) {
                // ✅ Hash password with bcrypt (SECURE)
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE admin SET name=?, email=?, password=? WHERE id=?");
                $stmt->bind_param("sssi", $encryptedName, $encryptedEmail, $hashedPassword, $user_id);
            } else {
                // No password update
                $stmt = $conn->prepare("UPDATE admin SET name=?, email=? WHERE id=?");
                $stmt->bind_param("ssi", $encryptedName, $encryptedEmail, $user_id);
            }
            
            $stmt->execute();

            // ✅ Update session with decrypted name
            $_SESSION['full_name'] = $name;

            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } catch (Exception $e) {
            error_log("UpdateProfile error (Admin): " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error during profile update.']);
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
    $stmt = $conn->prepare("SELECT id, name, email, role, password FROM admin WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        session_destroy();
        header('Location: ../../public/login.php');
        exit;
    }

    // ✅ Decrypt name and email for display
    $user['name'] = decrypt_data($user['name']);
    $user['email'] = decrypt_data($user['email']);
    // ⚠️ Password is HASHED - cannot be decrypted (and shouldn't be shown!)

} catch (Exception $e) {
    error_log("Admin Profile fetch error: " . $e->getMessage());
    die("Error loading profile data: " . htmlspecialchars($e->getMessage()));
}

// Calculate initials
$nameToUse = $user['name'] ?? 'Admin';
$nameParts = explode(' ', trim($nameToUse));
if (count($nameParts) > 1) {
    $initials = strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1));
} else if (!empty($nameToUse)) {
    $initials = strtoupper(substr($nameToUse, 0, 1));
    if (strlen($nameToUse) > 1) { 
        $initials .= strtoupper(substr($nameToUse, 1, 1)); 
    }
} else {
    $initials = 'AD';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Profile - Eye Master Clinic</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { background:#f8f9fa; color:#223; }
.vertical-bar { position:fixed; left:0; top:0; width:55px; height:100vh; background:linear-gradient(180deg,#991010 0%,#6b1010 100%); z-index:1000; }
.vertical-bar .circle { width:70px; height:70px; background:#b91313; border-radius:50%; position:absolute; left:-8px; top:45%; transform:translateY(-50%); border:4px solid #5a0a0a; }
header { display:flex; align-items:center; background:#fff; padding:12px 20px 12px 75px; box-shadow:0 2px 4px rgba(0,0,0,0.05); position:relative; z-index:100; }
.logo-section { display:flex; align-items:center; gap:10px; margin-right:auto; }
.logo-section img { height:32px; border-radius:4px; object-fit:cover; }
nav { display:flex; gap:8px; align-items:center; }
nav a { text-decoration:none; padding:8px 12px; color:#5a6c7d; border-radius:6px; font-weight:600; }
nav a.active { background:#dc3545; color:#fff; }
.container { padding:30px 20px 40px 75px; max-width:1000px; margin:0 auto; }
.profile-card { background:#fff; border:1px solid #e6e9ee; border-radius:16px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.05); }
.profile-header { background:linear-gradient(135deg, #991010 0%, #6b1010 100%); padding:32px 40px; display:flex; align-items:center; gap:20px; }
.profile-avatar { width:90px; height:90px; border-radius:50%; background:rgba(255,255,255,0.2); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:32px; border:4px solid rgba(255,255,255,0.3); }
.profile-info { flex:1; }
.profile-name { font-size:28px; font-weight:800; color:#fff; margin-bottom:8px; }
.profile-meta { display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
.badge { display:inline-block; padding:6px 14px; border-radius:20px; font-weight:700; font-size:12px; text-transform:uppercase; }
.badge.admin-role { background:rgba(255,255,255,0.9); color:#991010; }
.badge.admin-id { background:rgba(255,255,255,0.2); color:#fff; border:2px solid rgba(255,255,255,0.3); }
.profile-body { padding:40px; }
.section-title { font-size:20px; font-weight:700; color:#2c3e50; margin-bottom:24px; display:flex; align-items:center; gap:10px; }
.section-title:before { content:'📋'; font-size:24px; }

/* ✅ Security notice */
.security-notice { background:#fff3cd; border:1px solid #ffc107; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-size:13px; color:#856404; }
.security-notice strong { color:#664d03; }

.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px; } 
.form-group { display:flex; flex-direction:column; }
.form-group label { font-weight:700; color:#4a5568; font-size:13px; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px; }
.form-group input { padding:12px 14px; border:1px solid #dde3ea; border-radius:8px; font-size:14px; background:#fff; transition:all .2s; }
.form-group input:focus { outline:none; border-color:#991010; box-shadow:0 0 0 3px rgba(153,16,16,0.1); }
.form-group input:disabled { background:#f8f9fb; color:#6b7f86; cursor:not-allowed; }

.password-wrapper { position:relative; }
.password-wrapper input { padding-right:45px; width: 100%; }
.password-wrapper button { position:absolute; right:0; top:0; bottom:0; width:40px; background:transparent; border:none; cursor:pointer; font-size:18px; color:#555; display: flex; align-items: center; justify-content: center; z-index: 10; }

.form-actions { display:flex; gap:12px; justify-content:flex-end; padding-top:20px; border-top:2px solid #f3f6f9; }
.btn { padding:12px 24px; border-radius:8px; border:none; cursor:pointer; font-weight:700; font-size:14px; transition:all .2s; display:flex; align-items:center; gap:8px; }
.btn-edit { background:#28a745; color:#fff; }
.btn-save { background:#1d4ed8; color:#fff; }
.btn-cancel { background:#6c757d; color:#fff; }
.btn-logout { background:#dc3545; color:#fff; }

.toast-overlay { position: fixed; inset: 0; background: rgba(34, 49, 62, 0.6); z-index: 9998; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
.toast { background: #fff; padding: 24px; border-radius: 12px; display: flex; align-items: center; gap: 16px; font-weight: 600; min-width: 300px; }
.toast.success { border-top: 4px solid #16a34a; }
.toast.error { border-top: 4px solid #dc2626; }

#menu-toggle { display: none; background: #f1f5f9; border: 2px solid #e2e8f0; font-size: 24px; padding: 5px 12px; border-radius: 8px; cursor: pointer; }

@media (max-width: 1000px) {
    .vertical-bar { display: none; }
    header { padding: 12px 20px; }
    .container { padding: 20px; }
    #menu-toggle { display: block; }
    nav#main-nav { display: none; flex-direction: column; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(20, 0, 0, 0.9); z-index: 2000; padding-top: 80px; }
    nav#main-nav.show { display: flex; }
    nav#main-nav a { color: #fff; font-size: 24px; padding: 15px; text-align: center; }
}
</style>
</head>
<body>


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
          <span class="badge admin-id">ID: <?= htmlspecialchars($user['id']) ?></span>
          <span class="badge admin-role"><?= htmlspecialchars(ucfirst($user['role'] ?? 'N/A')) ?></span>
        </div>
      </div>
    </div>

    <div class="profile-body">
      <div class="section-title">Admin Information</div>

      <!-- ✅ Security Notice -->
      <div class="security-notice">
        <strong>🔒 Security Note:</strong> Your password is encrypted and cannot be displayed. Leave the password field empty if you don't want to change it.
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
          <div class="form-group">
            <label for="profilePassword">New Password (optional)</label>
            <div class="password-wrapper">
              <!-- ✅ Empty by default - password is hashed and can't be shown -->
              <input type="password" id="profilePassword" placeholder="Leave empty to keep current password" disabled>
              <button type="button" onclick="togglePasswordVisibility()">👁️</button>
            </div>
          </div>
        </div>

        <div class="form-actions" id="viewActions">
          <button type="button" class="btn btn-edit" onclick="enableEdit()">✏️ Edit Profile</button>
          <button type="button" class="btn btn-logout" onclick="confirmLogout()">🚪 Logout</button>
        </div>

        <div class="form-actions" id="editActions" style="display:none;">
          <button type="button" class="btn btn-save" onclick="saveProfile()">💾 Save Changes</button>
          <button type="button" class="btn btn-cancel" onclick="cancelEdit()">✕ Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// ✅ Store original data (passwords are NEVER stored in JavaScript)
let originalData = {
  name: <?= json_encode($user['name']) ?>,
  email: <?= json_encode($user['email']) ?>
  // ⚠️ No password stored here - it's hashed and secure!
};

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
}

function cancelEdit() {
  document.getElementById('profileName').value = originalData.name;
  document.getElementById('profileEmail').value = originalData.email;
  document.getElementById('profilePassword').value = ''; // ✅ Clear password field
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
  const password = document.getElementById('profilePassword').value; // ✅ Can be empty

  if (!name || !email) {
    alert('Please fill in all required fields.');
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
      alert(payload.message);
      location.reload();
    } else {
      alert(payload.message);
    }
  })
  .catch(err => {
    console.error(err);
    alert('Network error. Please try again.');
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