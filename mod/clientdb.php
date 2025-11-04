<?php 
$host = 'localhost';
$username = 'root';
$password = '';
$dbname = 'appointmentdb';

$mysqli = new mysqli($host, $username, $password, $dbname);
if ($mysqli->connect_error) {
    die('Connection Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}


?>