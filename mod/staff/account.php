<?php
// =======================================================
// UPDATED: Staff Account Management with Encryption Fixes,
// Strong Password, Email OTP Verification, & Real-Time Validation
// =======================================================

// Start session
session_start();

// Database connections
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../../config/encryption_util.php'; 

// Load PHPMailer for OTP
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Set Timezone
date_default_timezone_set('Asia/Manila');

// =======================================================
// 1. SECURITY CHECK
// =======================================================
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'staff') {
    if (isset($_POST['action'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    } else {
        header('Location: ../login.php'); 
    }
    exit;
}

// =======================================================
// 2. SERVER-SIDE ACTION HANDLING
// =======================================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    // --- REAL-TIME FIELD VALIDATION ---
    if ($action === 'validate_field') {
        $field = $_POST['field'] ?? '';
        $value = trim($_POST['value'] ?? '');
        $staff_id = $_POST['staff_id'] ?? '';
        $error = '';

        if ($field === 'full_name') {
            if (empty($value)) $error = 'Full name is required.';
            elseif (strlen($value) < 3) $error = 'Full name must be at least 3 characters.';
        } 
        elseif ($field === 'email') {
            if (empty($value)) {
                $error = 'Email is required.';
            } elseif (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email format.';
            } else {
                $domain = substr(strrchr($value, "@"), 1);
                if (!checkdnsrr($domain, "MX")) {
                    $error = 'Email domain is not valid or cannot receive emails.';
                } else {
                    // Duplicate Check (Handling Encrypted DB)
                    try {
                        $check = $conn->query("SELECT staff_id, email FROM staff");
                        while ($row = $check->fetch_assoc()) {
                            if (decrypt_data($row['email']) === $value && $row['staff_id'] != $staff_id) {
                                $error = 'This email is already registered.';
                                break;
                            }
                        }
                    } catch(Exception $e) {
                        $error = 'Database error verifying email.';
                    }
                }
            }
        } 
        elseif ($field === 'password') {
            // STRONG PASSWORD REGEX
            if (!empty($value)) {
                if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $value)) {
                    $error = 'Weak password. Must have min 8 chars, 1 Upper, 1 Number, 1 Special Char.';
                }
            } elseif (empty($value) && empty($staff_id)) {
                $error = 'Password is required for new staff accounts.';
            }
        }

        echo json_encode(['valid' => empty($error), 'message' => $error]);
        exit;
    }

    // --- SEND / RESEND OTP ACTION ---
    if ($action === 'sendOtp') {
        $new_email = trim($_POST['email'] ?? '');
        
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email address format.']);
            exit;
        }

        $domain = substr(strrchr($new_email, "@"), 1);
        if (!checkdnsrr($domain, "MX")) {
            echo json_encode(['success' => false, 'message' => 'Email domain cannot receive emails.']);
            exit;
        }

        // Duplicate Check before sending OTP
        $staff_id = $_POST['staff_id'] ?? '';
        $check = $conn->query("SELECT staff_id, email FROM staff");
        while ($row = $check->fetch_assoc()) {
            if (decrypt_data($row['email']) === $new_email && $row['staff_id'] != $staff_id) {
                echo json_encode(['success' => false, 'message' => 'This email is already in use by another staff.']);
                exit;
            }
        }

        // Generate 6-digit OTP
        $otp = rand(100000, 999999);
        $_SESSION['staff_email_otp'] = $otp;
        $_SESSION['staff_email_pending'] = $new_email;
        $_SESSION['staff_email_expiry'] = time() + 300; // 5 minutes validity

        // Send Email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'rogerjuancito0621@gmail.com'; 
            $mail->Password   = 'rhtstropgtnfgipb';          
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;
            
            $mail->setFrom('no-reply@eyecareclinic.com', 'Eye Master Clinic Security');
            $mail->addAddress($new_email);
            $mail->isHTML(true);
            $mail->Subject = 'Staff Email Verification Code';
            $mail->Body    = "
                <div style='font-family:sans-serif; padding:20px; background:#f4f4f4;'>
                    <div style='background:#fff; padding:20px; border-radius:8px; max-width:500px; margin:0 auto; border-top:5px solid #16a34a;'>
                        <h2 style='color:#16a34a;'>Verification Code</h2>
                        <p>A request was made to link this email to a Staff Account. Please provide this code to your staffistrator:</p>
                        <h1 style='font-size:32px; letter-spacing:5px; color:#333; text-align:center; padding:10px; background:#f9f9f9; border-radius:5px;'>{$otp}</h1>
                        <p style='color:#666; font-size:12px;'>This code is valid for 5 minutes.</p>
                    </div>
                </div>";

            $mail->send();
            echo json_encode(['success' => true, 'message' => 'Verification code sent to the provided email.']);
        } catch (Exception $e) {
            error_log("OTP Mail Error: " . $mail->ErrorInfo);
            echo json_encode(['success' => false, 'message' => 'Failed to send OTP email.']);
        }
        exit;
    }


    // --- VIEW DETAILS ---
    if ($action === 'viewDetails') {
        $id = $_POST['id'] ?? '';
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Missing ID']);
            exit;
        }
        
        try {
            $stmt = $conn->prepare("SELECT staff_id, full_name, email, password, role, status FROM staff WHERE staff_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $staff = $stmt->get_result()->fetch_assoc();

            if (!$staff) {
                echo json_encode(['success' => false, 'message' => 'Staff not found']);
                exit;
            }
            
            // DECRYPT DATA FOR VIEW MODAL
            $staff['full_name'] = decrypt_data($staff['full_name']);
            $staff['email'] = decrypt_data($staff['email']);
            $staff['password'] = decrypt_data($staff['password']); 
            
            echo json_encode(['success' => true, 'data' => $staff]);
        } catch (Exception $e) {
            error_log("ViewDetails error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error fetching details.']);
        }
        exit;
    }

    // --- ADD STAFF ---
    if ($action === 'addStaff') {
        $name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = trim($_POST['role'] ?? 'staff');
        $otp = trim($_POST['otp'] ?? '');

        if (!$name || !$email || !$password) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            exit;
        }

        // Validate OTP
        if (empty($otp) || !isset($_SESSION['staff_email_otp']) || 
            $otp != $_SESSION['staff_email_otp'] || 
            $email !== $_SESSION['staff_email_pending'] || 
            time() > $_SESSION['staff_email_expiry']) {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired Verification Code.']);
            exit;
        }

        // Validate Strong Password
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
            echo json_encode(['success' => false, 'message' => 'Password does not meet security requirements.']);
            exit;
        }

        try {
            // Final Duplicate check just in case
            $check = $conn->query("SELECT email FROM staff");
            while ($row = $check->fetch_assoc()) {
                if (decrypt_data($row['email']) === $email) {
                    echo json_encode(['success' => false, 'message' => 'This email is already registered.']);
                    exit;
                }
            }

            $encryptedName = encrypt_data($name);
            $encryptedEmail = encrypt_data($email);
            $encryptedPassword = encrypt_data($password); // Still encrypting to match your login-action.php setup

            $stmt = $conn->prepare("INSERT INTO staff (full_name, email, password, role, status) VALUES (?, ?, ?, ?, 'Active')");
            $stmt->bind_param("ssss", $encryptedName, $encryptedEmail, $encryptedPassword, $role);
            
            if ($stmt->execute()) {
                // Clear OTP Session
                unset($_SESSION['staff_email_otp']);
                unset($_SESSION['staff_email_pending']);
                unset($_SESSION['staff_email_expiry']);
                
                echo json_encode(['success' => true, 'message' => 'Staff account created successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save staff account.']);
            }
        } catch (Exception $e) {
            error_log("AddStaff error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    // --- EDIT STAFF ---
    if ($action === 'editStaff') {
        $id = $_POST['staff_id'] ?? '';
        $name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $status = $_POST['status'] ?? 'Active';
        $otp = trim($_POST['otp'] ?? '');

        if (!$id || !$name || !$email) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
            exit;
        }

        try {
            // Get current DB email to check if it changed
            $stmt = $conn->prepare("SELECT email FROM staff WHERE staff_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $currUser = $stmt->get_result()->fetch_assoc();
            $current_db_email = decrypt_data($currUser['email']);

            // If Email Changed, verify OTP
            if ($email !== $current_db_email) {
                if (empty($otp)) {
                    echo json_encode(['success' => false, 'message' => 'REQUIRE_OTP']); 
                    exit;
                }
                
                if (!isset($_SESSION['staff_email_otp']) || 
                    $otp != $_SESSION['staff_email_otp'] || 
                    $email !== $_SESSION['staff_email_pending'] || 
                    time() > $_SESSION['staff_email_expiry']) {
                    echo json_encode(['success' => false, 'message' => 'Invalid or expired Verification Code.']);
                    exit;
                }
            }

            // Strong Password Validation (if changed)
            if (!empty($password)) {
                if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
                    echo json_encode(['success' => false, 'message' => 'Password does not meet security requirements.']);
                    exit;
                }
            }

            // Duplicate Check
            $check = $conn->query("SELECT staff_id, email FROM staff");
            while ($row = $check->fetch_assoc()) {
                if (decrypt_data($row['email']) === $email && $row['staff_id'] != $id) {
                    echo json_encode(['success' => false, 'message' => 'Another staff member has this email.']);
                    exit;
                }
            }

            $encryptedName = encrypt_data($name);
            $encryptedEmail = encrypt_data($email);

            if (!empty($password)) {
                $encryptedPassword = encrypt_data($password);
                $stmt = $conn->prepare("UPDATE staff SET full_name=?, email=?, password=?, status=? WHERE staff_id=?");
                $stmt->bind_param("ssssi", $encryptedName, $encryptedEmail, $encryptedPassword, $status, $id);
            } else {
                $stmt = $conn->prepare("UPDATE staff SET full_name=?, email=?, status=? WHERE staff_id=?");
                $stmt->bind_param("sssi", $encryptedName, $encryptedEmail, $status, $id);
            }
            
            if ($stmt->execute()) {
                unset($_SESSION['staff_email_otp']);
                unset($_SESSION['staff_email_pending']);
                unset($_SESSION['staff_email_expiry']);
                echo json_encode(['success' => true, 'message' => 'Staff updated successfully!']);
            }
            
        } catch (Exception $e) {
            error_log("EditStaff error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error during update.']);
        }
        exit;
    }

    // --- REMOVE STAFF ---
    if ($action === 'removeStaff') {
        $id = $_POST['id'] ?? '';
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Missing ID']);
            exit;
        }
        try {
            $stmt = $conn->prepare("DELETE FROM staff WHERE staff_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Staff removed successfully']);
        } catch (Exception $e) {
            error_log("RemoveStaff error: " . $e->getMessage());
            if ($conn->errno === 1451) {
                echo json_encode(['success' => false, 'message' => 'Cannot remove staff. They are assigned to existing appointments.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error during removal.']);
            }
        }
        exit;
    }
}


// ============================================
// 4. FETCH STAFF LIST & STATS
// ============================================
$statusFilter = $_GET['status'] ?? 'All';
$search = trim($_GET['search'] ?? '');

$query = "SELECT staff_id, full_name, email, password, status, role FROM staff WHERE 1=1";
$params = [];
$paramTypes = "";

if ($statusFilter !== 'All') {
    $query .= " AND status = ?";
    $params[] = $statusFilter;
    $paramTypes .= "s";
}

$query .= " ORDER BY staff_id DESC";

try {
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($paramTypes, ...$params);
    }
    $stmt->execute();
    $allStaff = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // DECRYPT DATA FOR TABLE
    $staffMembers = [];
    foreach ($allStaff as $staff) {
        $staff['full_name'] = decrypt_data($staff['full_name']);
        $staff['email'] = decrypt_data($staff['email']);
        $staff['password'] = decrypt_data($staff['password']); 
        
        // PHP-SIDE SEARCH (Since DB is encrypted)
        if ($search !== '') {
            $searchLower = strtolower($search);
            if (
                stripos($staff['full_name'], $search) === false &&
                stripos($staff['email'], $search) === false &&
                stripos((string)$staff['staff_id'], $search) === false
            ) {
                continue; 
            }
        }
        $staffMembers[] = $staff;
    }
    
} catch (Exception $e) {
    error_log("Fetch Staff List error: " . $e->getMessage());
    $staffMembers = [];
}

