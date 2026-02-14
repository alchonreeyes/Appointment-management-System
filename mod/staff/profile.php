<?php
// staff/profile.php - FIXED DATABASE COLUMN NAMES (staff_id, full_name)
session_start();
require_once __DIR__ . '/../database.php';

// ✅ IMPORT ENCRYPTION UTILITY
require_once __DIR__ . '/../../config/encryption_util.php';

// =======================================================
// 1. SECURITY CHECK
// =======================================================
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? null;

if (!$user_id || $user_role !== 'staff') {
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
            // ✅ ENCRYPT email to check for duplicates
            $encryptedEmailToCheck = encrypt_data($email);
            
            // FIX: Use 'staff_id' instead of 'id'
            $stmt_email = $conn->prepare("SELECT staff_id FROM staff WHERE email = ? AND staff_id != ?");
            $stmt_email->bind_param("si", $encryptedEmailToCheck, $user_id);
            $stmt_email->execute();
            
            if ($stmt_email->get_result()->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'This email is already in use by another staff.']);
                exit;
            }
            
            // ✅ ENCRYPT data
            $encryptedName = encrypt_data($name);
            $encryptedEmail = encrypt_data($email);

            // FIX: Use 'full_name' and 'staff_id'
            if (!empty($password)) {
                $encryptedPassword = encrypt_data($password);
                $stmt = $conn->prepare("UPDATE staff SET full_name=?, email=?, password=? WHERE staff_id=?");
                $stmt->bind_param("sssi", $encryptedName, $encryptedEmail, $encryptedPassword, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE staff SET full_name=?, email=? WHERE staff_id=?");
                $stmt->bind_param("ssi", $encryptedName, $encryptedEmail, $user_id);
            }
            
            if ($stmt->execute()) {
                $_SESSION['full_name'] = $name;
                echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
            } else {
                throw new Exception("Execute failed: " . $stmt->error);
            }

        } catch (Exception $e) {
            error_log("UpdateProfile error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
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
// 3. FETCH USER DATA (WITH COLUMN FIXES & SAFE DECRYPT)
// =======================================================
$user = null;

function safe_decrypt($data) {
    if (empty($data)) return '';
    if (!function_exists('decrypt_data')) return $data;
    try {
        $decrypted = decrypt_data($data);
        return ($decrypted !== false && $decrypted !== '') ? $decrypted : $data;
    } catch (Exception $e) {
        return $data;
    }
}

try {
    // FIX: Select 'staff_id' and 'full_name' instead of 'id' and 'name'
    $stmt = $conn->prepare("SELECT staff_id, full_name, email, role, password FROM staff WHERE staff_id = ?");
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        session_destroy();
        header('Location: ../../public/login.php');
        exit;
    }

    // ✅ SAFELY DECRYPT DATA (Using 'full_name' now)
    $user['name'] = safe_decrypt($user['full_name']); // Map full_name to name for easier JS usage
    $user['email'] = safe_decrypt($user['email']);
    $user['password'] = safe_decrypt($user['password']);
    
    // Map staff_id to id for consistency in HTML
    $user['id'] = $user['staff_id'];

} catch (Exception $e) {
    error_log("Profile fetch error: " . $e->getMessage());
    die("Error loading profile data: " . htmlspecialchars($e->getMessage())); 
}

// Calculate initials
$nameToUse = $user['name'] ?? 'Staff';
$nameParts = explode(' ', trim($nameToUse));
if (count($nameParts) > 1) {
    $initials = strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1));
} else if (!empty($nameToUse)) {
    $initials = strtoupper(substr($nameToUse, 0, 1));
    if (strlen($nameToUse) > 1) { 
        $initials .= strtoupper(substr($nameToUse, 1, 1)); 
    }
} else {
    $initials = 'ST';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Profile - Eye Master Clinic</title>
<style>
/* ... (Standard CSS) ... */
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
.badge.staff-role { background:rgba(255,255,255,0.9); color:#991010; }
.badge.staff-id { background:rgba(255,255,255,0.2); color:#fff; border:2px solid rgba(255,255,255,0.3); }
.profile-body { padding:40px; }
.section-title { font-size:20px; font-weight:700; color:#2c3e50; margin-bottom:24px; display:flex; align-items:center; gap:10px; }
.section-title:before { content:'📋'; font-size:24px; }
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px; } 
.form-group { display:flex; flex-direction:column; }
.form-group label { font-weight:700; color:#4a5568; font-size:13px; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px; }
.form-group input { padding:12px 14px; border:1px solid #dde3ea; border-radius:8px; font-size:14px; background:#fff; transition:all .2s; }
.form-group input:focus { outline:none; border-color:#991010; box-shadow:0 0 0 3px rgba(153,16,16,0.1); }
.form-group input:disabled { background:#f8f9fb; color:#6b7f86; cursor:not-allowed; }

/* === PASSWORD WRAPPER === */
.password-wrapper { position:relative; }
.password-wrapper input { padding-right:45px; width: 100%; }
.password-wrapper button { 
    position:absolute; right:0; top:0; bottom:0; width:40px; 
    background:transparent; border:none; cursor:pointer; 
    font-size:18px; color:#555; transition:color .2s; 
    z-index: 10; display: flex; align-items: center; justify-content: center;
}
.password-wrapper button:hover { color:#991010; }

.form-actions { display:flex; gap:12px; justify-content:flex-end; padding-top:20px; border-top:2px solid #f3f6f9; }
.btn { padding:12px 24px; border-radius:8px; border:none; cursor:pointer; font-weight:700; font-size:14px; transition:all .2s; display:flex; align-items:center; gap:8px; }
.btn:hover { transform:translateY(-2px); box-shadow:0 6px 16px rgba(0,0,0,0.15); }
.btn-edit { background:#28a745; color:#fff; }
.btn-edit:hover { background:#218838; }
.btn-save { background:#1d4ed8; color:#fff; }
.btn-save:hover { background:#1e40af; }
.btn-cancel { background:#6c757d; color:#fff; }
.btn-cancel:hover { background:#5a6268; }
.btn-logout { background:#dc3545; color:#fff; }
.btn-logout:hover { background:#c82333; }
.btn:disabled { opacity:0.6; cursor:not-allowed; transform:none; }

/* LOGOUT OVERLAY */
.logout-overlay { display:none; position:fixed; inset:0; background:rgba(2,12,20,0.6); z-index:3000; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(4px); }
.logout-overlay.show { display:flex; animation:fadeIn .2s ease; }
.logout-card { width:450px; max-width:96%; background:#fff; border-radius:16px; padding:0; box-shadow:0 20px 60px rgba(8,15,30,0.25); animation:slideUp .3s ease; }
.logout-header { background:linear-gradient(135deg, #dc3545 0%, #a01c1c 100%); padding:24px 28px; border-radius:16px 16px 0 0; display:flex; align-items:center; gap:12px; }
.logout-title { font-weight:800; color:#fff; font-size:20px; }
.logout-body { padding:28px; font-size:16px; line-height:1.6; color:#333; }
.logout-actions { padding:20px 28px; background:#f8f9fb; border-radius:0 0 16px 16px; display:flex; gap:10px; justify-content:flex-end; border-top:1px solid #e8ecf0; }
.btn-small { padding:10px 18px; border-radius:8px; border:none; cursor:pointer; font-weight:700; font-size:14px; transition:all .2s; }
.btn-small:hover { transform:translateY(-1px); }
.btn-danger { background:#dc3545; color:#fff; }
.btn-danger:hover { background:#c82333; }
.btn-close { background:#fff; color:#4a5568; border:2px solid #e2e8f0; }
@keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
@keyframes slideUp { from { transform:translateY(20px); opacity:0; } to { transform:translateY(0); opacity:1; } }

/* TOAST */
.toast-overlay { position: fixed; inset: 0; background: rgba(34, 49, 62, 0.6); z-index: 9998; display: flex; align-items: center; justify-content: center; opacity: 1; transition: opacity 0.3s ease-out; backdrop-filter: blur(4px); }
.toast { background: #fff; color: #1a202c; padding: 24px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); z-index: 9999; display: flex; align-items: center; gap: 16px; font-weight: 600; min-width: 300px; max-width: 450px; text-align: left; animation: slideUp .3s ease; }
.toast-icon { font-size: 24px; font-weight: 800; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #fff; }
.toast-message { font-size: 15px; line-height: 1.5; }
.toast.success { border-top: 4px solid #16a34a; }
.toast.success .toast-icon { background: #16a34a; }
.toast.error { border-top: 4px solid #dc2626; }
.toast.error .toast-icon { background: #dc2626; }

/* LOADING SCREEN */
#loader-overlay { position: fixed; inset: 0; background: rgba(248, 249, 250, 0.85); z-index: 99999; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: opacity 0.5s ease; backdrop-filter: blur(4px); }
.loader-spinner { width: 50px; height: 50px; border-radius: 50%; border: 5px solid #f3f3f3; border-top: 5px solid #991010; animation: spin 1s linear infinite; }
.loader-text { margin-top: 15px; font-size: 16px; font-weight: 600; color: #5a6c7d; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
@keyframes fadeInContent { from { opacity: 0; } to { opacity: 1; } }

/* MOBILE NAV */
#menu-toggle { display: none; background: #f1f5f9; border: 2px solid #e2e8f0; color: #334155; font-size: 24px; padding: 5px 12px; border-radius: 8px; cursor: pointer; margin-left: 10px; z-index: 2100; }

@media (max-width: 1000px) {
  .vertical-bar { display: none; }
  header { padding: 12px 20px; justify-content: space-between; }
  .logo-section { margin-right: 0; }
  .container { padding: 20px; }
  #menu-toggle { display: block; }
  nav#main-nav { display: flex; flex-direction: column; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(20, 0, 0, 0.9); backdrop-filter: blur(5px); z-index: 2000; padding: 80px 20px 20px 20px; opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0.3s ease; }
  nav#main-nav.show { opacity: 1; visibility: visible; }
  nav#main-nav a { color: #fff; font-size: 24px; font-weight: 700; padding: 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.2); }
  nav#main-nav a:hover { background: rgba(255,255,255,0.1); }
  nav#main-nav a.active { background: none; color: #ff6b6b; }
}
@media (max-width: 768px) { 
    .form-grid { grid-template-columns:1fr; } 
    .profile-header { flex-direction: column; text-align: center; }
    .profile-meta { justify-content: center; }
}
</style>
</head>
<body>

<div id="loader-overlay"><div class="loader-spinner"></div><div class="loader-text">Loading Profile...</div></div>

<div id="main-content" style="display:none;">
    <header>
      <div class="logo-section">
        <img src="../photo/LOGO.jpg" alt="Logo"> <strong>EYE MASTER CLINIC</strong>
      </div>
      <button id="menu-toggle" aria-label="Open navigation">☰</button>
      <nav id="main-nav">
        <a href="staff_dashboard.php">🏠 Dashboard</a>
        <a href="appointment.php">📅 Appointments</a>
        <a href="patient_record.php">📘 Patient Record</a>
        <a href="product.php">💊 Product & Services</a>
        <a href="profile.php" class="active">🔍  Profile</a>
      </nav>
    </header>

    <div class="container">
      <div class="profile-card">
        <div class="profile-header">
          <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
          <div class="profile-info">
            <div class="profile-name"><?= htmlspecialchars($user['name']) ?></div>
            <div class="profile-meta">
              <span class="badge staff-id">ID: <?= htmlspecialchars($user['id']) ?></span>
              <span class="badge staff-role"><?= htmlspecialchars(ucfirst($user['role'] ?? 'N/A')) ?></span>
            </div>
          </div>
        </div>

        <div class="profile-body">
          <div class="section-title">Staff Information</div>

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
                <label for="profilePassword">Password</label>
                <div class="password-wrapper">
                  <input type="password" id="profilePassword" value="<?= htmlspecialchars($user['password']) ?>" disabled>
                  <button type="button" onclick="togglePasswordVisibility()" title="Show/Hide Password">👁️</button>
                </div>
              </div>

            </div>

            <div class="form-actions" id="viewActions">
              <button type="button" class="btn btn-edit" onclick="enableEdit()">✏️ Edit Profile</button>
              <button type="button" class="btn btn-logout" onclick="openLogoutModal()">🚪 Logout</button>
            </div>

            <div class="form-actions" id="editActions" style="display:none;">
              <button type="button" class="btn btn-save" onclick="saveProfile()">💾 Save Changes</button>
              <button type="button" class="btn btn-cancel" onclick="cancelEdit()">✕ Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </div>
</div>

<div id="logoutOverlay" class="logout-overlay" aria-hidden="true">
  <div class="logout-card" role="dialog">
       <div class="logout-header"><span style="font-size:24px;">⚠️</span><div class="logout-title">Confirm Logout</div></div>
       <div class="logout-body">Are you sure you want to logout?<br><br><strong>You will need to login again.</strong></div>
       <div class="logout-actions">
           <button class="btn-small btn-danger" onclick="confirmLogout()">Yes, Logout</button>
           <button class="btn-small btn-close" onclick="closeLogoutModal()">Cancel</button>
       </div>
   </div>
</div>
    
<script>
// ✅ Store DECRYPTED data in JavaScript (Including Password)
let originalData = {
  name: <?= json_encode($user['name']) ?>,
  email: <?= json_encode($user['email']) ?>,
  password: <?= json_encode($user['password']) ?>
};

function showToast(msg, type = 'success') {
    const overlay = document.createElement('div');
    overlay.className = 'toast-overlay';
    overlay.innerHTML = `<div class="toast ${type}"><div class="toast-icon">${type === 'success' ? '✓' : '✕'}</div><div class="toast-message">${msg}</div></div>`;
    document.body.appendChild(overlay);
    const timer = setTimeout(() => {
        overlay.style.opacity = '0';
        overlay.addEventListener('transitionend', () => overlay.remove(), { once: true });
    }, 2500);
}

function togglePasswordVisibility() {
    const input = document.getElementById('profilePassword');
    const btn = document.querySelector('.password-wrapper button');
    if (!input || !btn) return;
    if (input.type === "password") {
        input.type = "text";
        btn.textContent = "🙈"; 
    } else {
        input.type = "password";
        btn.textContent = "👁️";
    }
}
    
function enableEdit() {
  document.getElementById('profileName').disabled = false;
  document.getElementById('profileEmail').disabled = false;
  document.getElementById('profilePassword').disabled = false;
  
  // We keep the password in the field so they can edit it directly
  document.getElementById('viewActions').style.display = 'none';
  document.getElementById('editActions').style.display = 'flex';
  document.getElementById('profileName').focus();
  showToast('You can now edit your profile', 'success');
}
    
function cancelEdit() {
  // Revert to original decrypted data
  document.getElementById('profileName').value = originalData.name;
  document.getElementById('profileEmail').value = originalData.email;
  document.getElementById('profilePassword').value = originalData.password;

  document.getElementById('profileName').disabled = true;
  document.getElementById('profileEmail').disabled = true;
  document.getElementById('profilePassword').disabled = true;

  const passInput = document.getElementById('profilePassword');
  passInput.type = 'password';
  const passBtn = document.querySelector('.password-wrapper button');
  if(passBtn) passBtn.textContent = '👁️';

  document.getElementById('viewActions').style.display = 'flex';
  document.getElementById('editActions').style.display = 'none';
  showToast('Changes cancelled', 'error');
}
    
function saveProfile() {
  const name = document.getElementById('profileName').value.trim();
  const email = document.getElementById('profileEmail').value.trim();
  const password = document.getElementById('profilePassword').value;

  if (!name || !email) {
    showToast('Please fill in Name and Email fields.', 'error'); 
    return;
  }
  if (password && password.length < 6) {
    showToast('Password must be at least 6 characters long.', 'error'); 
    return;
  }

  const formData = new URLSearchParams();
  formData.append('action', 'updateProfile');
  formData.append('name', name);
  formData.append('email', email);
  if (password) {
    formData.append('password', password);
  }
    
  const saveBtn = document.querySelector('#editActions .btn-save');
  saveBtn.disabled = true;
  saveBtn.innerHTML = '⏳ Saving...';

  fetch('profile.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: formData
  })
  .then(res => res.json())
  .then(payload => {
    saveBtn.disabled = false;
    saveBtn.innerHTML = '💾 Save Changes';

    if (payload.success) {
      originalData.name = name;
      originalData.email = email;
      if (password) originalData.password = password;

      document.getElementById('profileName').disabled = true;
      document.getElementById('profileEmail').disabled = true;
      document.getElementById('profilePassword').disabled = true;

      document.getElementById('viewActions').style.display = 'flex';
      document.getElementById('editActions').style.display = 'none';
      document.querySelector('.profile-name').textContent = name;

      showToast(payload.message, 'success');
      // No reload needed if we update the DOM directly
      // setTimeout(() => window.location.reload(), 1500);
    } else {
      showToast(payload.message || 'Failed to update profile.', 'error');
    }
  })
  .catch(err => {
    console.error(err);
    saveBtn.disabled = false;
    saveBtn.innerHTML = '💾 Save Changes';
    showToast('Network error while saving profile.', 'error');
  });
}
    
function openLogoutModal() {
    const overlay = document.getElementById('logoutOverlay');
    overlay.classList.add('show');
    overlay.setAttribute('aria-hidden', 'false');
}
function closeLogoutModal() {
    const overlay = document.getElementById('logoutOverlay');
    overlay.classList.remove('show');
    overlay.setAttribute('aria-hidden', 'true');
}
function confirmLogout() {
  fetch('profile.php', { 
      method: 'POST', 
      headers: {'Content-Type':'application/x-www-form-urlencoded'}, 
      body: new URLSearchParams({action: 'logout'}) 
    })
    .then(res => res.json())
    .then(payload => {
      if (payload.success) {
        showToast('Logging out...', 'success');
        setTimeout(() => { window.location.href = '../../public/login.php'; }, 1000);
      } else { 
        showToast('Logout failed.', 'error'); 
      }
    })
    .catch(err => { 
      console.error(err); 
      showToast('Logout network error.', 'error'); 
      setTimeout(() => { window.location.href = '../../public/login.php'; }, 1500); 
    });
}

// LOADER & INIT
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        const loader = document.getElementById('loader-overlay');
        const content = document.getElementById('main-content');
        if (loader) {
            loader.style.opacity = '0';
            loader.addEventListener('transitionend', () => { loader.style.display = 'none'; }, { once: true });
        }
        if (content) {
            content.style.display = 'block';
            content.style.animation = 'fadeInContent 0.2s ease';
        }
    }, 1000);

    const menuToggle = document.getElementById('menu-toggle');
    const mainNav = document.getElementById('main-nav');
    if (menuToggle && mainNav) {
        menuToggle.addEventListener('click', function() {
            mainNav.classList.toggle('show');
            this.innerHTML = mainNav.classList.contains('show') ? '✕' : '☰';
        });
        mainNav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                mainNav.classList.remove('show');
                menuToggle.innerHTML = '☰';
            });
        });
    }
});
</script>

</body>
</html>