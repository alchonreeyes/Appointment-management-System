<?php
class Database{
    private $server = "localhost";
    private $dbname = "capstone";
    private $username = "root";
    private $password = "";
    protected $pdo;

    public function __construct(){
        try {       
        $this->pdo = new PDO("mysql:host={$this->server};dbname={$this->dbname};", $this->username, $this->password);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
        $this->pdo->exec("SET time_zone = '+08:00'");
              date_default_timezone_set('Asia/Manila');
    } catch (PDOException $e) {
            echo("Error connecting: " . $e->getMessage());
            exit;
        }
    }
    
    

    public function getConnection(){
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