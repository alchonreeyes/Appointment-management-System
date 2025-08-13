<?php
class Database{
    private $server = "localhost";
    private $dbname = "appointmentdb";
    private $username = "root";
    private $password = "";
    protected $pdo;

    public function __construct(){
        try {
        
        $this->pdo = new PDO("mysql:host={$this->server};dbname={$this->dbname};", $this->username, $this->password);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
         
        } catch (PDOException $e) {
            echo("Error connection" . $e->getMessage());
        }
    }

    public function getConnection(){
        return $this->pdo;
    }
}

?>