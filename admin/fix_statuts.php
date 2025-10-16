<?php
/**
 * Script de migration : Convertir les statuts 'en_attente' en 'non_lu'
 * À exécuter une seule fois pour corriger les anciennes réservations
 */

session_start();
require_once '../config.php';

// Vérifier que l'admin est connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die('Accès non autorisé');
}

try {
    // Mettre à jour tous les statuts 'en_attente' en 'non_lu'
    $stmt = $conn->prepare("UPDATE reservations SET statut = 'non_lu' WHERE statut = 'en_attente'");
    $stmt->execute();

    $affected = $stmt->rowCount();

    echo "✅ Migration réussie !<br>";
    echo "📊 Nombre de réservations mises à jour : <strong>$affected</strong><br><br>";
    echo "Vous pouvez maintenant retourner sur la page <a href='reservations.php' style='color: blue; text-decoration: underline;'>Réservations</a>";

} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage();
}
?>
