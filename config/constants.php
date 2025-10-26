<?php
/**
 * Configuration centralisée des constantes
 * Évite les "magic numbers" et centralise la configuration
 */

// ============================================
// CONFIGURATION GÉNÉRALE
// ============================================

// Mode debug (désactiver en production!)
define('DEBUG_MODE', false);

// Informations du restaurant
define('RESTAURANT_NAME', 'Restaurant Mulho');
define('RESTAURANT_EMAIL', 'contact@restaurantmulho.com');
define('RESTAURANT_PHONE', '+221 XX XXX XX XX');
define('RESTAURANT_ADDRESS', 'Dakar, Sénégal');

// ============================================
// GÉOLOCALISATION
// ============================================

// Coordonnées GPS du restaurant
define('RESTAURANT_LATITUDE', 14.6806968);
define('RESTAURANT_LONGITUDE', -17.4480072);

// Rayon de livraison en mètres
define('GEOFENCE_RADIUS_METERS', 150);

// Message d'erreur géolocalisation
define('GEOFENCE_ERROR_MESSAGE', 'Vous êtes trop loin du restaurant pour commander');

// ============================================
// LIMITES ET QUOTAS
// ============================================

// Capacité maximale du restaurant
define('MAX_RESTAURANT_CAPACITY', 50);

// Taille maximale d'un groupe pour réservation
define('MAX_PARTY_SIZE', 20);
define('MIN_PARTY_SIZE', 1);

// Nombre maximum d'items dans le panier
define('MAX_CART_ITEMS', 50);

// Montant minimum de commande (FCFA)
define('MIN_ORDER_AMOUNT', 1000);

// Montant maximum de commande (FCFA)
define('MAX_ORDER_AMOUNT', 500000);

// ============================================
// PAGINATION
// ============================================

// Nombre d'items par page (admin)
define('ITEMS_PER_PAGE_ADMIN', 10);

// Nombre d'items par page (public)
define('ITEMS_PER_PAGE_PUBLIC', 12);

// Nombre maximum de pages à afficher dans la pagination
define('MAX_PAGINATION_LINKS', 5);

// ============================================
// SESSION ET SÉCURITÉ
// ============================================

// Durée de vie de la session (secondes)
define('SESSION_LIFETIME_SECONDS', 3600); // 1 heure

// Timeout d'inactivité (secondes)
define('SESSION_TIMEOUT_SECONDS', 1800); // 30 minutes

// Durée de validité du code 2FA (secondes)
define('TWO_FACTOR_CODE_EXPIRY', 300); // 5 minutes

// Nombre maximum de tentatives de connexion
define('MAX_LOGIN_ATTEMPTS', 5);

// Durée de blocage après tentatives échouées (secondes)
define('LOGIN_LOCKOUT_DURATION', 900); // 15 minutes

// Nombre maximum de tentatives de code 2FA
define('MAX_2FA_ATTEMPTS', 3);

// Durée de validité du token de réinitialisation de mot de passe (secondes)
define('PASSWORD_RESET_TOKEN_EXPIRY', 3600); // 1 heure

// ============================================
// UPLOADS DE FICHIERS
// ============================================

// Taille maximale d'upload d'image (octets)
define('MAX_IMAGE_UPLOAD_SIZE', 2097152); // 2MB

// Extensions d'images autorisées
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Types MIME autorisés
define('ALLOWED_IMAGE_MIME_TYPES', [
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/gif',
    'image/webp'
]);

// Dimensions maximales des images
define('MAX_IMAGE_WIDTH', 5000);
define('MAX_IMAGE_HEIGHT', 5000);

// Dimensions pour le redimensionnement automatique
define('IMAGE_RESIZE_MAX_DIMENSION', 1920);

// Qualité JPEG après optimisation (0-100)
define('JPEG_QUALITY', 85);

// Niveau de compression PNG (0-9)
define('PNG_COMPRESSION', 8);

// ============================================
// COMMANDES
// ============================================

// Statuts de commande possibles
define('ORDER_STATUS_PENDING', 'En attente');
define('ORDER_STATUS_CONFIRMED', 'Confirmée');
define('ORDER_STATUS_PREPARING', 'En préparation');
define('ORDER_STATUS_READY', 'Prêt');
define('ORDER_STATUS_DELIVERED', 'Livré');
define('ORDER_STATUS_CANCELLED', 'Annulé');

// Timeout pour confirmer une commande (minutes)
define('ORDER_CONFIRMATION_TIMEOUT', 30);

// Délai de préparation estimé (minutes)
define('PREPARATION_TIME_MINUTES', 30);

// ============================================
// RÉSERVATIONS
// ============================================

// Statuts de réservation possibles
define('RESERVATION_STATUS_PENDING', 'En attente');
define('RESERVATION_STATUS_CONFIRMED', 'Confirmée');
define('RESERVATION_STATUS_CANCELLED', 'Annulée');
define('RESERVATION_STATUS_COMPLETED', 'Terminée');
define('RESERVATION_STATUS_NO_SHOW', 'Non présenté');

// Nombre de jours à l'avance pour réserver
define('MAX_RESERVATION_DAYS_ADVANCE', 30);
define('MIN_RESERVATION_DAYS_ADVANCE', 0);

// Heures d'ouverture pour les réservations
define('RESERVATION_OPEN_HOUR', 11); // 11h
define('RESERVATION_CLOSE_HOUR', 22); // 22h

// ============================================
// EMAIL
// ============================================

// Nombre maximum d'emails par heure (anti-spam)
define('MAX_EMAILS_PER_HOUR', 50);

// Délai entre les emails (secondes)
define('EMAIL_THROTTLE_SECONDS', 10);

// Email par défaut pour les notifications admin
define('DEFAULT_ADMIN_EMAIL', 'admin@restaurantmulho.com');

