<?php
include '../config/db.php';
$db = new Database();
$pdo = $db->getConnection();

// 1. Fetch all Services (IDs 11, 12, 13)
$servicesStmt = $pdo->prepare("SELECT * FROM services WHERE service_id IN (11, 12, 13) ORDER BY service_id ASC");
$servicesStmt->execute();
$services = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch all Particulars
$partStmt = $pdo->prepare("SELECT * FROM particulars");
$partStmt->execute();
$allParticulars = $partStmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function to filter particulars for a specific service
function getParticulars($service_id, $data, $category = 'benefit') {
    return array_filter($data, function($item) use ($service_id, $category) {
        return $item['service_id'] == $service_id && $item['category'] == $category;
    });
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment</title>
    <link rel="stylesheet" href="../assets/book_appointment.css">
    <style>
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            margin: 0;
            background-image: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.9)), url("../assets/src/eyewear-share.jpg");
            background-size: cover;
            background-repeat: no-repeat;
             font-family: Arial, sans-serif;
        }
        .content-wrapper {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            padding: 2rem 1rem;
        }
        /* Fix for nav fonts */
        .nav-links ul li a {
            font-family: 'Lucida Sans', 'Lucida Sans Regular', 'Lucida Grande', 'Lucida Sans Unicode', Geneva, Verdana, sans-serif;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="content-wrapper">
        <div class="book-appointment">
            
            <?php foreach ($services as $service): 
                $sId = $service['service_id'];
                // Determine styling class based on service ID (optional logic)
                $cardClass = ($sId == 13) ? 'visionplus' : 'vision7';
                
                // Determine Link based on service ID
                $link = '#';
                if($sId == 11) $link = '../public/appointment.php';
                if($sId == 12) $link = '../public/medical.php';
                if($sId == 13) $link = '../public/ishihara.php';
            ?>

            <div class="appointment-wrapper <?= $cardClass ?>">
                <div class="card-header">
                    <h1 class="brand-name">
                        <?php 
                        // Split name for styling "Eye-Examination <br> Appointment"
                        $nameParts = explode(" Appointment", $service['service_name']);
                        echo $nameParts[0]; 
                        ?> 
                        <br> 
                        <span class="highlight">Appointment</span>
                    </h1>
                    
                    <p class="service-description"><?= htmlspecialchars($service['description']) ?></p>
                </div>
                
                <?php 
                $benefits = getParticulars($sId, $allParticulars, 'benefit');
                if (!empty($benefits)): 
                ?>
                <div class="benefits-list">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <?php foreach ($benefits as $p): ?>
                        <div class="benefit-item" style="display: flex; align-items: center; gap: 10px;">
                            <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="width: 24px; height: 24px; stroke: red; background: #e8f5e9; border-radius: 50%; padding: 4px; flex-shrink: 0;">
                                <path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span style="font-size: 0.9rem; color: #333;"><?= htmlspecialchars($p['label']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php 
                $diseases = getParticulars($sId, $allParticulars, 'disease');
                if (!empty($diseases)): 
                ?>
                <div class="health-screening">
                    <h3>Can pre-determine health diseases:</h3>
                    <div class="disease-grid">
                        <?php foreach ($diseases as $p): ?>
                        <div class="disease-item">
                            <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span><?= htmlspecialchars($p['label']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>  
                <?php endif; ?>

                <?php 
                $extras = getParticulars($sId, $allParticulars, 'extra');
                if (!empty($extras)): 
                ?>
                <div class="extra-benefits">
                    <?php foreach ($extras as $p): ?>
                    <div class="extra-item">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                            <path d="M2 17l10 5 10-5M2 12l10 5 10-5"/>
                        </svg>
                        <span><?= htmlspecialchars($p['label']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <a class="appointment-btn" style="text-align: center; font-family: Arial, Helvetica, sans-serif; text-decoration: none;" href="<?= $link ?>">BOOK AN APPOINTMENT</a>
            </div>
            
            <?php endforeach; ?>

        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>