// Stats Calculation
$countSql = "SELECT
    COALESCE(SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END), 0) AS active,
    COALESCE(SUM(CASE WHEN status = 'Inactive' THEN 1 ELSE 0 END), 0) AS inactive,
    COALESCE(COUNT(*), 0) AS total
    FROM staff WHERE 1=1";

try {
    $stmt_stats = $conn->prepare($countSql);
    $stmt_stats->execute();
    $stats = $stmt_stats->get_result()->fetch_assoc();
} catch (Exception $e) {
    $stats = ['active' => 0, 'inactive' => 0, 'total' => 0];
}

$activeCount = (int)($stats['active'] ?? 0);
$inactiveCount = (int)($stats['inactive'] ?? 0);
$totalCount = (int)($stats['total'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Account - Eye Master Clinic</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* --- 100% RESPONSIVE BASE --- */
* { margin:0; padding:0; box-sizing:border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
body { background:#f8f9fa; color:#223; max-width: 100vw; overflow-x: hidden; padding-bottom: 40px; }

/* --- SPIN ANIMATION ADDED HERE --- */
@keyframes spin { 
    100% { transform: rotate(360deg); } 
}

/* --- PAGE LOADER (Runs on File Open) --- */
.page-loader { 
    position: fixed; inset: 0; background: #f8f9fa; z-index: 99999; 
    display: flex; flex-direction: column; align-items: center; justify-content: center; 
    transition: opacity 0.4s ease; 
}
.page-loader .spinner { 
    width: 40px; height: 40px; border: 4px solid #e2e8f0; border-top: 4px solid #991010; 
    border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 15px; 
}

/* Sidebar (Vertical Bar) */
.vertical-bar { position:fixed; left:0; top:0; width:55px; height:100vh; background:linear-gradient(180deg,#991010 0%,#6b1010 100%); z-index:1000; }
.vertical-bar .circle { width:70px; height:70px; background:#b91313; border-radius:50%; position:absolute; left:-8px; top:45%; transform:translateY(-50%); border:4px solid #5a0a0a; }

/* Header */
header { display:flex; align-items:center; background:#fff; padding:12px 75px; box-shadow:0 2px 4px rgba(0,0,0,0.05); position:relative; z-index:100; justify-content: space-between;}
.logo-section { display:flex; align-items:center; gap:10px; margin-right:auto; }
.logo-section img { height:32px; border-radius:4px; object-fit:cover; }
nav { display:flex; gap:8px; align-items:center; }
nav a { text-decoration:none; padding:8px 12px; color:#5a6c7d; border-radius:6px; font-weight:600; font-size: 14px; }
nav a.active { background:#dc3545; color:#fff; }

/* Container */
.container { padding:20px 75px 40px 75px; max-width:100%; margin:0 auto; }
.header-row { margin-bottom:18px; }
.header-row h2 { font-size:20px; color:#2c3e50; }

/* Filters & Inputs */
.filters { display:flex; gap:10px; align-items:center; margin-bottom:16px; flex-wrap:wrap; width: 100%; }
select, input[type="text"], input[type="email"], input[type="password"] { 
    padding:9px 12px; 
    border:1px solid #dde3ea; 
    border-radius:8px; 
    background:#fff; 
    font-size: 14px; 
    transition: border-color 0.2s;
}
select:focus, input[type="text"]:focus, input[type="email"]:focus, input[type="password"]:focus {
    border-color: #991010; outline: none;
}
#searchInput { width: 333px; }
.filters .button-group { margin-left: auto; display: flex; gap: 10px; align-items: center; }

/* Buttons */
button.btn { padding:9px 12px; border-radius:8px; border:none; cursor:pointer; font-weight:700; }
.add-btn { background:#16a34a; color:#fff; padding:10px 16px; border-radius:8px; border:none; cursor:pointer; font-weight:700; transition:all .2s; font-size: 14px;}
.add-btn:hover { background:#15803d; transform:translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }

/* Stats Cards */
.stats { display:flex; gap:16px; margin-bottom:18px; flex-wrap: wrap; justify-content: flex-start; }
.stat-card { background:#fff; border:1px solid #e6e9ee; border-radius:10px; padding:18px 24px; text-align:center; flex: 1 1 250px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
.stat-card h3 { margin-bottom:6px; font-size:24px; color:#991010; }
.stat-card p { color:#6b7f86; font-size:13px; font-weight: 600; text-transform: uppercase; }

/* Table */
.table-container { background: #fff; border-radius: 10px; border: 1px solid #e6e9ee; padding: 0; overflow-x: auto; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
.custom-table { width: 100%; border-collapse: collapse; min-width: 900px; table-layout: fixed; }
.custom-table th { background: #f1f5f9; color: #4a5568; font-weight: 700; font-size: 13px; text-transform: uppercase; padding: 12px 15px; text-align: left; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
.custom-table td { padding: 12px 15px; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 14px; vertical-align: middle; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.custom-table tbody tr:hover { background: #f8f9fb; }

/* Table Elements */
.staff-avatar { width:45px; height:45px; border-radius:50%; background:linear-gradient(135deg, #991010 0%, #6b1010 100%); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; }
.action-btn { padding:6px 10px; border-radius:6px; border:none; color:#fff; font-weight:600; cursor:pointer; font-size:12px; transition:all .2s; margin-right: 4px; }
.action-btn:hover { transform:translateY(-1px); box-shadow:0 2px 4px rgba(0,0,0,0.15); }
.view { background:#1d4ed8; }
.edit { background:#28a745; }
.remove { background:#dc3545; }
.badge { display:inline-block; padding:4px 10px; border-radius:20px; font-weight:700; font-size:11px; text-transform:uppercase; }
.badge.active { background:#dcfce7; color:#16a34a; border:1px solid #86efac; }
.badge.inactive { background:#fee; color:#dc2626; border:1px solid #fca5a5; }

/* Password Display in Table */
.password-display { display:flex; align-items:center; gap:8px; }
.password-display input { border:none; background:transparent; font-family:monospace; font-size:14px; width:120px; padding:0; outline: none; }
.password-display button { background:none; border:none; cursor:pointer; font-size:16px; padding:4px; opacity: 0.6; transition: opacity 0.2s; }
.password-display button:hover { opacity: 1; }

/* FIXED OVERLAPPING MODALS Z-INDEX */
.detail-overlay, .form-overlay, .remove-overlay { display: none; position: fixed; inset: 0; background: rgba(2, 12, 20, 0.6); z-index: 3000; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px); }
.otp-overlay { display: none; position: fixed; inset: 0; background: rgba(2, 12, 20, 0.6); z-index: 3500; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px); }
.detail-overlay.show, .form-overlay.show, .remove-overlay.show, .otp-overlay.show { display: flex; animation: fadeIn .2s ease; }
@keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
.detail-card, .form-card, .otp-card { max-width: 96%; background: #fff; border-radius: 16px; padding: 0; box-shadow: 0 20px 60px rgba(8, 15, 30, 0.25); animation: slideUp .3s ease; }
.detail-card { width: 700px; }
.form-card { width: 500px; }
.otp-card { width: 400px; padding: 24px; text-align: center; }
@keyframes slideUp { from { transform:translateY(20px); opacity:0; } to { transform:translateY(0); opacity:1; } }
.detail-header { background: linear-gradient(135deg, #991010 0%, #6b1010 100%); padding: 20px 24px; border-radius: 16px 16px 0 0; display: flex; justify-content: space-between; align-items: center; }
.detail-title { font-weight: 800; color: #fff; font-size: 20px; display: flex; align-items: center; gap: 10px; }
.detail-id { background: rgba(255, 255, 255, 0.2); color: #fff; padding: 4px 12px; border-radius: 20px; font-weight: 700; font-size: 13px; }
.detail-content { padding: 24px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.detail-section { display: flex; flex-direction: column; gap: 14px; }
.detail-row { background: #f8f9fb; padding: 12px 14px; border-radius: 10px; border: 1px solid #e8ecf0; }
.detail-label { font-weight: 700; color: #4a5568; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 6px; }
.detail-value { color: #1a202c; font-weight: 600; font-size: 14px; }
.form-body { padding: 24px; }

/* Real-Time Validation Styling */
.form-group { margin-bottom: 12px; }
.form-group label { display: block; font-weight: 700; color: #4a5568; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
.form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 1px solid #dde3ea; border-radius: 8px; font-size: 14px; transition: border-color 0.2s;}
.form-group input:focus { outline: none; border-color: #991010; }
.error-msg { color: #dc2626; font-size: 12px; font-weight: 600; margin-top: 4px; display: none; }
.input-error { border-color: #dc2626 !important; background-color: #fef2f2 !important; }
.input-success { border-color: #16a34a !important; }

.form-group .form-password-wrapper { position: relative; }
.form-group .form-password-wrapper input[type="password"], .form-group .form-password-wrapper input[type="text"] { padding-right: 45px; }
.form-group .form-password-wrapper button { position: absolute; right: 1px; top: 1px; bottom: 1px; width: 40px; background: transparent; border: none; cursor: pointer; font-size: 18px; color: #555; }

.remove-body { padding: 24px; font-size: 15px; line-height: 1.6; color: #333; }
.remove-body strong { color: #c82333; font-weight: 700; }
.detail-actions, .form-actions { padding: 16px 24px; background: #f8f9fb; border-radius: 0 0 16px 16px; display: flex; gap: 10px; justify-content: flex-end; border-top: 1px solid #e8ecf0; }

.btn-small { padding: 10px 18px; border-radius: 8px; border: none; cursor: pointer; font-weight: 700; font-size: 13px; transition: all .2s; }
.btn-small:hover { transform: translateY(-1px); }
.btn-close { background: #fff; color: #4a5568; border: 2px solid #e2e8f0; }
.btn-save { background: #28a745; color: #fff; }
.btn-save:hover { background: #218838; }
.btn-save:disabled { background: #cbd5e1; cursor: not-allowed; transform: none; box-shadow: none; }
.btn-danger { background: #dc3545; color: #fff; }
.btn-danger:hover { background: #c82333; }

/* OTP Styles */
.otp-header { font-size: 40px; margin-bottom: 10px; }
.otp-inputs { display: flex; gap: 10px; justify-content: center; margin: 20px 0; }
.otp-inputs input { width: 45px; height: 50px; font-size: 24px; font-weight: bold; text-align: center; border: 2px solid #cbd5e1; border-radius: 8px; }
.otp-inputs input:focus { border-color: #991010; outline: none; }
.resend-box { margin-top: 15px; font-size: 13px; color: #64748b;}
.resend-link { color: #1d4ed8; text-decoration: none; font-weight: 700; cursor: pointer; transition: color 0.2s; }
.resend-link.disabled { color: #94a3b8; cursor: not-allowed; text-decoration: none; }

/* SUCCESS MODAL DESIGN */
.success-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.4); z-index: 4000; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
.success-modal-overlay.show { display: flex; animation: fadeIn 0.3s ease; }
.success-modal-card { background: #fff; padding: 25px 35px; border-radius: 12px; box-shadow: 0 15px 40px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 20px; max-width: 90%; width: auto; animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
@keyframes popIn { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
.success-icon-circle { width: 50px; height: 50px; background-color: #28a745; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.success-icon-circle svg { width: 28px; height: 28px; fill: none; stroke: #fff; stroke-width: 3.5; stroke-linecap: round; stroke-linejoin: round; stroke-dasharray: 50; stroke-dashoffset: 50; animation: checkDraw 0.6s ease forwards; }
@keyframes checkDraw { to { stroke-dashoffset: 0; } }
.success-text { font-size: 16px; font-weight: 600; color: #333; line-height: 1.4; }

/* Old Toast for errors */
.toast-overlay { position: fixed; inset: 0; background: transparent; pointer-events: none; z-index: 9998; display: flex; align-items: flex-end; justify-content: center; padding-bottom: 30px; }
.toast { pointer-events: auto; background: #fff; color: #1a202c; padding: 16px 20px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); z-index: 9999; display: flex; align-items: center; gap: 14px; font-weight: 600; min-width: 300px; max-width: 400px; text-align: left; animation: slideUp .3s ease; border: 1px solid #e2e8f0; }
.toast-icon { font-size: 18px; font-weight: 800; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: #fff; }
.toast-message { font-size: 14px; line-height: 1.4; }
.toast.error { border-left: 5px solid #dc2626; } .toast.error .toast-icon { background: #dc2626; }

/* Loader */
#actionLoader { display: none; position: fixed; inset: 0; background: rgba(2, 12, 20, 0.6); z-index: 9990; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(4px); }
#actionLoader.show { display: flex; animation: fadeIn .2s ease; }
#actionLoader .loader-card { background: #fff; border-radius: 12px; padding: 24px; display: flex; align-items: center; gap: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
#actionLoader .loader-spinner { width: 32px; height: 32px; border-radius: 50%; border: 4px solid #f3f3f3; border-top: 4px solid #991010; animation: spin 1s linear infinite; flex-shrink: 0; }
#actionLoaderText { font-weight: 600; color: #334155; font-size: 15px; }

/* Mobile */
#menu-toggle { display: none; background: #f1f5f9; border: 2px solid #e2e8f0; font-size: 24px; padding: 5px 12px; border-radius: 8px; cursor: pointer; margin-left: 10px; z-index: 2100; }

@media (max-width: 1000px) {
  .vertical-bar { display: none; }
  header { padding: 12px 20px; justify-content: space-between; }
  .logo-section { margin-right: 0; }
  .container { padding: 20px; }
  .filters { flex-direction: column; align-items: stretch; }
  #searchInput { width: 100%; margin: 0; }
  .filters .button-group { margin-left: 0; justify-content: space-between; width: 100%; }
  .add-btn { width: 100%; }
  #menu-toggle { display: block; }
  nav#main-nav { display: flex; flex-direction: column; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(20, 0, 0, 0.95); backdrop-filter: blur(10px); z-index: 2000; padding: 80px 20px 20px 20px; opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0.3s ease; }
  nav#main-nav.show { opacity: 1; visibility: visible; }
  nav#main-nav a { color: #fff; font-size: 20px; font-weight: 700; padding: 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); width: 100%; }
  nav#main-nav a.active { background: none; color: #ff6b6b; border-bottom: 1px solid #ff6b6b; }
}


</style>
</head>
<body>

<div id="pageLoader" class="page-loader">
    <div class="spinner"></div>
    <div style="font-weight: 600; color: #4a5568;">Loading Staff Account..</div>
</div>

<div id="main-content">

    <div id="actionLoader" aria-hidden="true">
        <div class="loader-card">
            <div class="loader-spinner"></div>
            <p id="actionLoaderText">Processing...</p>
        </div>
    </div>
    
    <div id="successModal" class="success-modal-overlay">
        <div class="success-modal-card">
            <div class="success-icon-circle">
                <svg viewBox="0 0 24 24">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>
            <div class="success-text" id="successMessageText">Action successful!</div>
        </div>
    </div>

    <div id="otpModalOverlay" class="otp-overlay" aria-hidden="true">
        <div class="otp-card" role="dialog" aria-modal="true">
            <div class="otp-header">✉️</div>
            <h3 style="margin-bottom: 10px; color: #1a202c;">Verify Email</h3>
            <div style="color: #4a5568; font-size: 14px; margin-bottom: 20px;">
                We sent a 6-digit code to the new staff email address. Enter it below to confirm the account.
            </div>
            
            <div class="otp-inputs" id="otpContainer">
                <input type="text" maxlength="1" id="otp1">
                <input type="text" maxlength="1" id="otp2">
                <input type="text" maxlength="1" id="otp3">
                <input type="text" maxlength="1" id="otp4">
                <input type="text" maxlength="1" id="otp5">
                <input type="text" maxlength="1" id="otp6">
            </div>

            <button class="btn-save" style="width:100%; margin-bottom: 10px; padding: 12px; border-radius: 8px; font-weight:bold; border: none; cursor:pointer;" onclick="verifyOtpAndSave()">Verify & Save</button>
            <button class="btn-close" style="width:100%; padding: 12px; border-radius: 8px; font-weight:bold; cursor:pointer;" onclick="closeOtpModal()">Cancel</button>
            
            <div class="resend-box">
                Didn't receive the code? <br>
                <span id="resendBtn" class="resend-link" onclick="resendOtp()">Resend Code</span>
                <span id="timerText" style="display:none; color:#64748b;">in <b id="timerCount">60</b>s</span>
            </div>
        </div>
    </div>

    <header>
      <div class="logo-section">
        <img src="../photo/LOGO.jpg" alt="Logo"> <strong>EYE MASTER CLINIC</strong>
      </div>
      <button id="menu-toggle" aria-label="Open navigation"><i class="fa-solid fa-bars"></i></button>
      <nav id="main-nav">
        <a href="staff_dashboard.php">🏠 Dashboard</a>
        <a href="appointment.php">📅 Appointments</a>
        <a href="patient_record.php">📘 Patient Record</a>
        <a href="product.php">💊 Product & Services</a>
        <a href="profile.php">🔍 Profile</a>
      </nav>
    </header>
    
    <div class="container">
      <div class="header-row">
        <h2>Staff Account Management</h2>
      </div>
    
      <form id="filtersForm" method="get" class="filters">
        <select name="status" id="statusFilter">
            <option value="All" <?= $statusFilter==='All'?'selected':'' ?>>All Status</option>
            <option value="Active" <?= $statusFilter==='Active'?'selected':'' ?>>Active</option>
            <option value="Inactive" <?= $statusFilter==='Inactive'?'selected':'' ?>>Inactive</option>
        </select>

        <input type="text" name="search" id="searchInput" placeholder="Search staff name or email..." value="<?= htmlspecialchars($search) ?>">
          
        <div class="button-group">
            <button type="button" class="add-btn" onclick="openAddModal()">➕ Add New Staff</button>
        </div>
      </form>
    
      <div class="stats">
        <div class="stat-card"><h3><?= $activeCount ?></h3><p>Active Staff</p></div>
        <div class="stat-card"><h3><?= $inactiveCount ?></h3><p>Inactive Staff</p></div>
        <div class="stat-card"><h3><?= $totalCount ?></h3><p>Total Staff</p></div>
      </div>
    
      <div class="table-container">
        <table id="staffTable" class="custom-table">
          <thead>
            <tr>
              <th style="width: 50px;">#</th>
              <th>Staff Member</th>
              <th>Email</th>
              <th>Password</th>
              <th>Status</th>
              <th style="text-align:center; width: 220px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($staffMembers): $i=0; foreach ($staffMembers as $staff): $i++;
              $nameParts = explode(' ', trim($staff['full_name'])); 
              $initials = count($nameParts) > 1
                ? strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1))
                : strtoupper(substr($staff['full_name'], 0, 1));
              if (strlen($initials) == 1 && strlen($staff['full_name']) > 1) { $initials .= strtoupper(substr($staff['full_name'], 1, 1)); }
              elseif (empty($initials)) { $initials = '??'; }
            ?>
              <tr>
                <td><?= $i ?></td>
                <td>
                  <div style="display:flex;align-items:center;gap:10px;">
                    <div class="staff-avatar"><?= htmlspecialchars($initials) ?></div>
                    <div>
                      <div style="font-weight:700;color:#223;"><?= htmlspecialchars($staff['full_name']) ?></div> 
                      <div style="font-size:12px;color:#6b7f86;">ID: <?= htmlspecialchars($staff['staff_id']) ?></div>
                    </div>
                  </div>
                </td>
                <td><?= htmlspecialchars($staff['email']) ?></td>
                <td>
                  <div class="password-display">
                    <input type="password" value="<?= htmlspecialchars($staff['password']) ?>" readonly>
                    <button type="button" onclick="togglePasswordInTable(this)" title="Show/Hide Password"><i class="fa-solid fa-eye"></i></button>
                  </div>
                </td>
                <td>
                  <span class="badge <?= strtolower($staff['status']) ?>">
                    <?= htmlspecialchars($staff['status']) ?>
                  </span>
                </td>
                <td style="text-align: center;">
                    <button class="action-btn view" onclick='viewDetails(<?= json_encode($staff["staff_id"]) ?>)'>View</button>
                    
                    <button class="action-btn edit" onclick='openEditModal(
                        <?= json_encode($staff["staff_id"]) ?>,
                        <?= json_encode($staff["full_name"]) ?>,
                        <?= json_encode($staff["email"]) ?>,
                        <?= json_encode($staff["password"]) ?>, 
                        <?= json_encode($staff["status"]) ?>
                    )'>Edit</button>
                    
                    <button class="action-btn remove" onclick='openRemoveModal(
                        <?= json_encode($staff["staff_id"]) ?>,
                        <?= json_encode($staff["full_name"]) ?>
                    )'>Remove</button>
                </td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="6" style="padding:40px;color:#677a82;text-align:center;">No staff members found matching your filters.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    
    <div id="detailOverlay" class="detail-overlay" aria-hidden="true">
      <div class="detail-card" role="dialog" aria-labelledby="detailTitle">
        <div class="detail-header">
          <div class="detail-title" id="detailTitle">Staff Details</div>
          <div class="detail-id" id="detailId">#</div>
        </div>
        <div class="detail-content">
          <div class="detail-section">
            <div class="detail-row"><span class="detail-label">Full Name</span><div class="detail-value" id="detailName"></div></div>
            <div class="detail-row"><span class="detail-label">Email Address</span><div class="detail-value" id="detailEmail"></div></div>
          </div>
          <div class="detail-section">
            <div class="detail-row"><span class="detail-label">Status</span><div id="detailStatusWrap"></div></div>
            <div class="detail-row">
                <span class="detail-label">Password</span>
                <div class="password-display" style="margin-top: 4px;">
                    <input type="password" id="detailPassword" readonly style="font-weight: 600; color: #1a202c;">
                    <button type="button" onclick="togglePasswordInTable(this)" title="Show/Hide Password"><i class="fa-solid fa-eye"></i></button>
                </div>
            </div>
          </div>
        </div>
        <div class="detail-actions">
          <button id="detailClose" class="btn-small btn-close" onclick="closeDetailModal()">Close</button>
        </div>
      </div>
    </div>
    
    <div id="formOverlay" class="form-overlay" aria-hidden="true">
      <div class="form-card" role="dialog">
        <div class="detail-header">
          <div class="detail-title" id="formTitle">Add Staff</div>
        </div>
        <div class="form-body">
          <form id="staffForm" onsubmit="return false;">
            <input type="hidden" id="formStaffId">
            <div class="form-group">
                <label for="formStaffName">Full Name *</label>
                <input type="text" id="formStaffName" required oninput="debounceBackendValidate('full_name', this.value, 'nameError', this)">
                <div class="error-msg" id="nameError"></div>
            </div>
            <div class="form-group">
                <label for="formEmail">Email Address *</label>
                <input type="email" id="formEmail" required oninput="debounceBackendValidate('email', this.value, 'emailError', this)">
                <div class="error-msg" id="emailError"></div>
            </div>
            <div class="form-group">
                <label for="formPassword">Password *</label>
                <div class="form-password-wrapper">
                    <input type="password" id="formPassword" oninput="debounceBackendValidate('password', this.value, 'passwordError', this)" placeholder="Min 8 chars, 1 Upper, 1 Num, 1 Spec Char"> 
                    <button type="button" onclick="togglePasswordVisibility(this)" title="Show/Hide Password"><i class="fa-solid fa-eye"></i></button>
                </div>
                <div class="error-msg" id="passwordError"></div>
            </div>
            <div class="form-group"><label for="formStatus">Status *</label><select id="formStatus"><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
          </form>
        </div>
        <div class="form-actions">
          <button class="btn-small btn-close" onclick="closeFormModal()">Cancel</button>
          <button class="btn-small btn-save" id="saveStaffBtn" onclick="initiateSave()">Save Staff</button>
        </div>
      </div>
    </div>
    
    <div id="removeOverlay" class="remove-overlay" aria-hidden="true">
      <div class="form-card" role="dialog" style="width: 440px; padding: 0;">
        <div class="detail-header" style="background:linear-gradient(135deg, #dc3545 0%, #a01c1c 100%);">
          <div class="detail-title" style="font-size: 20px;">⚠️ Confirm Removal</div>
        </div>
        <div class="remove-body">
          Are you sure you want to remove this staff member?
          <br>
          <strong id="removeStaffName" style="font-size: 18px;">Staff Name</strong>
          <br><br>
          <span style="font-weight: 700; color: #555;">This action cannot be undone.</span>
          <input type="hidden" id="removeStaffId">
        </div>
        <div class="form-actions">
          <button class="btn-small btn-close" onclick="closeRemoveModal()">Cancel</button>
          <button class="btn-small btn-danger" onclick="confirmRemove()">Yes, Remove Staff</button>
        </div>
      </div>
    </div>
    
    <script>
// --- PAGE OPEN LOADER (Fixed: No hanging) ---
    setTimeout(function() {
        const pageLoader = document.getElementById('pageLoader');
        if (pageLoader) {
            pageLoader.style.opacity = '0'; 
            setTimeout(() => {
                pageLoader.style.display = 'none'; 
            }, 400); 
        }
    }, 1000); // 1 second forced hide

    // --- UTILITIES ---
    const actionLoader = document.getElementById('actionLoader');
    const actionLoaderText = document.getElementById('actionLoaderText');

    function showActionLoader(message = 'Processing...') {
        if (actionLoaderText) actionLoaderText.textContent = message;
        if (actionLoader) {
            actionLoader.classList.add('show');
            actionLoader.setAttribute('aria-hidden', 'false');
        }
    }

    function hideActionLoader() {
        if (actionLoader) {
            actionLoader.classList.remove('show');
            actionLoader.setAttribute('aria-hidden', 'true');
        }
    }

    // --- NEW SUCCESS MODAL FUNCTION ---
    function showSuccessModal(msg) {
        const modal = document.getElementById('successModal');
        const text = document.getElementById('successMessageText');
        if(modal && text) {
            text.textContent = msg;
            modal.classList.add('show');
            setTimeout(() => {
                modal.classList.remove('show');
            }, 2500);
        }
    }

    function showToast(msg, type = 'error') {
        if (type === 'success') {
            showSuccessModal(msg);
            return;
        }

        const overlay = document.createElement('div');
        overlay.className = 'toast-overlay';
        const toast = document.createElement('div');
        toast.className = `toast ${type}`; 
        toast.innerHTML = `<div class="toast-icon">✕</div><div class="toast-message">${msg}</div>`;
        overlay.appendChild(toast);
        document.body.appendChild(overlay);
        
        const removeToast = () => {
            toast.style.opacity = '0';
            toast.addEventListener('transitionend', () => overlay.remove(), { once: true });
        };
        
        const timer = setTimeout(removeToast, 3000);
        toast.addEventListener('click', () => {
            clearTimeout(timer); 
            removeToast();
        });
    }

    // --- REAL-TIME VALIDATION ENGINE ---
    let debounceTimer;
    let originalEditEmail = ''; // Track email for OTP logic

    function debounceBackendValidate(fieldName, value, errorElementId, inputElement) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => validateBackendField(fieldName, value, errorElementId, inputElement), 500);
    }

    async function validateBackendField(fieldName, value, errorElementId, inputElement) {
        const staffId = document.getElementById('formStaffId').value;
        const fd = new URLSearchParams();
        fd.append('action', 'validate_field');
        fd.append('field', fieldName);
        fd.append('value', value);
        if (staffId) fd.append('staff_id', staffId);

        try {
            const res = await fetch('account.php', { 
                method: 'POST', 
                body: fd,
                headers: {'Content-Type': 'application/x-www-form-urlencoded'}
            });
            const json = await res.json();
            
            const errorEl = document.getElementById(errorElementId);
            if (errorEl) {
                if (!json.valid) {
                    errorEl.innerHTML = `❌ ${json.message}`;
                    errorEl.style.color = '#dc2626';
                    errorEl.style.display = 'block';
                    inputElement.classList.add('input-error');
                    inputElement.classList.remove('input-success');
                } else {
                    errorEl.innerHTML = `✅ Looks good`;
                    errorEl.style.color = '#16a34a';
                    errorEl.style.display = 'block';
                    inputElement.classList.remove('input-error');
                    inputElement.classList.add('input-success');
                }
            }
            return json.valid;
        } catch(e) {
            console.error("Validation Error:", e);
            return false;
        }
    }
    
    // --- PASSWORD TOGGLES ---
    function togglePasswordInTable(btn) {
      const wrapper = btn.closest('.password-display');
      if (!wrapper) return;
      const input = wrapper.querySelector('input');
      const icon = btn.querySelector('i');
      if (!input || !icon) return;
      
      if (input.type === 'password') {
          input.type = 'text';
          icon.classList.remove('fa-eye');
          icon.classList.add('fa-eye-slash');
      } else {
          input.type = 'password';
          icon.classList.remove('fa-eye-slash');
          icon.classList.add('fa-eye');
      }
    }
    
    function togglePasswordVisibility(btn) {
      const wrapper = btn.closest('.form-password-wrapper');
      if (!wrapper) return;
      const input = wrapper.querySelector('input');
      const icon = btn.querySelector('i');
      if (!input || !icon) return;
      
      if (input.type === 'password') {
          input.type = 'text';
          icon.classList.remove('fa-eye');
          icon.classList.add('fa-eye-slash');
      } else {
          input.type = 'password';
          icon.classList.remove('fa-eye-slash');
          icon.classList.add('fa-eye');
      }
    }
    
    // --- VIEW DETAILS ---
    function viewDetails(id) {
      showActionLoader('Fetching details...');
      fetch('account.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action:'viewDetails', id:id})
      })
      .then(res => res.json())
      .then(payload => {
        hideActionLoader();
        if (!payload || !payload.success) {
            showToast(payload?.message || 'Failed to load details', 'error'); return;
        }
        const d = payload.data;
        document.getElementById('detailId').textContent = d.staff_id;
        document.getElementById('detailName').textContent = d.full_name;
        document.getElementById('detailEmail').textContent = d.email;
        
        const dpInput = document.getElementById('detailPassword');
        dpInput.value = d.password;
        dpInput.type = 'password';
        const dpIcon = dpInput.nextElementSibling.querySelector('i');
        if(dpIcon) {
            dpIcon.classList.remove('fa-eye-slash');
            dpIcon.classList.add('fa-eye');
        }

        const statusWrap = document.getElementById('detailStatusWrap');
        if (statusWrap) {
            const stat = (d.status || '').toLowerCase();
            statusWrap.innerHTML = `<span class="badge ${stat}">${d.status}</span>`;
        }
        const overlay = document.getElementById('detailOverlay');
        overlay.classList.add('show');
        overlay.setAttribute('aria-hidden','false');
      })
      .catch(err => { 
          hideActionLoader();
          console.error(err); 
          showToast('Network error while fetching details', 'error'); 
      });
    }
    
    function closeDetailModal() {
      const overlay = document.getElementById('detailOverlay');
      overlay.classList.remove('show');
      overlay.setAttribute('aria-hidden','true');
    }

    function resetValidationUI() {
        document.querySelectorAll('.error-msg').forEach(el => { el.style.display = 'none'; el.innerHTML = ''; });
        document.querySelectorAll('input').forEach(el => { el.classList.remove('input-error'); el.classList.remove('input-success'); });
    }
    
    // --- ADD / EDIT FORM ---
    function openAddModal() {
      document.getElementById('formTitle').textContent = 'Add New Staff';
      document.getElementById('staffForm').reset();
      document.getElementById('formStaffId').value = '';
      originalEditEmail = '';
      resetValidationUI();
      
      const passInput = document.getElementById('formPassword');
      passInput.value = '';
      passInput.type = 'password';
      passInput.required = true; 
      
      const passBtnIcon = passInput.closest('.form-password-wrapper')?.querySelector('button i');
      if (passBtnIcon) passBtnIcon.className = 'fa-solid fa-eye';

      const overlay = document.getElementById('formOverlay');
      overlay.classList.add('show');
      overlay.setAttribute('aria-hidden','false');
    }
    
    function openEditModal(id, full_name, email, decryptedPassword, status) {
      document.getElementById('formTitle').textContent = 'Edit Staff';
      document.getElementById('staffForm').reset();
      resetValidationUI();
      
      document.getElementById('formStaffId').value = id;
      document.getElementById('formStaffName').value = full_name; 
      document.getElementById('formEmail').value = email;
      document.getElementById('formStatus').value = status;
      originalEditEmail = email;
      
      const passInput = document.getElementById('formPassword');
      passInput.value = decryptedPassword; 
      passInput.type = 'password'; 
      passInput.required = false; 
      
      const passBtnIcon = passInput.closest('.form-password-wrapper')?.querySelector('button i');
      if (passBtnIcon) passBtnIcon.className = 'fa-solid fa-eye';

      const overlay = document.getElementById('formOverlay');
      overlay.classList.add('show');
      overlay.setAttribute('aria-hidden','false');
    }
    
    function closeFormModal() {
      document.getElementById('staffForm').reset();
      resetValidationUI();
      const overlay = document.getElementById('formOverlay');
      overlay.classList.remove('show');
      overlay.setAttribute('aria-hidden','true');
    }
    
    // ---------------------------------------------------------
    // STEP 1: INITIATE SAVE (Check Validations & Check Email Change)
    // ---------------------------------------------------------
    async function initiateSave() {
      const saveBtn = document.getElementById('saveStaffBtn');
      if (saveBtn.disabled) return;
      saveBtn.disabled = true;

      const id = document.getElementById('formStaffId').value;
      const nameInput = document.getElementById('formStaffName');
      const emailInput = document.getElementById('formEmail');
      const passwordInput = document.getElementById('formPassword');
      
      const name = nameInput.value.trim();
      const email = emailInput.value.trim();
      const password = passwordInput.value;
      const action = id ? 'editStaff' : 'addStaff';
      
      const isNameValid = await validateBackendField('full_name', name, 'nameError', nameInput);
      const isEmailValid = await validateBackendField('email', email, 'emailError', emailInput);
      const isPassValid = await validateBackendField('password', password, 'passwordError', passwordInput);

      if(!isNameValid || !isEmailValid || !isPassValid) {
          showToast('Please fix the highlighted validation errors.', 'error');
          saveBtn.disabled = false;
          return;
      }
      
      // If adding new staff, OR editing and email changed, SEND OTP
      if (action === 'addStaff' || (action === 'editStaff' && email !== originalEditEmail)) {
          sendOtpRequest(email, true);
      } else {
          // If editing and email didn't change, just save
          submitFinalStaff('');
      }
    }

    // ---------------------------------------------------------
    // STEP 2: OTP LOGIC
    // ---------------------------------------------------------
    let resendInterval = null;

    function sendOtpRequest(email, isInitial = false) {
        showActionLoader(isInitial ? 'Sending verification code to new email...' : 'Resending code...');
        
        fetch('account.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: new URLSearchParams({ action: 'sendOtp', email: email, staff_id: document.getElementById('formStaffId').value })
        })
        .then(r => r.json())
        .then(res => {
            hideActionLoader();
            if (res.success) {
                document.getElementById('otpModalOverlay').classList.add('show');
                document.getElementById('otp1').focus();
                startResendTimer();
                if(!isInitial) showToast("A new code has been sent.");
            } else {
                showToast(res.message, 'error');
                document.getElementById('saveStaffBtn').disabled = false;
            }
        }).catch(e => { 
            hideActionLoader(); 
            showToast('Network Error.', 'error'); 
            document.getElementById('saveStaffBtn').disabled = false;
        });
    }

    function resendOtp() {
        const btn = document.getElementById('resendBtn');
        if (btn.classList.contains('disabled')) return;
        const email = document.getElementById('formEmail').value.trim();
        for(let i=1; i<=6; i++) document.getElementById('otp'+i).value = '';
        sendOtpRequest(email, false);
    }

    function startResendTimer() {
        const btn = document.getElementById('resendBtn');
        const timerText = document.getElementById('timerText');
        const count = document.getElementById('timerCount');
        let timeLeft = 60;

        btn.classList.add('disabled');
        timerText.style.display = 'inline';
        count.innerText = timeLeft;
        
        if (resendInterval) clearInterval(resendInterval);
        resendInterval = setInterval(() => {
            timeLeft--;
            count.innerText = timeLeft;
            if (timeLeft <= 0) {
                clearInterval(resendInterval);
                btn.classList.remove('disabled');
                timerText.style.display = 'none';
            }
        }, 1000);
    }

    // Auto-Next / Backspace / Paste for OTP Inputs
    const otpInputs = document.querySelectorAll('.otp-inputs input');
    otpInputs.forEach((input, index) => {
        input.addEventListener('input', (e) => {
            input.value = input.value.replace(/[^0-9]/g, '');
            if (input.value.length === 1 && index < otpInputs.length - 1) otpInputs[index + 1].focus();
        });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && input.value === '' && index > 0) otpInputs[index - 1].focus();
        });
        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const pastedData = e.clipboardData.getData('text').slice(0, 6).replace(/[^0-9]/g, '');
            if (pastedData) {
                for (let i = 0; i < pastedData.length; i++) {
                    if (otpInputs[i]) {
                        otpInputs[i].value = pastedData[i];
                        if (i < otpInputs.length - 1) otpInputs[i + 1].focus();
                        else otpInputs[i].focus();
                    }
                }
            }
        });
    });

    function closeOtpModal() {
        document.getElementById('otpModalOverlay').classList.remove('show');
        for(let i=1; i<=6; i++) document.getElementById('otp'+i).value = '';
        document.getElementById('saveStaffBtn').disabled = false;
    }

    function verifyOtpAndSave() {
        let otp = '';
        for(let i=1; i<=6; i++) otp += document.getElementById('otp'+i).value;
        if (otp.length !== 6) {
            showToast('Please enter the complete 6-digit code.', 'error'); return;
        }
        
        document.getElementById('otpModalOverlay').classList.remove('show');
        submitFinalStaff(otp);
    }

    // ---------------------------------------------------------
    // STEP 3: FINAL SUBMIT TO DATABASE
    // ---------------------------------------------------------
    function submitFinalStaff(otpCode) {
      showActionLoader('Saving account...');

      const formData = new URLSearchParams();
      formData.append('action', document.getElementById('formStaffId').value ? 'editStaff' : 'addStaff');
      formData.append('full_name', document.getElementById('formStaffName').value.trim());
      formData.append('email', document.getElementById('formEmail').value.trim());
      formData.append('status', document.getElementById('formStatus').value);
      formData.append('otp', otpCode);

      const password = document.getElementById('formPassword').value;
      if (password) { formData.append('password', password); }
      
      const id = document.getElementById('formStaffId').value;
      if (id) { formData.append('staff_id', id); }
      
      fetch('account.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: formData
      })
      .then(res => res.json())
      .then(payload => {
        hideActionLoader();
        document.getElementById('saveStaffBtn').disabled = false;

        if (payload.success) {
          showSuccessModal(payload.message); 
          closeFormModal();
          setTimeout(() => window.location.reload(), 1500);
        } else {
          showToast(payload.message || 'An error occurred.', 'error');
          if (payload.message.includes('Verification Code') || payload.message.includes('OTP')) {
              document.getElementById('otpModalOverlay').classList.add('show');
              for(let i=1; i<=6; i++) document.getElementById('otp'+i).value = '';
              document.getElementById('otp1').focus();
          }
        }
      })
      .catch(err => { 
          hideActionLoader();
          console.error(err); 
          showToast('Network error.', 'error'); 
          document.getElementById('saveStaffBtn').disabled = false;
      });
    }
    
    // --- REMOVE ---
    function openRemoveModal(id, name) {
      document.getElementById('removeStaffId').value = id;
      document.getElementById('removeStaffName').textContent = name;
      const overlay = document.getElementById('removeOverlay');
      overlay.classList.add('show');
      overlay.setAttribute('aria-hidden','false');
    }
    
    function closeRemoveModal() {
      const overlay = document.getElementById('removeOverlay');
      overlay.classList.remove('show');
      overlay.setAttribute('aria-hidden','true');
    }
    
    function confirmRemove() {
      const id = document.getElementById('removeStaffId').value;
      if (!id) { showToast('Could not find staff ID', 'error'); return; }
      
      showActionLoader('Removing staff...');
      
      fetch('account.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({action:'removeStaff', id:id})
      })
      .then(res => res.json())
      .then(payload => {
        hideActionLoader();
        if (payload.success) {
          showSuccessModal(payload.message);
          closeRemoveModal();
          setTimeout(() => window.location.reload(), 1500);
        } else {
          showToast(payload.message || 'Failed to remove staff.', 'error');
        }
      })
      .catch(err => { 
          hideActionLoader();
          console.error(err); 
          showToast('Network error.', 'error'); 
      });
    }
    
    // --- EVENT LISTENERS ---
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') {
            closeOtpModal();
            closeDetailModal();
            closeFormModal();
            closeRemoveModal();
        }
    });
    
    // Filter auto-submit
    (function(){
      const form = document.getElementById('filtersForm');
      const status = document.getElementById('statusFilter');
      const search = document.getElementById('searchInput');
      status?.addEventListener('change', ()=> form.submit());
      let timer = null;
      search?.addEventListener('input', function(){
        clearTimeout(timer);
        timer = setTimeout(()=> form.submit(), 600);
      });
    })();
    
    // Mobile Menu Toggle
    document.addEventListener('DOMContentLoaded', function() {
      const menuToggle = document.getElementById('menu-toggle');
      const mainNav = document.getElementById('main-nav');
      if (menuToggle && mainNav) {
        menuToggle.addEventListener('click', function() {
          mainNav.classList.toggle('show');
          if (mainNav.classList.contains('show')) {
            this.innerHTML = '<i class="fa-solid fa-xmark"></i>'; this.setAttribute('aria-label', 'Close navigation');
          } else {
            this.innerHTML = '<i class="fa-solid fa-bars"></i>'; this.setAttribute('aria-label', 'Open navigation');
          }
        });
        mainNav.querySelectorAll('a').forEach(link => {
          link.addEventListener('click', function() {
            mainNav.classList.remove('show');
            menuToggle.innerHTML = '<i class="fa-solid fa-bars"></i>';
            menuToggle.setAttribute('aria-label', 'Open navigation');
          });
        });
      }
    });

    // Prevent Form Resubmission on Reload
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
    </script>
    
</div>
</body>
</html>