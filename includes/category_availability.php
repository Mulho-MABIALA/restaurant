<?php
/**
 * Fonctions pour gérer la disponibilité horaire des catégories et des plats
 */

/**
 * Vérifie si une catégorie est disponible à l'heure actuelle
 *
 * @param array $categorie Tableau contenant les informations de la catégorie
 * @return bool True si disponible, False sinon
 */
function isCategoryAvailable($categorie) {
    // Si la disponibilité horaire n'est pas active, la catégorie est toujours disponible
    if (empty($categorie['disponibilite_active']) || $categorie['disponibilite_active'] != 1) {
        return true;
    }

    // Si pas d'horaires définis, on considère disponible
    if (empty($categorie['heure_debut']) || empty($categorie['heure_fin'])) {
        return true;
    }

    // Récupérer l'heure actuelle
    $now = new DateTime();
    $currentTime = $now->format('H:i:s');

    // Convertir les heures de début et fin
    $heureDebut = $categorie['heure_debut'];
    $heureFin = $categorie['heure_fin'];

    // Vérifier si l'heure actuelle est dans la plage
    if ($currentTime >= $heureDebut && $currentTime <= $heureFin) {
        return true;
    }

    return false;
}

/**
 * Récupère le message de disponibilité pour une catégorie
 *
 * @param array $categorie Tableau contenant les informations de la catégorie
 * @return string Message de disponibilité
 */
function getCategoryAvailabilityMessage($categorie) {
    if (isCategoryAvailable($categorie)) {
        return "Disponible maintenant";
    }

    if (!empty($categorie['disponibilite_active']) && $categorie['disponibilite_active'] == 1) {
        $debut = substr($categorie['heure_debut'], 0, 5);
        $fin = substr($categorie['heure_fin'], 0, 5);
        return "Disponible de {$debut} à {$fin}";
    }

    return "Toujours disponible";
}

/**
 * Récupère le temps restant avant la fin de disponibilité
 *
 * @param array $categorie Tableau contenant les informations de la catégorie
 * @return string|null Temps restant ou null
 */
function getTimeUntilCategoryUnavailable($categorie) {
    if (!isCategoryAvailable($categorie)) {
        return null;
    }

    if (empty($categorie['disponibilite_active']) || $categorie['disponibilite_active'] != 1) {
        return null;
    }

    $now = new DateTime();
    $endTime = DateTime::createFromFormat('H:i:s', $categorie['heure_fin']);

    if (!$endTime) {
        return null;
    }

    // Ajouter la date actuelle à l'heure de fin
    $endTime->setDate($now->format('Y'), $now->format('m'), $now->format('d'));

    $diff = $endTime->getTimestamp() - $now->getTimestamp();

    if ($diff <= 0) {
        return null;
    }

    $hours = floor($diff / 3600);
    $minutes = floor(($diff % 3600) / 60);

    if ($hours > 0) {
        return "{$hours}h {$minutes}min";
    } else {
        return "{$minutes}min";
    }
}

/**
 * Récupère le temps avant le début de disponibilité
 *
 * @param array $categorie Tableau contenant les informations de la catégorie
 * @return string|null Temps avant disponibilité ou null
 */
function getTimeUntilCategoryAvailable($categorie) {
    if (isCategoryAvailable($categorie)) {
        return null;
    }

    if (empty($categorie['disponibilite_active']) || $categorie['disponibilite_active'] != 1) {
        return null;
    }

    $now = new DateTime();
    $startTime = DateTime::createFromFormat('H:i:s', $categorie['heure_debut']);

    if (!$startTime) {
        return null;
    }

    // Ajouter la date actuelle à l'heure de début
    $startTime->setDate($now->format('Y'), $now->format('m'), $now->format('d'));

    $diff = $startTime->getTimestamp() - $now->getTimestamp();

    if ($diff <= 0) {
        return null;
    }

    $hours = floor($diff / 3600);
    $minutes = floor(($diff % 3600) / 60);

    if ($hours > 0) {
        return "Dans {$hours}h {$minutes}min";
    } else {
        return "Dans {$minutes}min";
    }
}

