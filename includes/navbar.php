<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../includes/navbar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Top bar -->
    <div class="top-bar">
        <span>Store Location | Brand</span>
        <a href="#">Contact Us</a>
    </div>

    <!-- Main nav -->
    <nav>
        <div class="nav-section">
            <!-- Hamburger Menu (Mobile Only) -->
            <button class="hamburger" id="hamburger">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Logo container with dropdown (Desktop Only) -->
            <div class="logo-container">
                <div class="logo">
                    <img src="" alt="Logo">
                </div>
                
                <!-- Dropdown for desktop only -->
                <div class="dropdown">
                    <div class="dropdown-content">
                        <ul>
                            <h4>BRANDS</h4>
                            <li><a href="#">Coach</a></li>
                            <li><a href="#">Armani Exchange</a></li>
                            <li><a href="#">ARNETTE</a></li>
                            <li><a href="#">Celine</a></li> 
                            <li><a href="#">Roman King</a></li> 
                            <li><a href="#">C. Lindbergh</a></li>
                            <br>
                        </ul>
                        <ul>
                            <li><a href="">Memoflex</a></li>
                            <li><a href="">Kate Spade New York</a></li>
                            <li><a href="">Herman Miller</a></li>
                            <li><a href="">Hushpuppies</a></li>
                            <li><a href="">Jiashie eyes</a></li>
                            <li><a href="#">Airflex</a></li>    
                        </ul>
                        <ul>
                            <h4>EYEWEAR FRAMES</h4>
                            <li><a href="#">Acetate</a></li>
                            <li><a href="#">B Titanium</a></li>
                            <li><a href="#">Metal</a></li>
                            <li><a href="#">Plastic</a></li>    
                            <li><a href="#">Plastic/Metal</a></li>    
                            <li><a href="#">Rubber</a></li>    
                            <br>
                        </ul>
                        <ul>
                            <li><a href="#">Rubber/Metal</a></li>
                            <li><a href="#">Rubber/Plastic</a></li>
                            <li><a href="#">Titanium</a></li>
                            <li><a href="#">Titanium/Acatate</a></li>
                            <li><a href="#">Titanium/Wood</a></li>
                            <li><a href="#">Wood</a></li>
                        </ul>
                        <ul>
                            <h4>LENSES</h4>
                            <li><a href="#">Apollo Lenses</a></li>
                            <li><a href="#">Essilor Lenses</a></li>
                            <li><a href="#">Hoya Lenses</a></li>
                            <br>
                            <br>
                            <br>
                            <br>
                            <br>
                        </ul>    
                    </div>
                </div>
            </div>

            <!-- Desktop Navigation Links -->
            <div class="nav-links desktop-nav">
                <ul>
                    <li><a href="../public/about.php">About</a></li>
                    <li><a href="#">Category</a></li>
                    <li><a href="../public/store.php">Store</a></li>
                    <li><a href="../public/home.php">Home</a></li>
                    <li><a href="../public/browse.php">Browse</a></li>
                </ul>
                <div class="search">
                    <input type="text" id="browse" placeholder="I'm looking for.....">
                </div>
            </div>

            <!-- Desktop User Config -->
            <div class="nav-config desktop-config">
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <i class="fa-regular fa-user"></i>
                    <a href="../public/login.php" class="signin">Sign In & Sign Up</a>
                    <a href="../public/appointment.php" class="book-btn">Book Appointment</a>
                <?php else: ?>
                    <div class="user-menu">
                        <button class="user-icon">
                            <a href="../client/profile.php"><i class="fa-regular fa-user"></i></a>
                        </button>
                        <div class="dropdown-menu">
                            <a href="../client/profile.php">Profile</a>
                            <a href="../public/my-appointments.php">My Appointments</a>
                            <a href="../public/settings.php">Settings</a>
                            <a href="../actions/logout.php">Logout</a>
                        </div>
                    </div>
                    <a href="../public/book_appointment.php" class="book-btn">Book Appointment</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Mobile Navigation Menu -->
    <div class="mobile-nav-menu" id="mobileNavMenu">
        <div class="mobile-nav-header">
            <button class="mobile-nav-close" id="closeMobileNav">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="mobile-nav-content">
            <!-- Mobile Navigation Links -->
            <ul class="mobile-nav-links">
                <li><a href="../public/home.php">Home</a></li>
                <li><a href="../public/browse.php">Browse</a></li>
                <li><a href="../public/store.php">Store</a></li>
                <li><a href="#">Category</a></li>
                <li><a href="../public/about.php">About</a></li>
            </ul>

            <!-- Mobile Search -->
            <div class="mobile-nav-search">
                <input type="text" placeholder="I'm looking for.....">
            </div>

            <!-- Mobile User Section -->
            <div class="mobile-nav-user">
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="../public/login.php" class="mobile-nav-link">
                        <i class="fa-regular fa-user"></i> Sign In & Sign Up
                    </a>
                <?php else: ?>
                    <a href="../client/profile.php" class="mobile-nav-link">
                        <i class="fa-regular fa-user"></i> Profile
                    </a>
                    <a href="../public/my-appointments.php" class="mobile-nav-link">
                        <i class="fa-regular fa-calendar"></i> My Appointments
                    </a>
                    <a href="../public/settings.php" class="mobile-nav-link">
                        <i class="fa-solid fa-gear"></i> Settings
                    </a>
                    <a href="../actions/logout.php" class="mobile-nav-link">
                        <i class="fa-solid fa-right-from-bracket"></i> Logout
                    </a>
                <?php endif; ?>
                
                <a href="../public/appointment.php" class="mobile-nav-book-btn">
                    Book Appointment
                </a>
            </div>
        </div>
    </div>

    <!-- Overlay for mobile menu -->
    <div class="mobile-nav-overlay" id="mobileNavOverlay"></div>

    <script>
        // Mobile navigation toggle functionality
        const hamburger = document.getElementById('hamburger');
        const mobileNavMenu = document.getElementById('mobileNavMenu');
        const closeMobileNav = document.getElementById('closeMobileNav');
        const mobileNavOverlay = document.getElementById('mobileNavOverlay');

        function openMobileNav() {
            mobileNavMenu.classList.add('active');
            mobileNavOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeMobileNavFunc() {
            mobileNavMenu.classList.remove('active');
            mobileNavOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        hamburger.addEventListener('click', openMobileNav);
        closeMobileNav.addEventListener('click', closeMobileNavFunc);
        mobileNavOverlay.addEventListener('click', closeMobileNavFunc);
    </script>
</body>
</html>