<?php
class Database{
    private $server = "localhost";
    private $dbname = "appointmentdb";
    private $username = "root";
    private $password = "";
    protected $pdo;

    public function __construct(){
        try {
        //constructor kasi ayoko ma hack, kin nalang puso mo
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