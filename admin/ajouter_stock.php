<?php
require_once '../config.php';
session_start();

// Vérification de la session d'administration
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom']);
    $quantite = (int) $_POST['quantite'];
    $unite = trim($_POST['unite']);
    $seuil = (int) $_POST['seuil_alerte'];

    if (!empty($nom)) {
        try {
            $stmt = $conn->prepare("INSERT INTO stocks (nom, quantite, unite, seuil_alerte) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nom, $quantite, $unite, $seuil]);

            // Redirection après succès
            header("Location: gestion_stock.php");
            exit;
        } catch (PDOException $e) {
            echo "Erreur lors de l'insertion : " . $e->getMessage();
        }
    } else {
        echo "Le nom du stock ne peut pas être vide.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter un ingrédient</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss/dist/tailwind.min.css">
</head>
<body class="bg-gray-100 p-6">
    <h1 class="text-xl font-bold mb-4">Ajouter un ingrédient</h1>

    <form method="POST" class="bg-white p-4 rounded shadow-md w-full max-w-md">
        <label>Nom :</label>
        <input type="text" name="nom" required class="border p-2 w-full mb-3">

        <label>Quantité :</label>
        <input type="number" name="quantite" required class="border p-2 w-full mb-3">

        <label>Unité (ex : kg, L, pièces) :</label>
        <input type="text" name="unite" class="border p-2 w-full mb-3">

        <label>Seuil d’alerte :</label>
        <input type="number" name="seuil_alerte" required class="border p-2 w-full mb-3">

        <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded">➕ Ajouter</button>
    </form>
</body>
</html>
