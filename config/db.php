<?php
class Database {
    private $server;
    private $dbname;
    private $username;
    private $password;
    protected $pdo;

    public function __construct() {
        // Detect if running on Localhost or Live Server
        $whitelist = array('127.0.0.1', '::1', 'localhost');
        if (in_array($_SERVER['SERVER_NAME'], $whitelist)) {
            // XAMPP SETTINGS
            $this->server = "localhost";
            $this->dbname = "capstone";
            $this->username = "root";
            $this->password = "";
        } else {
            // INFINITYFREE SETTINGS (Fill these from your Client Area)
            $this->server = "sql300.infinityfree.com"; 
            $this->dbname = "if0_38294_capstone";
            $this->username = "if0_38294";
            $this->password = "YourVpanelPassword";
        }

        try {       
            $this->pdo = new PDO("mysql:host={$this->server};dbname={$this->dbname};charset=utf8mb4", $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
            $this->pdo->exec("SET time_zone = '+08:00'");
            date_default_timezone_set('Asia/Manila');
        } catch (PDOException $e) {
            die("Error connecting: " . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->pdo;
    }
}
?>

<?php
// FILE: config/mailer_settings.php

// Define PHPMailer Credentials securely
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'alchonreyez@gmail.com'); // Your Gmail
define('SMTP_PASSWORD', 'urwbzscfmaynltzx'); // Your App Password
define('SMTP_PORT', 587);
?>