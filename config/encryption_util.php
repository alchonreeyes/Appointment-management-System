<?php
// config/encryption_util.php

// SECURITY NOTE: Sa production, ilagay ito sa .env file o sa labas ng public directory.
define('MASTER_ENCRYPTION_KEY', 'Your_32_Character_Secret_Key_Here_1234'); 
define('CIPHER_METHOD', 'AES-256-CBC');

/**
 * Advanced Encryption: Adds HMAC integrity check to prevent decryption of truncated data.
 */
function encrypt_data($data) {
    // 1. Basic Validation
    if (empty($data)) return $data;

    $key = MASTER_ENCRYPTION_KEY;
    
    // 2. Generate Initialization Vector (IV)
    $ivlen = openssl_cipher_iv_length(CIPHER_METHOD);
    $iv = openssl_random_pseudo_bytes($ivlen);
    
    // 3. Encrypt the data
    // OPENSSL_RAW_DATA is crucial to avoid double base64 encoding issues
    $ciphertext_raw = openssl_encrypt($data, CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv);
    
    // 4. Create an HMAC (Signature) to protect integrity
    // Ito ang magsasabi kung naputol (truncated) ang data sa database
    $hmac = hash_hmac('sha256', $iv . $ciphertext_raw, $key, true);
    
    // 5. Combine: IV + HMAC + Ciphertext and Encode
    return base64_encode($iv . $hmac . $ciphertext_raw);
}

/**
 * Advanced Decryption: Validates integrity before attempting to decrypt.
 * Works on any hosting by failing gracefully if data is corrupted.
 */
function decrypt_data($data) {
    // 1. Basic Validation
    if (empty($data)) return $data;

    $key = MASTER_ENCRYPTION_KEY;
    $ivlen = openssl_cipher_iv_length(CIPHER_METHOD);
    $sha2len = 32; // SHA256 length in bytes

    // 2. Decode
    $c = base64_decode($data);
    
    // 3. CRITICAL CHECK: Length Validation
    // Ang data ay dapat may laman na IV + HMAC + at least 1 byte ng ciphertext.
    // Kung mas maikli dito, ibig sabihin naputol ang data sa pag-save.
    if (strlen($c) < ($ivlen + $sha2len)) {
        // Data is corrupted or plain text
        return $data; 
    }

    // 4. Extract Components
    $iv = substr($c, 0, $ivlen);
    $hmac = substr($c, $ivlen, $sha2len);
    $ciphertext_raw = substr($c, $ivlen + $sha2len);

    // 5. Verify HMAC (Integrity Check)
    // Kino-compare natin ang HMAC ng data ngayon vs sa HMAC nung in-encrypt ito.
    $calcmac = hash_hmac('sha256', $iv . $ciphertext_raw, $key, true);
    
    // hash_equals is timing-attack safe
    if (!hash_equals($hmac, $calcmac)) {
        // HMAC Mismatch: Ibig sabihin may nagbago sa data or naputol ito.
        // Return original data safely instead of crashing.
        return $data;
    }

    // 6. Decrypt
    $original_plaintext = openssl_decrypt($ciphertext_raw, CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv);

    return $original_plaintext === false ? $data : $original_plaintext;
}
?>