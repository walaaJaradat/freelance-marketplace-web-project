<?php
try{


$host = 'localhost:3307'; // localhost:3306
$dbname ='freelance_marketplace'; // web1220198_freelance_marketplace
$username='root';
$password=''; 

$connstring =("mysql:host=$host;dbname=$dbname;charset=utf8mb4");
$pdo = new PDO($connstring,$username,$password);
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);


}catch(PDOException $e){
    die($e ->getMessage());
}
?>