<?php
/**
 * Mapping des fichiers PHP vers leurs slugs de permissions
 * Utilisé pour standardiser les permissions sur toutes les pages
 */

return [
    // Pages principales de gestion
    'dashboard.php' => 'dashboard',
    'gestion_plats.php' => 'gestion_plats',
    'categories_plats.php' => 'gestion_categories',
    'gestion_stock.php' => 'gestion_stocks',
    'ajouter_stock.php' => 'gestion_stocks',
    'reservations.php' => 'reservations',
    'commandes.php' => 'commandes',
    'voir_commande.php' => 'commandes',

    // RH et Employés
    'gestion_employe.php' => 'gestion_employes',
    'gestion_postes.php' => 'gestion_postes',
    'gestion_paie.php' => 'gestion_paie',
    'badgeuse.php' => 'badgeuse',
    'presence.php' => 'presence',
    'planification_horaires.php' => 'planification_horaires',
    'horaires.php' => 'horaires',
    'generer_bulletin.php' => 'gestion_paie',

    // Administration
    'admin_gestion.php' => 'admin_gestion',
    'ajouter_admin.php' => 'admin_gestion',
    'admin_supprimer.php' => 'admin_gestion',
    'gestion_droits.php' => 'gestion_droits',

    // Newsletter
    'admin_newsletter.php' => 'admin_newsletter',
    'admin_newsletter_compose.php' => 'admin_newsletter',
    'admin_newsletter_campaigns.php' => 'admin_newsletter',
    'admin_newsletter_analytics.php' => 'admin_newsletter',
    'admin_newsletter_templates.php' => 'admin_newsletter',

    // Contenu
    'gallery.php' => 'gallery',
    'admin_evenements.php' => 'admin_evenements',
    'gestion_contenu.php' => 'gestion_contenu',
    'avis_admin.php' => 'avis_admin',

    // Statistiques et rapports
    'statistiques.php' => 'statistiques',
    'stats.php' => 'statistiques',

    // Configuration
    'settings.php' => 'settings',
    'config_security.php' => 'config_security',

    // Profil
    'profile.php' => 'profile',

    // Pages qui ne nécessitent PAS de permissions spécifiques (accès libre pour tous les admins)
    'notifications.php' => null,
    'logout.php' => null,
    'access_denied.php' => null,
];
