<?php
class Database{
    private $server = "localhost";
    private $dbname = "appointmentdb";
    private $username = "root";
    private $password = "";
    protected $pdo;

    public function __construct(){
        $this->pdo = new PDO("mysql:host={$this->server};dbname={$this->dbname};", $this->username, $this->password);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    }

    public function getConnection(){
        return $this->pdo;
    }
}

?>