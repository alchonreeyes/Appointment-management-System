<?php
require_once '../config/db.php';
require_once '../config/encryption_util.php';

$db = new Database();
$pdo = $db->getConnection();

// Palitan ang ID na ito sa ID ng account mo sa database
$test_id = 1; 

$stmt = $pdo->prepare("SELECT full_name, phone_number FROM users WHERE id = ?");
$stmt->execute([$test_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>Diagnostic Results:</h3>";
if ($row) {
    echo "<b>Raw Name in DB:</b> " . $row['full_name'] . " (Length: " . strlen($row['full_name']) . ")<br>";
    echo "<b>Decrypted Name:</b> " . decrypt_data($row['full_name']) . "<br><br>";

    echo "<b>Raw Phone in DB:</b> " . $row['phone_number'] . " (Length: " . strlen($row['phone_number']) . ")<br>";
    echo "<b>Decrypted Phone:</b> " . decrypt_data($row['phone_number']) . "<br>";
    
    if (strlen($row['phone_number']) < 20) {
        echo "<p style='color:red;'>⚠️ WARNING: Ang phone number mo ay masyadong maikli! Pinuputol ito ng database kaya hindi ma-decrypt.</p>";
    }
} else {
    echo "User not found.";
}
?>