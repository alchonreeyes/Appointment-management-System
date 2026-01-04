<?php
// includes/navbar.php

// TRICK: Only start a session if one isn't active yet.
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../config/db.php';
$db = new Database();
$pdo = $db->getConnection();

// Fetch unique brands from products table
try {
    $brandStmt = $pdo->query("SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand != '' ORDER BY brand ASC");
    $brands = $brandStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $brands = [];
}

// Fetch unique frame types from products table
try {
    $frameStmt = $pdo->query("SELECT DISTINCT frame_type FROM products WHERE frame_type IS NOT NULL ORDER BY frame_type ASC");
    $frameTypes = $frameStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $frameTypes = [];
}

// Fetch unique lens types from products table
try {
    $lensStmt = $pdo->query("SELECT DISTINCT lens_type FROM products WHERE lens_type IS NOT NULL AND lens_type != '' ORDER BY lens_type ASC");
    $lensTypes = $lensStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $lensTypes = [];
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
                    <img src="../mod/photo/LOGO.jpg" alt="Logo">
                </div>
                
                <!-- Dynamic Dropdown for desktop only -->
                <div class="dropdown">
                    <div class="dropdown-content">
                        <!-- BRANDS Column -->
                        <ul>
                            <h4>BRANDS</h4>
                            <?php if (!empty($brands)): ?>
                                <?php 
                                // Split brands into two columns (first half)
                                $halfCount = ceil(count($brands) / 2);
                                $firstHalf = array_slice($brands, 0, $halfCount);
                                foreach ($firstHalf as $brand): 
                                ?>
                                    <li><a href="../public/browse.php?brand=<?= urlencode($brand) ?>"><?= htmlspecialchars($brand) ?></a></li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li><a href="#">No brands available</a></li>
                            <?php endif; ?>
                        </ul>
                        
                        <!-- BRANDS Column 2 (Second Half) -->
                        <ul>
                            <li style="visibility: hidden;"><h4>&nbsp;</h4></li> <!-- Spacer to align with first column header -->
                            <?php if (!empty($brands) && count($brands) > $halfCount): ?>
                                <?php 
                                // Second half of brands
                                $secondHalf = array_slice($brands, $halfCount);
                                foreach ($secondHalf as $brand): 
                                ?>
                                    <li><a href="../public/browse.php?brand=<?= urlencode($brand) ?>"><?= htmlspecialchars($brand) ?></a></li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                        
                        <!-- EYEWEAR FRAMES Column -->
                        <ul>
                            <h4>EYEWEAR FRAMES</h4>
                            <?php if (!empty($frameTypes)): ?>
                                <?php foreach ($frameTypes as $frameType): ?>
                                    <li><a href="../public/browse.php?frame_type=<?= urlencode($frameType) ?>"><?= htmlspecialchars($frameType) ?></a></li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li><a href="#">No frames available</a></li>
                            <?php endif; ?>
                        </ul>
                        
                        <!-- LENSES Column -->
                        <ul>
                            <h4>LENSES</h4>
                            <?php if (!empty($lensTypes)): ?>
                                <?php foreach ($lensTypes as $lensType): ?>
                                    <li><a href="../public/browse.php?lens_type=<?= urlencode($lensType) ?>"><?= htmlspecialchars($lensType) ?></a></li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li><a href="#">No lenses available</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Desktop Navigation Links -->
            <div class="nav-links desktop-nav">
                <ul>
                    <li><a href="../public/home.php">Home</a></li>
                    <li><a href="../public/browse.php">Browse</a></li>
                    <li><a href="../public/store.php">Store</a></li>
                    <li><a href="../public/about.php">About</a></li>
                </ul>
                <form class="search-group" id="globalSearchForm" onsubmit="handleSearch(event)">
                    <input type="text" id="searchQuery" placeholder="Search eye glasses..." required style="padding-left: 20px;">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
            
            <div class="nav-config desktop-config"> 
                <?php 
                if (!isset($_SESSION['client_id'])): 
                ?>
                    <a href="../public/login.php"><i class="fa-regular fa-user"></i></a>
                    <a href="../public/login.php" class="signin">Sign In & Sign Up</a>
                    <a href="../public/book_appointment.php" class="book-btn">Book Appointment</a>
                     
                <?php else: ?>
                    <div class="user-menu" style="align-items: center; display: flex;">
                        <a href="../client/profile.php" style="text-decoration: none; color: inherit; display: flex; align-items: center;">
                            <i class="fa-regular fa-user" style="font-size: 1.6rem;"></i>
                            <span style="display:inline-block; margin-left:8px; font-size:14px; font-weight:600; line-height:1; vertical-align:middle; color: #004aad;">Profile</span>
                        </a>
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
                <li><a href="../public/about.php">About</a></li>
            </ul>
            
            <!-- Mobile Filters Section -->
            <div class="mobile-nav-section">
                <h4>Brands</h4>
                <ul class="mobile-filter-list">
                    <?php if (!empty($brands)): ?>
                        <?php foreach ($brands as $brand): ?>
                            <li><a href="../public/browse.php?brand=<?= urlencode($brand) ?>"><?= htmlspecialchars($brand) ?></a></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>No brands available</li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="mobile-nav-section">
                <h4>Frame Types</h4>
                <ul class="mobile-filter-list">
                    <?php if (!empty($frameTypes)): ?>
                        <?php foreach ($frameTypes as $frameType): ?>
                            <li><a href="../public/browse.php?frame_type=<?= urlencode($frameType) ?>"><?= htmlspecialchars($frameType) ?></a></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>No frame types available</li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="mobile-nav-section">
                <h4>Lens Types</h4>
                <ul class="mobile-filter-list">
                    <?php if (!empty($lensTypes)): ?>
                        <?php foreach ($lensTypes as $lensType): ?>
                            <li><a href="../public/browse.php?lens_type=<?= urlencode($lensType) ?>"><?= htmlspecialchars($lensType) ?></a></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>No lens types available</li>
                    <?php endif; ?>
                </ul>
            </div>
               
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
                    <a href="../client/appointments.php" class="mobile-nav-link">
                        <i class="fa-regular fa-calendar"></i> My Appointments
                    </a>
                    <a href="../client/settings.php" class="mobile-nav-link">
                        <i class="fa-solid fa-gear"></i> Settings
                    </a>
                    <a href="../actions/logout.php" class="mobile-nav-link">
                        <i class="fa-solid fa-right-from-bracket"></i> Logout
                    </a>
                <?php endif; ?>
                
                <a href="../public/book_appointment.php" class="mobile-nav-book-btn">
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

        function handleSearch(e) {
            e.preventDefault();
            const query = document.getElementById('searchQuery').value.trim();
            if (query) {
                window.location.href = `../public/browse.php?search=${encodeURIComponent(query)}`;
            }
        }
    </script>
</body>
</html>