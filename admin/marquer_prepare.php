<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_detail'])) {
    $id_detail = $_POST['id_detail'];

    // Récupérer le nom du plat
    $stmt = $conn->prepare("SELECT nom_plat FROM commande_details WHERE id = ?");
    $stmt->execute([$id_detail]);
    $plat = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($plat) {
        // Pour chaque ingrédient de la recette, déduire la quantité
        $stmt = $conn->prepare("SELECT id_ingredient, quantite_utilisee FROM recettes WHERE nom_plat = ?");
        $stmt->execute([$plat['nom_plat']]);
        $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($ingredients as $ingredient) {
            $conn->prepare("
                UPDATE ingredients
                SET stock_actuel = stock_actuel - ?
                WHERE id = ?
            ")->execute([
                $ingredient['quantite_utilisee'],
                $ingredient['id_ingredient']
            ]);
        }
    }

    // Marquer le plat comme préparé
    $stmt = $conn->prepare("UPDATE commande_details SET prepare = 1 WHERE id = ?");
    $stmt->execute([$id_detail]);
}

header("Location: cuisine.php");
exit;
