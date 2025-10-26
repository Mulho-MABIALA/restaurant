<?php
require_once '../config.php'; // connexion PDO dans $conn

// Données admin à ajouter
$username = 'mulho';
$email = 'mulhomabiala29@gmail.com';
$password_plain = '1010';  // mot de passe en clair
$role = 'admin';           // ou 'superadmin'

$password_hashed = password_hash($password_plain, PASSWORD_DEFAULT);

try {
    $stmt = $conn->prepare("INSERT INTO admin (username, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, $email, $password_hashed, $role]);
    echo "Admin ajouté avec succès.";
} catch (PDOException $e) {
    echo "Erreur lors de l'ajout de l'admin : " . $e->getMessage();
}
