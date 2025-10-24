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
            <!-- Logo container with dropdown - CHANGED -->
            <div class="logo-container">
                <div class="logo">
                    <img src="" alt="Logo">
                </div>
                
                <!-- Dropdown moved inside logo-container -->
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

            <div class="nav-links">
                <ul>
                    <li><a href="#">About</a></li>
                    <li><a href="#">Category</a></li>
                    <li><a href="../public/home.php">Home</a></li>
                    <li><a href="#">Brands</a></li>
                </ul>
                <div class="search">
                    <input type="text" id="browse" placeholder="I'm looking for.....">
                </div>
            </div>

            <div class="nav-config">
    <?php if (!isset($_SESSION['user_id'])): ?>
        <!-- 👤 Not logged in -->
        <i class="fa-regular fa-user"></i>
        <a href="../public/login.php" class="signin">Sign In & Sign Up</a>
        <a href="../public/appointment.php" class="book-btn">Book Appointment</a>
    <?php else: ?>
        <a href="../public/book_appointment.php" class="book-btn">Book Appointment</a>
        <!-- ✅ Logged in -->
        <div class="user-menu">
            <button class="user-icon">
                <i class="fa-solid fa-user-circle"></i>
            </button>
            <div class="dropdown-menu">
                <a href="../public/profile.php">Profile</a>
                <a href="../public/my-appointments.php">My Appointments</a>
                <a href="../public/settings.php">Settings</a>
                <a href="../actions/logout.php">Logout</a>
            </div>
        </div>
    <?php endif; ?>
</div>

        </div>

    </nav>
</body>
</html>