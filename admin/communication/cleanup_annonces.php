<?php
require_once '../../config.php'; // Assurez-vous que $conn est défini ici
require_once 'fonctions_annonces.php'; // Contient les fonctions getAnnoncesActives(), etc.

/**
 * Désactiver les annonces expirées et afficher le résultat
 */
$desactivees = desactiverAnnoncesExpirees();
echo "Annonces désactivées: $desactivees\n";

/**
 * Nettoyer les anciennes annonces inactives (optionnel)
 */
// $supprimees = nettoyerAnnoncesExpirees();
// echo "Annonces supprimées: $supprimees\n";

echo "Nettoyage terminé à " . date('Y-m-d H:i:s') . "\n";


/**
 * NOTIFIER UNE NOUVELLE ANNONCE PAR EMAIL
 */
function notifierNouvelleAnnonce($id_annonce) {
    global $conn;

    $sql = "SELECT * FROM annonce_public WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':id' => $id_annonce]);
    $annonce = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($annonce) {
        $sujet = "Nouvelle annonce: " . $annonce['titre'];
        $message = "Une nouvelle annonce a été publiée:\n\n";
        $message .= "Titre: " . $annonce['titre'] . "\n";
        $message .= "Type: " . $annonce['type_annonce'] . "\n";
        $message .= "Contenu: " . $annonce['contenu'] . "\n";

        // Ajouter ici votre système d'envoi d'email
        // mail($destinataire, $sujet, $message);
    }
}


/**
 * WIDGET DASHBOARD POUR ADMIN
 */
function afficherWidgetAnnonces() {
    $stats = getStatistiquesAnnonces(); // Utilise PDO maintenant

    echo '<div class="widget-annonces card">';
    echo '<div class="card-header"><h5><i class="fas fa-bullhorn"></i> Annonces</h5></div>';
    echo '<div class="card-body">';
    echo '<div class="row text-center gap-4 md:gap-0 flex flex-wrap justify-around">';

    echo '<div class="col-md-3"><h3 class="text-primary text-2xl">' . $stats['total'] . '</h3><small>Total</small></div>';
    echo '<div class="col-md-3"><h3 class="text-success text-2xl">' . $stats['actives'] . '</h3><small>Actives</small></div>';
    echo '<div class="col-md-3"><h3 class="text-info text-2xl">' . ($stats['par_type']['menu'] ?? 0) . '</h3><small>Menu</small></div>';
    echo '<div class="col-md-3"><h3 class="text-warning text-2xl">' . ($stats['par_type']['site'] ?? 0) . '</h3><small>Site</small></div>';

    echo '</div>';

    if ($stats['expire_aujourdhui'] > 0) {
        echo '<div class="alert alert-warning mt-3 flex items-center gap-2">';
        echo '<i class="fas fa-exclamation-triangle"></i> ';
        echo $stats['expire_aujourdhui'] . ' annonce(s) expire(nt) aujourd\'hui';
        echo '</div>';
    }

    echo '</div>';
    echo '</div>';
}
?>
