<?php
// =========================================================
// FOOTER LOGIC: FETCH DATA FROM DATABASE
// =========================================================

// 1. Check DB Connection (kung wala pa)
if (!isset($pdo)) {
    // Adjust path based on where footer is included. 
    // Assuming relative to footer.php location inside includes/
    // If footer is called from public/, this path '../config/db.php' works usually.
    // We use file_exists to be safe or just standard require.
    if (file_exists('../config/db.php')) {
        require_once '../config/db.php';
    } elseif (file_exists('../../config/db.php')) {
        require_once '../../config/db.php';
    }
    
    // Create connection only if $db object doesn't exist
    if (!isset($db)) {
        $db = new Database();
        $pdo = $db->getConnection();
    }
}

// 2. FETCH SERVICES (Appointments) - LIMIT 4
// Kukunin natin ang service_name at ID para sa link
$stmtServices = $pdo->prepare("SELECT service_id, service_name FROM services LIMIT 4");
$stmtServices->execute();
$footerServices = $stmtServices->fetchAll(PDO::FETCH_ASSOC);

// 3. FETCH BRANDS (Products) - LIMIT 10 (5 for Col 1, 5 for Col 2)
// DISTINCT para walang duplicate na brand name
$stmtBrands = $pdo->prepare("SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand != '' ORDER BY brand ASC LIMIT 10");
$stmtBrands->execute();
$allBrands = $stmtBrands->fetchAll(PDO::FETCH_ASSOC);

// Hatiin ang brands: 5 sa left, 5 sa right
$brandsCol1 = array_slice($allBrands, 0, 5);
$brandsCol2 = array_slice($allBrands, 5, 5);
?>

<style>
    /* General Footer Styles (PRESERVED FROM ORIGINAL) */
    .site-footer {
        background-color: #090909ff;
        color: #fff;
        padding: 40px 20px;
        font-family: 'Lucida Sans', 'Lucida Sans Regular', 'Lucida Grande', 'Lucida Sans Unicode', Geneva, Verdana, sans-serif;
    }

    .footer-container {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 30px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .footer-about, .footer-links-column {
        flex: 1;
        min-width: 220px; /* Prevents columns from getting too narrow */
    }

    .site-footer h3 {
        font-size: 1.2rem;
        font-weight: 600;
        margin-bottom: 15px;
    }

    .site-footer p {
        font-size: 0.8rem;
        line-height: 1.6;
        margin-bottom: 10px;
    }

    .site-footer ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .site-footer ul li {
        margin-bottom: 8px;
    }

    .site-footer ul li a {
        color: #ccc;
        text-decoration: none;
        font-size: 0.9rem;
        transition: color 0.3s ease;
    }

    .site-footer ul li a:hover {
        color: #fff;
        text-decoration: underline;
    }

    /* Social Icons Styling */
    .social-icons {
        margin-top: 15px;
        display: flex;
        gap: 15px;
    }

    .social-icons a {
        color: #fff;
        font-size: 1.2rem;
        transition: transform 0.3s ease, color 0.3s ease;
    }

    .social-icons a:hover {
        color: #007bff; /* Example hover color */
        transform: translateY(-3px);
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .footer-container {
            flex-direction: column;
            text-align: center;
        }
        
        .social-icons {
            justify-content: center;
        }
    }
</style>

<footer class="site-footer">
    <div class="footer-container">
        
        <div class="footer-about">
            <h3>Eye Master</h3>
            <p>
                Providing quality eye care services and products since 1960. 
                Your vision is our priority. Visit our clinic for a comprehensive 
                eye examination and a wide range of eyewear.
            </p>
            
            <div class="social-icons">
                <a href="https://web.facebook.com/EyeMasterOpticalClinic" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                <a href="../public/store.php" aria-label="Email"><i class="fas fa-envelope"></i></a>
            </div>
        </div>

        <div class="footer-links-column-container" style="display: flex; gap: 50px; flex: 2; justify-content: flex-end; flex-wrap: wrap;">
            
            <div class="footer-links-column">
                <h3>Appointments</h3>
                <ul>
                    <?php if (!empty($footerServices)): ?>
                        <?php foreach ($footerServices as $svc): ?>
                            <li>
                                <a href="../public/appointment.php?service_id=<?= $svc['service_id'] ?>">
                                    <?= htmlspecialchars($svc['service_name']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li><a href="#">No services available</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="footer-links-column">
                <h3>Brands</h3>
                <ul>
                    <?php if (!empty($brandsCol1)): ?>
                        <?php foreach ($brandsCol1 as $br): ?>
                            <li>
                                <a href="../public/browse.php?search=<?= urlencode($br['brand']) ?>">
                                    <?= htmlspecialchars($br['brand']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li><a href="#">No brands available</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="footer-links-column">
                <h3>&nbsp;</h3> <ul>
                    <?php if (!empty($brandsCol2)): ?>
                        <?php foreach ($brandsCol2 as $br): ?>
                            <li>
                                <a href="../public/browse.php?search=<?= urlencode($br['brand']) ?>">
                                    <?= htmlspecialchars($br['brand']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>&nbsp;</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

    </div>
    
    <div style="text-align: center; border-top: 1px solid #333; margin-top: 30px; padding-top: 20px; font-size: 0.8rem; color: #aaa;">
        &copy; <?= date('Y') ?> Eye Master Optical. All Rights Reserved.
    </div>
</footer>