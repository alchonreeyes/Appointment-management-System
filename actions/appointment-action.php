<?php
include '../config/db.php';
$db = new Database();
$pdo = $db->getConnection();

class Appointment{

    private $pdo;

    public function __construct($pdo)
    {
         $this->pdo = $pdo;
    }

    public function bookAppointment($data){
        
        

}
    
?>