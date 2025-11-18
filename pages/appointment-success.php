<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Confirmed</title>
    <link rel="stylesheet" href="../assets/login.css">
    
    <style>
        /* Page Background - Matches your theme */
        body {
            background-color: #FFF0F0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .main-content {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }

        /* Card Container */
        .auth-card {
            background: white;
            width: 100%;
            max-width: 500px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(211, 69, 53, 0.15);
            overflow: hidden;
            text-align: center;
            opacity: 0;
            animation: fadeUp 0.8s ease-out forwards; /* Entrance animation */
        }

        /* Header Section */
        .card-header {
            background-color: #D94032;
            color: white;
            padding: 50px 20px;
            position: relative;
            overflow: hidden;
        }

        /* Decorative background circles */
        .header-shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        .shape-1 { width: 200px; height: 200px; top: -100px; left: -50px; }
        .shape-2 { width: 150px; height: 150px; bottom: -50px; right: -20px; }

        /* ANIMATED CHECKMARK CONTAINER */
        .checkmark-circle {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px auto;
            position: relative;
            z-index: 2;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        /* SVG Styles */
        .checkmark-svg {
            width: 50px;
            height: 50px;
            stroke: #D94032;
            stroke-width: 4;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
            stroke-dasharray: 100; /* Length of the path */
            stroke-dashoffset: 100; /* Hide it initially */
            animation: drawCheck 1s ease-in-out forwards 0.5s; /* Start after card loads */
        }

        .card-header h1 {
            margin: 0;
            font-size: 32px;
            font-weight: 700;
            position: relative;
            z-index: 2;
        }

        /* Body Section */
        .card-body {
            padding: 40px;
        }

        .card-body p {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .redirect-text {
            font-size: 14px;
            color: #999;
            margin-top: 20px;
        }

        /* Button */
        .btn-home {
            background-color: #D94032;
            color: white;
            text-decoration: none;
            padding: 15px 40px;
            border-radius: 30px;
            font-weight: 600;
            display: inline-block;
            transition: transform 0.2s, background 0.2s;
            box-shadow: 0 4px 10px rgba(217, 64, 50, 0.3);
        }

        .btn-home:hover {
            background-color: #b93529;
            transform: translateY(-2px);
        }

        /* Keyframe Animations */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes drawCheck {
            to { stroke-dashoffset: 0; }
        }

    </style>
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<div class="main-content">
    <div class="auth-card">
        <div class="card-header">
            <div class="header-shape shape-1"></div>
            <div class="header-shape shape-2"></div>
            
            <div class="checkmark-circle">
                <svg class="checkmark-svg" viewBox="0 0 24 24">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>
            
            <h1>Success!</h1>
        </div>

        <div class="card-body">
            <h2 style="color:#333; margin-top:0;">Appointment Booked</h2>
            <p>Thank you! Your appointment has been successfully scheduled. We have sent a confirmation email with the details.</p>
            
            <a href="../public/home.php" class="btn-home">Return to Home</a>

            <div class="redirect-text">
                Redirecting in <span id="countdown">5</span> seconds...
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
    // Countdown Timer Logic
    let seconds = 5;
    const countdownElement = document.getElementById('countdown');
    
    const interval = setInterval(() => {
        seconds--;
        countdownElement.textContent = seconds;
        
        if (seconds <= 0) {
            clearInterval(interval);
            // Redirect to home page
            window.location.href = '../public/home.php';
        }
    }, 1000); // Update every 1 second
</script>

</body>
</html>