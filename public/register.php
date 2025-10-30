<?php 
include '../actions/register-action.php';

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign up</title>
    <link rel="stylesheet" href="../assets/sign-up.css">
</head>
<body>
    <?php include '../includes/navbar.php' ?>    

    <div class="signup-wrapper"> 

        <main>
    <div class="login-wrapper">
        <form action="../actions/register-action.php" method="POST" class="login-form">
            <h1>Create account</h1>

            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" name="full_name" id="name" placeholder="Enter your Full name..." required>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" name="email" id="email" placeholder="Enter your Email address..." required>
            </div>
            

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" placeholder="Enter your password..." required>
            </div>

            <div class="form-group">
                <label for="phone_number">phone number</label>
                <input type="tel" name="phone_number" placeholder="Enter your phone number" required>
            </div>
            
            <div class="form-group">
                <label for="address">address</label>
               <input type="text" name="address" placeholder="Enter your address..." required>
            </div>


            <div class="form-group terms">
                <label>
                    <input type="checkbox" name="terms" required>
                    I agree to the Terms & Conditions and Privacy Policy
                </label>
                <label>
                    <input type="checkbox" name="policy" required>
                    General Use of Services
                </label>
            </div>

            <button type="submit" name="signup">Sign Up</button>
        </form>
    </div>
</main>

    
    
    
    
    
    <?php include '../includes/footer.php' ?>    




</body>
</html>