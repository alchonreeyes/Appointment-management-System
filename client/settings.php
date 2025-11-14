<?php
session_start();
require '../config/db_mysqli.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../public/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle theme change
if (isset($_POST['change_theme'])) {
    $theme = $_POST['theme'] === 'dark' ? 'dark' : 'light';
    
    // Save to database or session
    $_SESSION['theme'] = $theme;
    
    // Optional: Save to database
    $stmt = $conn->prepare("UPDATE users SET theme_preference = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("si", $theme, $user_id);
        $stmt->execute();
    }
    
    $_SESSION['success_message'] = "Theme updated successfully!";
    header("Location: settings.php");
    exit();
}

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

// Get current theme
$current_theme = $_SESSION['theme'] ?? $user['theme_preference'] ?? 'light';
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
        /* Settings Specific Styles */
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
            border: none;
            background: transparent;
            color: #6c757d;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .settings-nav-item:hover {
            color: #8B0000;
        }

        .settings-nav-item.active {
            color: #8B0000;
            border-bottom-color: #8B0000;
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

        .settings-card p {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .theme-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .theme-option {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            background: white;
        }

        .theme-option:hover {
            border-color: #8B0000;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(139, 0, 0, 0.1);
        }

        .theme-option.selected {
            border-color: #8B0000;
            background: linear-gradient(135deg, rgba(139, 0, 0, 0.05) 0%, rgba(165, 42, 42, 0.05) 100%);
        }

        .theme-option.selected::after {
            content: '✓';
            position: absolute;
            top: 10px;
            right: 10px;
            background: #8B0000;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .theme-preview {
            width: 100%;
            height: 80px;
            border-radius: 6px;
            margin-bottom: 12px;
            display: flex;
            overflow: hidden;
        }

        .theme-preview.light {
            background: linear-gradient(to right, #f8f9fa 60%, #e9ecef 60%);
        }

        .theme-preview.dark {
            background: linear-gradient(to right, #2c3e50 60%, #1a252f 60%);
        }

        .theme-option h4 {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .theme-option span {
            font-size: 12px;
            color: #6c757d;
        }

        .placeholder-section {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            color: #6c757d;
        }

        .placeholder-section i {
            font-size: 48px;
            color: #dee2e6;
            margin-bottom: 15px;
        }

        .placeholder-section h4 {
            font-size: 18px;
            color: #495057;
            margin-bottom: 8px;
        }

        .placeholder-section p {
            font-size: 14px;
            margin: 0;
        }

        @media (max-width: 768px) {
            .theme-options {
                grid-template-columns: 1fr;
            }
        }
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
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar">
                    <div class="avatar-circle"><?php echo $initials; ?></div>
                </div>
                <div class="profile-header-info">
                    <h1><?php echo htmlspecialchars($user['full_name']); ?></h1>
                    <p class="user-id">ID: <?php echo $user['id']; ?></p>
                    <span class="user-badge">CLIENT</span>
                </div>
            </div>

            <!-- Success Message -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success" style="margin: 20px;">
                    <i class="fa-solid fa-check-circle"></i>
                    <?php 
                        echo $_SESSION['success_message']; 
                        unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Main Content -->
            <div class="profile-section">
                <h2 class="section-title">
                    <i class="fa-solid fa-gear"></i>
                    Settings
                </h2>

                <!-- Settings Navigation -->
                <!-- <div class="settings-nav">
                    <a href="#" class="settings-nav-item active" data-section="appearance">
                        <i class="fa-solid fa-palette"></i>
                        Appearance
                    </a>
                    <a href="#" class="settings-nav-item" data-section="account">
                        <i class="fa-solid fa-user-gear"></i>
                        Account
                    </a>
                    <a href="#" class="settings-nav-item" data-section="notifications">
                        <i class="fa-solid fa-bell"></i>
                        Notifications
                    </a>
                    <a href="#" class="settings-nav-item" data-section="advanced">
                        <i class="fa-solid fa-sliders"></i>
                        Advanced
                    </a>
                </div> -->

                <!-- Appearance Section -->
                <div class="settings-section" id="appearance-section">
                    <div class="settings-card">
                        <h3>
                            <i class="fa-solid fa-palette"></i>
                            Theme
                        </h3>
                        <p>Choose your preferred theme for the Eye Master Clinic system</p>

                        <form method="POST" id="themeForm">
                            <div class="theme-options">
                                <label class="theme-option <?php echo $current_theme === 'light' ? 'selected' : ''; ?>">
                                    <input type="radio" name="theme" value="light" 
                                           <?php echo $current_theme === 'light' ? 'checked' : ''; ?>
                                           style="display: none;">
                                    <div class="theme-preview light"></div>
                                    <h4>Light Mode</h4>
                                    <span>Classic bright theme</span>
                                </label>

                                <label class="theme-option <?php echo $current_theme === 'dark' ? 'selected' : ''; ?>">
                                    <input type="radio" name="theme" value="dark" 
                                           <?php echo $current_theme === 'dark' ? 'checked' : ''; ?>
                                           style="display: none;">
                                    <div class="theme-preview dark"></div>
                                    <h4>Dark Mode</h4>
                                    <span>Easy on the eyes</span>
                                </label>
                            </div>

                            <div class="form-actions" style="margin-top: 20px;">
                                <button type="submit" name="change_theme" class="btn btn-primary">
                                    <i class="fa-solid fa-save"></i> Save Theme
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Account Section (Placeholder) -->
                <div class="settings-section" id="account-section" style="display: none;">
                    <div class="placeholder-section">
                        <i class="fa-solid fa-user-gear"></i>
                        <h4>Account Settings</h4>
                        <p>Account preferences will be available here</p>
                    </div>
                </div>

                <!-- Notifications Section (Placeholder) -->
                <div class="settings-section" id="notifications-section" style="display: none;">
                    <div class="placeholder-section">
                        <i class="fa-solid fa-bell"></i>
                        <h4>Notification Settings</h4>
                        <p>Notification preferences will be available here</p>
                    </div>
                </div>

                <!-- Advanced Section (Placeholder) -->
                <div class="settings-section" id="advanced-section" style="display: none;">
                    <div class="placeholder-section">
                        <i class="fa-solid fa-sliders"></i>
                        <h4>Advanced Settings</h4>
                        <p>Advanced options will be available here</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php' ?>

    <script>
        // Sidebar Toggle
        const sidebar = document.querySelector('.sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const toggleBtn = document.querySelector('.side-toggle');

        if (toggleBtn && sidebar && sidebarOverlay) {
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                this.classList.toggle('active');
            });

            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                if (toggleBtn) toggleBtn.classList.remove('active');
            });
        }

        // Theme Selection
        document.querySelectorAll('.theme-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.theme-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;
            });
        });

        // Settings Navigation
        document.querySelectorAll('.settings-nav-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remove active class from all nav items
                document.querySelectorAll('.settings-nav-item').forEach(nav => {
                    nav.classList.remove('active');
                });
                
                // Add active class to clicked item
                this.classList.add('active');
                
                // Hide all sections
                document.querySelectorAll('.settings-section').forEach(section => {
                    section.style.display = 'none';
                });
                
                // Show selected section
                const sectionId = this.getAttribute('data-section') + '-section';
                const targetSection = document.getElementById(sectionId);
                if (targetSection) {
                    targetSection.style.display = 'block';
                }
            });
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