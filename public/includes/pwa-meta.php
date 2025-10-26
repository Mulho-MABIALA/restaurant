<?php
/**
 * Meta tags et scripts PWA
 * À inclure dans le <head> de toutes les pages
 */
?>

<!-- PWA Manifest -->
<link rel="manifest" href="/restaurant/public/manifest.php">

<!-- Theme Color -->
<meta name="theme-color" content="#10b981">
<meta name="msapplication-TileColor" content="#10b981">

<!-- Apple iOS Meta Tags -->
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?php echo htmlspecialchars(getSetting('restaurant_name', 'Restaurant Mulho')); ?>">
<link rel="apple-touch-icon" href="/restaurant/public/assets/img/icons/icon-152x152.png">
<link rel="apple-touch-icon" sizes="72x72" href="/restaurant/public/assets/img/icons/icon-72x72.png">
<link rel="apple-touch-icon" sizes="96x96" href="/restaurant/public/assets/img/icons/icon-96x96.png">
<link rel="apple-touch-icon" sizes="128x128" href="/restaurant/public/assets/img/icons/icon-128x128.png">
<link rel="apple-touch-icon" sizes="144x144" href="/restaurant/public/assets/img/icons/icon-144x144.png">
<link rel="apple-touch-icon" sizes="152x152" href="/restaurant/public/assets/img/icons/icon-152x152.png">
<link rel="apple-touch-icon" sizes="192x192" href="/restaurant/public/assets/img/icons/icon-192x192.png">
<link rel="apple-touch-icon" sizes="384x384" href="/restaurant/public/assets/img/icons/icon-384x384.png">
<link rel="apple-touch-icon" sizes="512x512" href="/restaurant/public/assets/img/icons/icon-512x512.png">

<!-- Apple Splash Screens (optionnel mais recommandé) -->
<link rel="apple-touch-startup-image" href="/public/assets/img/splash/iphone5_splash.png" media="(device-width: 320px) and (device-height: 568px) and (-webkit-device-pixel-ratio: 2)">
<link rel="apple-touch-startup-image" href="/public/assets/img/splash/iphone6_splash.png" media="(device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2)">
<link rel="apple-touch-startup-image" href="/public/assets/img/splash/iphoneplus_splash.png" media="(device-width: 621px) and (device-height: 1104px) and (-webkit-device-pixel-ratio: 3)">
<link rel="apple-touch-startup-image" href="/public/assets/img/splash/iphonex_splash.png" media="(device-width: 375px) and (device-height: 812px) and (-webkit-device-pixel-ratio: 3)">
<link rel="apple-touch-startup-image" href="/public/assets/img/splash/iphonexr_splash.png" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 2)">
<link rel="apple-touch-startup-image" href="/public/assets/img/splash/iphonexsmax_splash.png" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 3)">
<link rel="apple-touch-startup-image" href="/public/assets/img/splash/ipad_splash.png" media="(device-width: 768px) and (device-height: 1024px) and (-webkit-device-pixel-ratio: 2)">
<link rel="apple-touch-startup-image" href="/public/assets/img/splash/ipadpro1_splash.png" media="(device-width: 834px) and (device-height: 1112px) and (-webkit-device-pixel-ratio: 2)">
<link rel="apple-touch-startup-image" href="/public/assets/img/splash/ipadpro2_splash.png" media="(device-width: 1024px) and (device-height: 1366px) and (-webkit-device-pixel-ratio: 2)">

<!-- Microsoft Windows Meta Tags -->
<meta name="msapplication-TileImage" content="/restaurant/public/assets/img/icons/icon-144x144.png">
<meta name="msapplication-config" content="/restaurant/public/browserconfig.xml">

<!-- Standard Favicon -->
<link rel="icon" type="image/png" sizes="32x32" href="/restaurant/public/assets/img/icons/icon-72x72.png">
<link rel="icon" type="image/png" sizes="16x16" href="/restaurant/public/assets/img/icons/icon-72x72.png">
<link rel="shortcut icon" href="/restaurant/public/assets/img/icons/icon-72x72.png">

