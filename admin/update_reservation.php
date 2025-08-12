<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once '../config.php';

// Vérifier si l'utilisateur est un admin connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: reservations.php');
    exit;
}

$id = intval($_POST['id']);
$nom = htmlspecialchars($_POST['nom'] ?? '');
$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
$telephone = htmlspecialchars($_POST['telephone'] ?? '');
$date_reservation = $_POST['date_reservation'] ?? '';
$heure_reservation = $_POST['heure_reservation'] ?? '';
$personnes = intval($_POST['personnes'] ?? 1);
$message = htmlspecialchars($_POST['message'] ?? ''); // AJOUT du message

// Validation des données
if (empty($nom) || empty($email) || empty($telephone) || empty($date_reservation) || empty($heure_reservation)) {
    $_SESSION['erreur'] = "Tous les champs obligatoires doivent être remplis";
    header("Location: reservations.php");
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['erreur'] = "L'email n'est pas valide";
    header("Location: reservations.php");
    exit;
}

// Requête UPDATE modifiée pour inclure le message
$stmt = $conn->prepare("UPDATE reservations SET 
    nom = ?, 
    email = ?, 
    telephone = ?, 
    date_reservation = ?, 
    heure_reservation = ?, 
    personnes = ?,
    message = ?
    WHERE id = ?");

// Exécution avec le message inclus
if ($stmt->execute([$nom, $email, $telephone, $date_reservation, $heure_reservation, $personnes, $message, $id])) {
    $_SESSION['success'] = "Réservation mise à jour avec succès";
} else {
    $_SESSION['erreur'] = "Erreur lors de la mise à jour: " . implode(" - ", $stmt->errorInfo());
}

header("Location: reservations.php");
exit;
?>