<?php
session_start();
require '../config/db_mysqli.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: ../public/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch basic user data
$query = "SELECT full_name, email FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Eye Master</title>
    <link rel="stylesheet" href="../assets/ojo-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Specific Styles for Settings Page */
        .settings-section {
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 1px solid #eee;
        }
        
        .settings-section:last-child {
            border-bottom: none;
        }

        .settings-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }

        .settings-desc {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 20px;
            max-width: 600px;
        }

        /* Danger Zone */
        .danger-zone {
            border: 1px solid #ffebee;
            background-color: #fffafa;
            padding: 25px;
            border-radius: 4px;
        }
        
        .danger-title {
            color: #d32f2f;
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem;
            margin-bottom: 10px;
        }

        .btn-delete {
            background-color: white;
            color: #d32f2f;
            border: 1px solid #d32f2f;
            padding: 12px 25px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            text-transform: uppercase;
            transition: all 0.3s;
        }

        .btn-delete:hover {
            background-color: #d32f2f;
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php' ?>

    <div class="ojo-container">
        
        <div class="account-header">
            <h1>MY ACCOUNT</h1>
        </div>

        <div class="account-grid">
            <nav class="account-menu">
                <ul>
                    <li><a href="profile.php">Account Details</a></li>
                    <li><a href="appointments.php">Appointments</a></li>
                    <li><a href="settings.php" class="active">Settings</a></li>
                    <li><a href="../actions/logout.php" style="color: #e74c3c;">Log out</a></li>
                </ul>
            </nav>

            <main class="account-content">
                
                <h3>Account Settings</h3>

                <div class="settings-section">
                    <div class="settings-title">Profile Management</div>
                    <p class="settings-desc">Update your personal information, contact details, and medical history preferences.</p>
                    <a href="profile.php" class="btn-ojo" style="padding: 10px 20px; font-size: 0.8rem;">Edit Profile</a>
                </div>

                <div class="settings-section">
                    <div class="danger-zone">
                        <div class="danger-title">Delete Account</div>
                        <p class="settings-desc">
                            Permanently delete your account and all associated data. This action is irreversible and will cancel all pending appointments.
                        </p>
                        <button class="btn-delete" onclick="openModal('warningModal')">Delete My Account</button>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <div id="warningModal" class="ojo-modal-overlay">
        <div class="ojo-modal" style="max-width: 450px; text-align: center;">
            <i class="fa-solid fa-triangle-exclamation" style="font-size: 3rem; color: #d32f2f; margin-bottom: 20px;"></i>
            <h2 style="border: none; margin-bottom: 10px;">Are you sure?</h2>
            <p style="color: #666; margin-bottom: 30px; line-height: 1.6;">
                You are about to permanently delete your account. 
                <br><strong>This cannot be undone.</strong>
            </p>
            
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button onclick="closeModal('warningModal')" class="btn-ojo" style="background: #eee; color: #333;">Cancel</button>
                <button onclick="openPasswordModal()" class="btn-ojo" style="background: #d32f2f;">Continue</button>
            </div>
        </div>
    </div>

    <div id="passwordModal" class="ojo-modal-overlay">
        <div class="ojo-modal" style="max-width: 450px;">
            <button class="close-modal" onclick="closeModal('passwordModal')">&times;</button>
            <h2>Verify Identity</h2>
            <p style="margin-bottom: 20px; color: #666;">Please enter your password to confirm deletion.</p>
            
            <div class="ojo-group">
                <label>Current Password</label>
                <input type="password" id="currentPasswordInput" placeholder="••••••••" required>
            </div>
            <p id="deletionError" style="color: #d32f2f; font-size: 0.85rem; margin-top: 10px; display: none;"></p>

            <button id="confirmDeleteBtn" class="btn-ojo" style="background: #d32f2f; width: 100%; margin-top: 20px;">
                Permanently Delete
            </button>
        </div>
    </div>

    <?php include '../includes/footer.php' ?>

    <script>
        function openModal(id) {
            document.getElementById(id).style.display = 'flex';
            if(id === 'warningModal') {
                document.getElementById('passwordModal').style.display = 'none';
            }
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        function openPasswordModal() {
            closeModal('warningModal');
            document.getElementById('passwordModal').style.display = 'flex';
            document.getElementById('currentPasswordInput').focus();
        }

        // AJAX DELETION LOGIC
        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            const password = document.getElementById('currentPasswordInput').value;
            const errorDiv = document.getElementById('deletionError');
            const btn = this;
            
            if (!password) {
                errorDiv.textContent = 'Please enter your password.';
                errorDiv.style.display = 'block';
                return;
            }
            
            errorDiv.style.display = 'none';
            btn.disabled = true;
            btn.textContent = 'Processing...';

            fetch('../actions/delete-account-action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `current_password=${encodeURIComponent(password)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Account deleted successfully.');
                    window.location.href = '../public/login.php';
                } else {
                    btn.disabled = false;
                    btn.textContent = 'Permanently Delete';
                    errorDiv.textContent = data.message || 'Error deleting account.';
                    errorDiv.style.display = 'block';
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.textContent = 'Permanently Delete';
                errorDiv.textContent = 'Network error.';
                errorDiv.style.display = 'block';
            });
        });

        // Close modal on outside click
        window.onclick = function(e) {
            if (e.target.classList.contains('ojo-modal-overlay')) {
                e.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>