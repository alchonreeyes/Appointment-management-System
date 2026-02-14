    <?php
    // config/encryption_util.php

    // config/encryption_util.php

    // Use a simple key for testing (32 characters exactly!)
    // config/encryption_util.php
    define('MASTER_ENCRYPTION_KEY', 'EyeMasterClinic2026SecureKey123456'); // 32 chars!
    // Use THIS EXACT key on localhost AND InfinityFree
    define('CIPHER_METHOD', 'AES-256-CBC');

    // Keep the same functions - they'll work with the new key
    function encrypt_data($data) {
        if (empty($data)) return $data;
        $key = MASTER_ENCRYPTION_KEY;
        $ivlen = openssl_cipher_iv_length(CIPHER_METHOD);
        // CRITICAL: Ensure IV is random and 16 bytes
        $iv = openssl_random_pseudo_bytes($ivlen);
        
        $encrypted = openssl_encrypt($data, CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv);
        
        // Returns IV + Encrypted Data (encoded for storage)
        return base64_encode($iv . $encrypted);
    }

    function decrypt_data($data) {
        if (empty($data)) return $data;
        $key = MASTER_ENCRYPTION_KEY;
        $ivlen = openssl_cipher_iv_length(CIPHER_METHOD);
        
        $decoded = base64_decode($data, true); // Use strict mode

        // =======================================================
        // FIX FOR 15-BYTE ERROR (CRITICAL LENGTH CHECK)
        // =======================================================
        // Check if the decoded string is even long enough to contain the 16-byte IV
        if ($decoded === false || strlen($decoded) < $ivlen) { 
            // If data is corrupted, or shorter than the required IV length, 
            // treat it as unencrypted or bad data to prevent the crash.
            return $data; 
        }
        
        $iv = substr($decoded, 0, $ivlen);
        $encrypted_data = substr($decoded, $ivlen);
        
        $decrypted = openssl_decrypt($encrypted_data, CIPHER_METHOD, $key, OPENSSL_RAW_DATA, $iv);

        // If decryption fails (e.g., wrong key), return the original data
        return $decrypted === false ? $data : $decrypted;
    }
    ?>