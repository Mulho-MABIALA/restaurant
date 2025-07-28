<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $nom = $_POST['name'] ?? '';
    $prix = $_POST['price'] ?? 0;

    if ($id) {
        if (!isset($_SESSION['panier'][$id])) {
            $_SESSION['panier'][$id] = 1;
        } else {
            $_SESSION['panier'][$id]++;
        }

        echo json_encode([
            'success' => true,
            'message' => "$nom ajouté au panier."
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => "Produit invalide."]);
    }
} else {
    echo json_encode(['success' => false, 'message' => "Méthode non autorisée."]);
}
?>
