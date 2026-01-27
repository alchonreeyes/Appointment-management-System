<?php
class Database {
    private $server;
    private $dbname;
    private $username;
    private $password;
    protected $pdo;

    public function __construct() {
        $whitelist = array('127.0.0.1', '::1', 'localhost');
        if (in_array($_SERVER['SERVER_NAME'], $whitelist)) {
            $this->server = "localhost";
            $this->dbname = "capstone";
            $this->username = "root";
            $this->password = "";
        } else {
            // DOUBLE CHECK THIS HOSTNAME in your Client Area!
            $this->server = "sql100.infinityfree.com"; 
            $this->dbname = "if0_40958419_capstone";
            $this->username = "if0_40958419";  // Update with correct username from InfinityFree panel
            $this->password = "TQa6Uyin3H";  // Update with correct password from InfinityFree panel
        }

        try {       
            $this->pdo = new PDO("mysql:host={$this->server};dbname={$this->dbname};charset=utf8mb4", $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
            $this->pdo->exec("SET time_zone = '+08:00'");
            date_default_timezone_set('Asia/Manila');
        } catch (PDOException $e) {
            // On the live site, this will tell you exactly why it's failing
            die("Error connecting: " . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->pdo;
    }
}

// Mailer Settings (Keep these here or in a separate file)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'alchonreyez@gmail.com');
define('SMTP_PASSWORD', 'urwbzscfmaynltzx'); 
define('SMTP_PORT', 587);
?>