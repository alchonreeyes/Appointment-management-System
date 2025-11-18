<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Page Title</title>
    <link rel="stylesheet" href="./style/profile.css">
    
    <?php 
    // Include theme handler
    if (!isset($current_theme)) {
        include '../includes/theme_handler.php';
    }
    
    // Load dark mode CSS if needed
    if ($current_theme === 'dark'): 
    ?>
        <link rel="stylesheet" href="./style/dark-mode.css">
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.body.classList.add('dark-mode');
            });
        </script>
    <?php endif; ?>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body<?php echo $current_theme === 'dark' ? ' class="dark-mode"' : ''; ?>>
        <!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
        <p>Client Dashboard</p>
    </div>
    
    <nav class="sidebar-nav sidebar-wrapper">
        <a href="../public/home.php">
            <i class="fa-solid fa-house"></i>
            <span>Home</span>
        </a>
        <a href="profile.php" class="active">
            <i class="fa-solid fa-user"></i>
            <span>My Profile</span>
        </a>
        <a href="appointments.php">
            <i class="fa-solid fa-calendar-check"></i>
            <span>My Appointments</span>
        </a>
        
        <a href="settings.php">
            <i class="fa-solid fa-gear"></i>
            <span>Settings</span>
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <a href="../actions/logout.php" onclick="return confirm('Are you sure you want to logout?')">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

    

<script>
    // Sidebar Toggle Functionality
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const toggleBtn = document.querySelector('.side-toggle');

    // Toggle sidebar on button click
    toggleBtn.addEventListener('click', function(e) {
        e.preventDefault();
        sidebar.classList.toggle('active');
        sidebarOverlay.classList.toggle('active');
        this.classList.toggle('active');
    });

    // Close sidebar when clicking overlay
    sidebarOverlay.addEventListener('click', function() {
        sidebar.classList.remove('active');
        sidebarOverlay.classList.remove('active');
        toggleBtn.classList.remove('active');
    });

    // Close sidebar on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            toggleBtn.classList.remove('active');
        }
    });

    // Prevent body scroll when sidebar is open
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (sidebar.classList.contains('active')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = 'auto';
            }
        });
    });

    observer.observe(sidebar, {
        attributes: true,
        attributeFilter: ['class']
    });
</script>
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling;
            
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

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>

    </body>
</html>