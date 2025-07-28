<?php
$host = 'localhost';
$db   = 'restaurant';
$user = 'root';
$pass = ''; // mot de passe MySQL (souvent vide sous WAMP)

try {
    $conn = new PDO("mysql:host=localhost;dbname=restaurant;charset=utf8mb4", "root", "");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur connexion BDD : " . $e->getMessage());
}
?>