// ============================================
// NOTIFICATIONS
// ============================================

// Durée d'affichage des toasts (millisecondes)
define('TOAST_DISPLAY_DURATION', 3000);

// Types de notifications
define('NOTIFICATION_SUCCESS', 'success');
define('NOTIFICATION_ERROR', 'error');
define('NOTIFICATION_WARNING', 'warning');
define('NOTIFICATION_INFO', 'info');

// ============================================
// CACHE ET PERFORMANCE
// ============================================

// Durée du cache des menus (secondes)
define('MENU_CACHE_DURATION', 300); // 5 minutes

// Durée du cache des catégories (secondes)
define('CATEGORY_CACHE_DURATION', 600); // 10 minutes

// Activer la compression de sortie
define('ENABLE_OUTPUT_COMPRESSION', true);

// ============================================
// LOGS
// ============================================

// Durée de conservation des logs (jours)
define('LOG_RETENTION_DAYS', 30);

// Niveau de log (debug, info, warning, error)
define('LOG_LEVEL', DEBUG_MODE ? 'debug' : 'error');

// Taille maximale du fichier de log (octets)
define('MAX_LOG_FILE_SIZE', 10485760); // 10MB

// ============================================
// RATE LIMITING
// ============================================

// Nombre maximum de requêtes par minute (API)
define('API_RATE_LIMIT_PER_MINUTE', 60);

// Nombre maximum de soumissions de formulaire par heure
define('FORM_SUBMISSION_LIMIT_PER_HOUR', 10);

// ============================================
// DEVISE
// ============================================

// Symbole de la devise
define('CURRENCY_SYMBOL', 'FCFA');

// Position du symbole (before/after)
define('CURRENCY_SYMBOL_POSITION', 'after');

// Séparateur de milliers
define('THOUSAND_SEPARATOR', ' ');

// Séparateur de décimales
define('DECIMAL_SEPARATOR', ',');

// Nombre de décimales
define('DECIMAL_PLACES', 0);

// ============================================
// DATES ET HEURES
// ============================================

// Fuseau horaire
define('TIMEZONE', 'Africa/Dakar');

// Format de date par défaut
define('DATE_FORMAT', 'd/m/Y');

// Format de date et heure
define('DATETIME_FORMAT', 'd/m/Y H:i');

// Format d'heure
define('TIME_FORMAT', 'H:i');

// ============================================
// LANGUES
// ============================================

// Langue par défaut
define('DEFAULT_LANGUAGE', 'fr');

// Langues disponibles
define('AVAILABLE_LANGUAGES', ['fr', 'en']);

// ============================================
// PERMISSIONS
// ============================================

// Codes de permissions
define('PERMISSION_VIEW_DASHBOARD', 'view_dashboard');
define('PERMISSION_MANAGE_ORDERS', 'manage_orders');
define('PERMISSION_MANAGE_RESERVATIONS', 'manage_reservations');
define('PERMISSION_MANAGE_MENU', 'manage_menu');
define('PERMISSION_MANAGE_CATEGORIES', 'manage_categories');
define('PERMISSION_MANAGE_USERS', 'manage_users');
define('PERMISSION_MANAGE_SETTINGS', 'manage_settings');
define('PERMISSION_VIEW_REPORTS', 'view_reports');
define('PERMISSION_MANAGE_ANNOUNCEMENTS', 'manage_announcements');

// ============================================
// CHEMINS
// ============================================

// Chemin racine du projet
define('ROOT_PATH', dirname(__DIR__));

// Chemin des uploads
define('UPLOAD_PATH', ROOT_PATH . '/uploads/');

// Chemin des logs
define('LOG_PATH', ROOT_PATH . '/logs/');

// Chemin du cache
define('CACHE_PATH', ROOT_PATH . '/cache/');

// ============================================
// URLs
// ============================================

// URL de base du site
define('BASE_URL', 'http://localhost/restaurant');

// URL des uploads
define('UPLOAD_URL', BASE_URL . '/uploads/');

// URL admin
define('ADMIN_URL', BASE_URL . '/admin/');

// URL publique
define('PUBLIC_URL', BASE_URL . '/public/');

// ============================================
// FONCTIONS UTILITAIRES
// ============================================

/**
 * Formate un montant avec la devise
 *
 * @param float $amount
 * @return string
 */
function formatCurrency($amount) {
    $formatted = number_format(
        $amount,
        DECIMAL_PLACES,
        DECIMAL_SEPARATOR,
        THOUSAND_SEPARATOR
    );

    if (CURRENCY_SYMBOL_POSITION === 'before') {
        return CURRENCY_SYMBOL . ' ' . $formatted;
    }

    return $formatted . ' ' . CURRENCY_SYMBOL;
}

/**
 * Formate une date
 *
 * @param string|int $date
 * @param string $format
 * @return string
 */
function formatDate($date, $format = DATE_FORMAT) {
    if (is_numeric($date)) {
        return date($format, $date);
    }

    return date($format, strtotime($date));
}

/**
 * Vérifie si on est en mode debug
 *
 * @return bool
 */
function isDebugMode() {
    return DEBUG_MODE === true;
}

/**
 * Log un message
 *
 * @param string $message
 * @param string $level
 */
function logMessage($message, $level = 'info') {
    $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
    $current_level = $levels[LOG_LEVEL] ?? 1;
    $message_level = $levels[$level] ?? 1;

    if ($message_level < $current_level) {
        return;
    }

    $log_file = LOG_PATH . 'app.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] [{$level}] {$message}\n";

    if (!is_dir(LOG_PATH)) {
        mkdir(LOG_PATH, 0755, true);
    }

    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Définir le fuseau horaire
date_default_timezone_set(TIMEZONE);
