<?php
class SecurityHelper {
    private $pdo;
    
    // Rate limits
    const MAX_ATTEMPTS_PER_IP = 5;        // 5 attempts per IP per hour
    const MAX_ATTEMPTS_PER_EMAIL = 5;     // 5 attempts per email per hour
    const BAN_DURATION = 3600;             // 1 hour ban
    const PERMANENT_BAN_THRESHOLD = 5;     // 5 violations = permanent ban
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get real IP address (works with proxies)
     */
    public function getRealIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
    
    /**
     * Check if IP is blacklisted
     */
    public function isIPBlocked($ip = null) {
        $ip = $ip ?? $this->getRealIP();
        
        $stmt = $this->pdo->prepare("
            SELECT * FROM ip_blacklist 
            WHERE ip_address = ? 
            AND (is_permanent = 1 OR expires_at > NOW())
            LIMIT 1
        ");
        $stmt->execute([$ip]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Check rate limits and log attempt
     */
    public function checkRateLimit($email, $action_type = 'resend_verification') {
        $ip = $this->getRealIP();
        
        // 1. Check if IP is blocked
        if ($this->isIPBlocked($ip)) {
            return [
                'allowed' => false,
                'reason' => 'blocked',
                'message' => 'Your IP has been blocked due to suspicious activity.'
            ];
        }
        
        // 2. Clean old logs (older than 1 hour)
        $this->pdo->prepare("DELETE FROM email_abuse_log WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 1 HOUR)")->execute();
        
        // 3. Check Email-based rate limit (PRIMARY CHECK)
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count 
            FROM email_abuse_log 
            WHERE target_email = ? 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$email]);
        $emailAttempts = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($emailAttempts >= self::MAX_ATTEMPTS_PER_EMAIL) {
            return [
                'allowed' => false,
                'reason' => 'email_limit',
                'message' => 'Too many requests for this email. Please try again in 1 hour.'
            ];
        }
        
        // 4. Check IP-based rate limit (SECONDARY CHECK)
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count 
            FROM email_abuse_log 
            WHERE ip_address = ? 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$ip]);
        $ipAttempts = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($ipAttempts >= self::MAX_ATTEMPTS_PER_IP) {
            $this->blockIP($ip, "Exceeded rate limit ($ipAttempts attempts in 1 hour)", false);
            
            return [
                'allowed' => false,
                'reason' => 'ip_limit',
                'message' => 'Too many requests from your IP. Access temporarily suspended.'
            ];
        }
        
        // 5. Log this attempt
        $this->logAttempt($ip, $email, $action_type);
        
        return ['allowed' => true];
    }
    
    /**
     * Log abuse attempt
     */
    private function logAttempt($ip, $email, $action_type) {
        $stmt = $this->pdo->prepare("
            INSERT INTO email_abuse_log (ip_address, target_email, action_type) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$ip, $email, $action_type]);
    }
    
    /**
     * Block IP address
     */
    public function blockIP($ip, $reason, $permanent = false) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as violations FROM ip_blacklist WHERE ip_address = ?");
        $stmt->execute([$ip]);
        $violations = $stmt->fetch(PDO::FETCH_ASSOC)['violations'];
        
        if ($violations >= self::PERMANENT_BAN_THRESHOLD) {
            $permanent = true;
            $reason = "Multiple violations - Permanent ban";
        }
        
        $expires = $permanent ? null : date('Y-m-d H:i:s', time() + self::BAN_DURATION);
        
        $stmt = $this->pdo->prepare("
            INSERT INTO ip_blacklist (ip_address, reason, expires_at, is_permanent) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                reason = VALUES(reason),
                expires_at = VALUES(expires_at),
                is_permanent = VALUES(is_permanent),
                blocked_at = NOW()
        ");
        
        $stmt->execute([$ip, $reason, $expires, $permanent ? 1 : 0]);
        
        error_log("IP BLOCKED: $ip - Reason: $reason - Permanent: " . ($permanent ? 'YES' : 'NO'));
    }
}
?>
