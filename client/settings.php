<?php
session_start();
require '../config/db_mysqli.php'; // Assuming this provides $conn (mysqli object)

if (!isset($_SESSION['user_id'])) {
    header("Location: ../public/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$query = "SELECT u.*, c.client_id FROM users u 
          LEFT JOIN clients c ON u.id = c.user_id 
          WHERE u.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: ../public/login.php");
    exit();
}

// Get user initials
$name_parts = explode(' ', $user['full_name']);
$initials = count($name_parts) >= 2 
    ? strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1))
    : strtoupper(substr($user['full_name'], 0, 2));

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Eye Master Clinic</title>
    <link rel="stylesheet" href="./style/profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Existing Settings Styles (Adjusted for clean aesthetics) */
        .settings-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
            flex-wrap: wrap;
        }

        .settings-nav-item {
            padding: 10px 20px;
            /* ... rest of nav styles ... */
        }

        .settings-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
        }

        .settings-card h3 {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* NEW MODAL STYLES (Simple for now) */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            text-align: left;
        }
        .modal-title {
            color: #dc3545; /* Red for danger */
            margin-bottom: 15px;
            font-size: 20px;
            font-weight: 700;
        }
        .modal-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            margin-top: 10px;
        }
        .btn-danger { background-color: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; margin-top: 15px; }
        .btn-secondary { background-color: #e9ecef; color: #333; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; margin-top: 15px; margin-right: 10px; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php' ?>
    
    <div class="link-section">
        <a href="../public/home.php"><i class="fa-solid fa-house"></i></a>
        <a href="#" class="side-toggle"><i class="fa-solid fa-bars"></i></a>
    </div>

    <?php include 'sidebar.php'; ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="profile">
        <div class="profile-details">
            <div class="profile-section">
                <h2 class="section-title">
                    <i class="fa-solid fa-gear"></i>
                    Settings
                </h2>

                <div class="settings-section" id="account-section" style="display: block;">
                    
                    <div class="settings-card">
                        <h3>
                            <i class="fa-solid fa-user-gear"></i>
                            General Account
                        </h3>
                        <p>Update your name, email, and password here.</p>
                        <div class="placeholder-section">
                             <i class="fa-solid fa-pen-to-square"></i>
                            <h4>Edit Profile</h4>
                        </div>
                    </div>

                    <div class="settings-card" style="border-left: 5px solid #dc3545;">
                        <h3>
                            <i class="fa-solid fa-trash-can"></i>
                            Account Deactivation
                        </h3>
                        <p>This action is irreversible and will permanently delete all your records, including appointments and history.</p>
                        <button class="btn-danger" onclick="openDeletionWarningModal()">
                            Delete My Account
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php' ?>

    <div class="modal-overlay" id="warningModal">
        <div class="modal-content">
            <h3 class="modal-title"><i class="fa-solid fa-triangle-exclamation"></i> Warning: Permanent Deletion</h3>
            <p>You are about to delete your Eye Master Clinic account. This action is **irreversible**. All your appointments, history, and profile data will be permanently removed from our system.</p>
            <p>Are you sure you want to proceed?</p>
            <div style="text-align: right;">
                <button class="btn-secondary" onclick="closeModal('warningModal')">Cancel</button>
                <button class="btn-danger" onclick="openPasswordModal()">I Understand, Proceed</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="passwordModal">
        <div class="modal-content">
            <h3 class="modal-title"><i class="fa-solid fa-lock"></i> Authorize Deletion</h3>
            <p>For security, please enter your current password to confirm the permanent deletion of your account.</p>
            <input type="password" id="currentPasswordInput" class="modal-input" placeholder="Enter your current password" required>
            <div id="deletionError" style="color: #dc3545; margin-top: 5px; display: none;"></div>
            <div style="text-align: right;">
                <button class="btn-secondary" onclick="closeModal('passwordModal')">Back</button>
                <button class="btn-danger" id="confirmDeleteBtn">Delete Permanently</button>
            </div>
        </div>
    </div>

    <script>
        // --- MODAL FUNCTIONS ---
        function openModal(id) {
            document.getElementById(id).style.display = 'flex';
            if (id === 'passwordModal') {
                document.getElementById('currentPasswordInput').value = '';
                document.getElementById('currentPasswordInput').focus();
            }
        }
        
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        
        function openDeletionWarningModal() {
            openModal('warningModal');
        }

        function openPasswordModal() {
            closeModal('warningModal');
            openModal('passwordModal');
        }

        // --- AJAX DELETION LOGIC ---
        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            const password = document.getElementById('currentPasswordInput').value;
            const errorDiv = document.getElementById('deletionError');
            
            if (!password) {
                errorDiv.textContent = 'Please enter your password.';
                errorDiv.style.display = 'block';
                return;
            }
            
            errorDiv.style.display = 'none';
            
            // Disable button during submission
            this.disabled = true;
            this.textContent = 'Processing...';

            fetch('../actions/delete-account-action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `current_password=${encodeURIComponent(password)}`
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('confirmDeleteBtn').disabled = false;
                document.getElementById('confirmDeleteBtn').textContent = 'Delete Permanently';

                if (data.success) {
                    alert(data.message + ' You will now be logged out.');
                    window.location.href = '../public/login.php'; // Redirect to login page
                } else {
                    errorDiv.textContent = data.message || 'Deletion failed due to a server error.';
                    errorDiv.style.display = 'block';
                    document.getElementById('currentPasswordInput').value = ''; // Clear password field
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                document.getElementById('confirmDeleteBtn').disabled = false;
                document.getElementById('confirmDeleteBtn').textContent = 'Delete Permanently';
                errorDiv.textContent = 'Network error. Could not connect to server.';
                errorDiv.style.display = 'block';
            });
        });
        
        // --- EXISTING THEME/NAV LISTENERS ---
        // (Keep your existing JS for sidebar/nav/theme-option listeners here)
        document.querySelectorAll('.settings-nav-item').forEach(item => {
            // Re-activate click listener if needed, but it's commented out in HTML
        });

        // Auto-hide success alert
        setTimeout(function() {
            const alert = document.querySelector('.alert-success');
            if (alert) {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }
        }, 3000);
    </script>
</body>
</html>