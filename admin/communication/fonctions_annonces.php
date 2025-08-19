<?php
/**
 * Fonctions pour l'affichage des annonces côté client
 * À inclure dans vos pages index.php et menu.php
 */

require_once __DIR__ . '/../../config.php'; // Ici config.php doit créer $conn comme instance PDO

/**
 * Récupère les annonces actives selon le type et la période
 * @param string $type 'site' ou 'menu'
 * @return array Liste des annonces
 */
function getAnnoncesActives($type = 'site') {
    global $conn;
    
    $date_aujourd_hui = date('Y-m-d');

    $sql = "SELECT * FROM annonce_public 
            WHERE statut = 'active' 
            AND type_annonce = :type
            AND (date_debut IS NULL OR date_debut <= :date)
            AND (date_fin IS NULL OR date_fin >= :date)
            ORDER BY date_creation DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':type' => $type,
        ':date' => $date_aujourd_hui
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Affiche les annonces sous forme de bannières
 * @param string $type 'site' ou 'menu'
 * @param string $position 'top', 'bottom', 'sidebar'
 */
function afficherAnnonces($type = 'site', $position = 'top') {
    $annonces = getAnnoncesActives($type);
    
    if (empty($annonces)) return;
    
    $css_class = match($position) {
        'top' => 'annonces-top',
        'bottom' => 'annonces-bottom',
        'sidebar' => 'annonces-sidebar',
        default => 'annonces-top',
    };
    
   foreach ($annonces as $annonce) {
        // Extraire uniquement le texte sans formatage
        $contenu = strip_tags($annonce['contenu']);
        $contenu = str_replace(["\r", "\n"], ' ', $contenu);
        $contenu = preg_replace('/\s+/', ' ', $contenu);
        
        // Limiter la longueur si nécessaire
        if (strlen($contenu) > 150) {
            $contenu = substr($contenu, 0, 147) . '...';
        }
        
        echo '<div class="annonce">' . htmlspecialchars($contenu) . '</div>';
    }

   
     echo '</div>';
    // Ajout du CSS et JS
    echo '<style>
    /* ... ton CSS ici ... */
    
    </style>';

    echo '<script>
    function fermerAnnonce(button) {
        const annonce = button.closest(".annonce-banner");
        annonce.style.animation = "slideOut 0.3s ease-in forwards";
        setTimeout(() => annonce.remove(), 300);
    }
    const style = document.createElement("style");
    style.textContent = `@keyframes slideOut { to { opacity: 0; transform: translateY(-20px); height: 0; margin: 0; padding: 0; } }`;
    document.head.appendChild(style);
    </script>';
}

/**
 * Compte le nombre d'annonces actives
 * @param string $type 'site' ou 'menu'
 * @return int
 */
function compterAnnoncesActives($type = 'site') {
    return count(getAnnoncesActives($type));
}

/**
 * Vérifie s'il y a des annonces urgentes
 * @param string $type 'site' ou 'menu'
 * @return bool
 */
function aAnnoncesUrgentes($type = 'site') {
    $annonces = getAnnoncesActives($type);
    $mots_urgents = ['urgent', 'fermeture', 'fermé', 'annulation', 'annulé', 'important'];
    
    foreach ($annonces as $annonce) {
        $texte = strtolower($annonce['titre'] . ' ' . $annonce['contenu']);
        foreach ($mots_urgents as $mot) {
            if (strpos($texte, $mot) !== false) return true;
        }
    }
    return false;
}

/**
 * Affiche une notification simple pour les annonces
 * @param string $type 'site' ou 'menu'
 */
function afficherNotificationAnnonces($type = 'site') {
    $count = compterAnnoncesActives($type);
    $urgent = aAnnoncesUrgentes($type);
    
    if ($count > 0) {
        $class = $urgent ? 'alert-warning' : 'alert-info';
        $icon = $urgent ? 'fa-exclamation-triangle' : 'fa-info-circle';
        $texte = $urgent ? 'Annonce importante' : 'Information';
        
        echo '<div class="alert ' . $class . ' alert-dismissible fade show" role="alert">';
       // echo '<i class="fas ' . $icon . '"></i> ';
        //echo '<strong>' . $texte . '</strong> - Consultez nos annonces ci-dessous.';
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }
}
?>