/**
 * Filtre les catégories disponibles
 *
 * @param array $categories Tableau de catégories
 * @param bool $onlyAvailable Si true, retourne seulement les catégories disponibles
 * @return array Catégories filtrées avec info de disponibilité
 */
function filterCategoriesByAvailability($categories, $onlyAvailable = false) {
    $result = [];

    foreach ($categories as $categorie) {
        $categorie['is_available'] = isCategoryAvailable($categorie);
        $categorie['availability_message'] = getCategoryAvailabilityMessage($categorie);

        if ($onlyAvailable && !$categorie['is_available']) {
            continue;
        }

        $result[] = $categorie;
    }

    return $result;
}

/**
 * Vérifie si un plat est disponible à l'heure actuelle
 * Prend en compte AUSSI les horaires de sa catégorie
 *
 * @param array $plat Tableau contenant les informations du plat
 * @param array|null $categorie (Optionnel) Informations de la catégorie du plat
 * @return bool True si disponible, False sinon
 */
function isPlatAvailable($plat, $categorie = null) {
    // Vérifier d'abord la disponibilité du plat lui-même
    if (!empty($plat['disponibilite_active']) && $plat['disponibilite_active'] == 1) {
        if (!empty($plat['heure_debut']) && !empty($plat['heure_fin'])) {
            $now = new DateTime();
            $currentTime = $now->format('H:i:s');

            if ($currentTime < $plat['heure_debut'] || $currentTime > $plat['heure_fin']) {
                return false; // Plat hors de ses horaires
            }
        }
    }

    // Ensuite vérifier la disponibilité de la catégorie si fournie
    if ($categorie !== null && !isCategoryAvailable($categorie)) {
        return false; // Catégorie fermée
    }

    return true;
}

/**
 * Récupère le message de disponibilité pour un plat
 *
 * @param array $plat Tableau contenant les informations du plat
 * @param array|null $categorie Informations de la catégorie
 * @return string Message de disponibilité
 */
function getPlatAvailabilityMessage($plat, $categorie = null) {
    // Vérifier le plat d'abord
    if (!empty($plat['disponibilite_active']) && $plat['disponibilite_active'] == 1) {
        $debut = substr($plat['heure_debut'], 0, 5);
        $fin = substr($plat['heure_fin'], 0, 5);

        $now = new DateTime();
        $currentTime = $now->format('H:i:s');

        if ($currentTime < $plat['heure_debut'] || $currentTime > $plat['heure_fin']) {
            return "Disponible de {$debut} à {$fin}";
        }
    }

    // Vérifier la catégorie ensuite
    if ($categorie !== null && !isCategoryAvailable($categorie)) {
        return getCategoryAvailabilityMessage($categorie);
    }

    return "Disponible maintenant";
}

/**
 * Filtre les plats disponibles
 *
 * @param array $plats Tableau de plats
 * @param array|null $categories_map Map des catégories (id => data)
 * @param bool $onlyAvailable Si true, retourne seulement les plats disponibles
 * @return array Plats filtrés avec info de disponibilité
 */
function filterPlatsByAvailability($plats, $categories_map = null, $onlyAvailable = false) {
    $result = [];

    foreach ($plats as $plat) {
        $categorie = null;
        if ($categories_map !== null && isset($plat['categorie_id'])) {
            $categorie = $categories_map[$plat['categorie_id']] ?? null;
        }

        $plat['is_available'] = isPlatAvailable($plat, $categorie);
        $plat['availability_message'] = getPlatAvailabilityMessage($plat, $categorie);

        if ($onlyAvailable && !$plat['is_available']) {
            continue;
        }

        $result[] = $plat;
    }

    return $result;
}
?>
