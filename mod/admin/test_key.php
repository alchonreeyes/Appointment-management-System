<?php
// FILE: test_key.php
// Ilagay ito sa loob ng 'mod/admin/' folder para madaling i-run.

// 1. ITO ANG KEY NA NASA CONFIG MO NGAYON
// Kung binago mo ito dati, HINDI gagana ang decryption.
define('CURRENT_KEY', 'Your_32_Character_Secret_Key_Here_1234'); 
define('CIPHER_METHOD', 'AES-256-CBC');

// 2. ITO ANG DATA MULA SA SCREENSHOT MO (Appointment ID 2)
// Encrypted Name: +Mj7/f3Pi6nxZJ6mPeZO8Ul7WYKL1owFDyV4qtqJrrQ=
$encrypted_sample = "+Mj7/f3Pi6nxZJ6mPeZO8Ul7WYKL1owFDyV4qtqJrrQ=";

function try_decrypt($data, $key) {
    $ivlen = openssl_cipher_iv_length(CIPHER_METHOD);
    $decoded = base64_decode($data, true);
    
    if ($decoded === false || strlen($decoded) < $ivlen) { 
        return "ERROR: Data corrupted or not encrypted properly."; 
    }
    
    $iv = substr($decoded, 0, $ivlen);
    $encrypted_data = substr($decoded, $ivlen);
    
    // Try to decrypt
    $decrypted = openssl_decrypt($encrypted_data, CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv);

    if ($decrypted === false) {
        return "❌ FAILED: Wrong Key";
    }
    return "✅ SUCCESS: " . $decrypted;
}

echo "<h1>Decryption Test</h1>";
echo "<strong>Testing Key:</strong> " . CURRENT_KEY . "<br><br>";
echo "<strong>Encrypted String:</strong> " . $encrypted_sample . "<br><br>";
echo "<strong>Result:</strong> " . try_decrypt($encrypted_sample, CURRENT_KEY);

?>