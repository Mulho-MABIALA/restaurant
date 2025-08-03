<?php
require_once '../../config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = $_POST['titre'];
    $contenu = $_POST['contenu'];
    $importance = $_POST['importance'];

    $stmt = $conn->prepare("INSERT INTO annonces (titre, contenu, importance) VALUES (?, ?, ?)");
    $stmt->execute([$titre, $contenu, $importance]);

    header('Location: annonces.php');
    exit;
}
?>

<h2 class="text-2xl font-bold mb-4">➕ Nouvelle annonce</h2>

<form method="post" class="space-y-4">
    <input type="text" name="titre" placeholder="Titre de l'annonce" required class="w-full border p-2 rounded" />
    
    <textarea name="contenu" rows="4" placeholder="Contenu..." required class="w-full border p-2 rounded"></textarea>

    <select name="importance" class="border p-2 rounded">
        <option value="basse">Importance basse</option>
        <option value="moyenne">Importance moyenne</option>
        <option value="haute">Importance haute</option>
    </select>

    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Publier</button>
</form>