<!-- Mobile Viewport -->
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
<meta name="mobile-web-app-capable" content="yes">

<!-- PWA Description -->
<meta name="description" content="Commandez vos plats préférés du Restaurant Mulho. Livraison rapide, paiement sécurisé, mode hors ligne.">
<meta name="keywords" content="restaurant, commande en ligne, livraison, <?php echo isset($pageKeywords) ? $pageKeywords : ''; ?>">

<!-- Open Graph / Facebook -->
<meta property="og:type" content="website">
<meta property="og:url" content="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>">
<meta property="og:title" content="<?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo htmlspecialchars(getSetting('restaurant_name', 'Restaurant Mulho')); ?>">
<meta property="og:description" content="Commandez vos plats préférés. Livraison rapide, paiement sécurisé.">
<meta property="og:image" content="/restaurant/public/assets/img/icons/icon-512x512.png">

<!-- Twitter -->
<meta property="twitter:card" content="summary_large_image">
<meta property="twitter:url" content="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>">
<meta property="twitter:title" content="<?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo htmlspecialchars(getSetting('restaurant_name', 'Restaurant Mulho')); ?>">
<meta property="twitter:description" content="Commandez vos plats préférés. Livraison rapide, paiement sécurisé.">
<meta property="twitter:image" content="/restaurant/public/assets/img/icons/icon-512x512.png">

<!-- Preload Critical Resources -->
<link rel="preload" href="/restaurant/public/assets/js/pwa-init.js" as="script">
<link rel="preload" href="/restaurant/public/assets/css/main.css" as="style">

<!-- DNS Prefetch pour les domaines externes -->
<link rel="dns-prefetch" href="//cdn.jsdelivr.net">
<link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
<link rel="dns-prefetch" href="//fonts.googleapis.com">

<?php
/**
 * Détecter si PWA est installée et ajuster l'UI
 */
?>
<script>
// Détecter le mode standalone (app installée)
if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
    document.documentElement.classList.add('pwa-installed', 'standalone-mode');

    // Sauvegarder le statut
    localStorage.setItem('pwa_installed', 'true');

    // Masquer les éléments "Installer l'app" si présents
    document.addEventListener('DOMContentLoaded', function() {
        const installElements = document.querySelectorAll('[data-hide-if-installed]');
        installElements.forEach(el => el.style.display = 'none');
    });
}

// Ajouter classe pour iOS
if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
    document.documentElement.classList.add('ios-device');
}

// Ajouter classe pour Android
if (/Android/.test(navigator.userAgent)) {
    document.documentElement.classList.add('android-device');
}
</script>

<!-- Styles PWA additionnels -->
<style>
    /* Masquer la barre d'adresse en mode standalone */
    .standalone-mode body {
        padding-top: env(safe-area-inset-top);
        padding-bottom: env(safe-area-inset-bottom);
    }

    /* iOS safe area */
    .ios-standalone {
        padding-top: constant(safe-area-inset-top);
        padding-top: env(safe-area-inset-top);
    }

    /* Indicateur online/offline */
    .is-offline .online-only {
        display: none !important;
    }

    .is-online .offline-only {
        display: none !important;
    }

    /* Badge offline */
    .is-offline::before {
        content: '⚠️ Mode hors ligne';
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        background: #f59e0b;
        color: white;
        text-align: center;
        padding: 8px;
        font-size: 14px;
        font-weight: 600;
        z-index: 9999;
    }
</style>

<!-- PWA Scripts -->
<script src="/restaurant/public/assets/js/offline-storage.js" defer></script>
<script src="/restaurant/public/assets/js/pwa-init.js" defer></script>
<script src="/restaurant/public/assets/js/pwa-install.js" defer></script>
