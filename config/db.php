<?php
class Database{
    private $server = "localhost";
    private $dbname = "temp_appointmentdb";
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