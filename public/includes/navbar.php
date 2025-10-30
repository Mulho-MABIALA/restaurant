<!DOCTYPE html>
<html lang="fr">
<meta name="google" content="notranslate">
<?php
if (!function_exists('t')) {
    require_once __DIR__ . '/language.php';
}
?>
<head>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Police Playfair Display pour correspondre au design -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'sans': ['Poppins', 'sans-serif'],
                        'playfair': ['Playfair Display', 'serif'],
                    },
                    animation: {
                        'slide-down': 'slideDown 0.3s ease-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                        'bounce-gentle': 'bounceGentle 2s infinite',
                        'glow': 'glow 2s ease-in-out infinite alternate',
                    }
                }
            }
        }
    </script>
    
    <style>
        /* ===== FONT IMPORT ===== */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&family=Playfair+Display:wght@400;500;600;700&display=swap');

        /* ===== UTILITY CLASSES ===== */
        .font-playfair {
            font-family: 'Playfair Display', serif;
        }

        html {
            scroll-behavior: smooth;
        }

        /* ===== NAVBAR ANIMATIONS ===== */
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Menu items base state */
        .nav-horizontal {
            position: relative;
        }

        /* Underline animation for nav items */
        .nav-horizontal::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 50%;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #b45309, #d97706);
            transform: translateX(-50%);
            transition: width 0.3s ease;
        }

        .nav-horizontal:hover::after {
            width: 100%;
        }

        /* Mobile dropdown animation */
        #mobile-dropdown {
            opacity: 0 !important;
            visibility: hidden !important;
            transform: translateY(-12px) scale(0.95) !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            pointer-events: none !important;
        }

        #mobile-dropdown.active {
            opacity: 1 !important;
            visibility: visible !important;
            transform: translateY(0) scale(1) !important;
            pointer-events: auto !important;
        }

        #mobile-dropdown.active .mobile-nav-link {
            animation: slideDown 0.3s ease-out forwards;
        }

        #mobile-dropdown.active .mobile-nav-link:nth-child(1) { animation-delay: 0.05s; }
        #mobile-dropdown.active .mobile-nav-link:nth-child(2) { animation-delay: 0.1s; }
        #mobile-dropdown.active .mobile-nav-link:nth-child(3) { animation-delay: 0.15s; }
        #mobile-dropdown.active .mobile-nav-link:nth-child(4) { animation-delay: 0.2s; }
        #mobile-dropdown.active .mobile-nav-link:nth-child(5) { animation-delay: 0.25s; }
        #mobile-dropdown.active .mobile-nav-link:nth-child(6) { animation-delay: 0.3s; }
        #mobile-dropdown.active .mobile-nav-link:nth-child(7) { animation-delay: 0.35s; }

        /* Underline animation for nav items */
        .nav-horizontal::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 50%;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #b45309, #d97706);
            transform: translateX(-50%);
            transition: width 0.3s ease;
        }

        .nav-horizontal:hover::after {
            width: 100%;
        }

        /* Menu toggle animation - Hamburger to X */
        #menu-toggle.active span:nth-child(1) {
            transform: rotate(45deg) translate(8px, 8px);
            background: #b45309;
        }

        #menu-toggle.active span:nth-child(2) {
            opacity: 0;
        }

        #menu-toggle.active span:nth-child(3) {
            transform: rotate(-45deg) translate(8px, -8px);
            background: #b45309;
        }

        /* Announcements scroll animation */
        @keyframes scroll-marquee {
            0% {
                transform: translateX(100%);
            }
            100% {
                transform: translateX(-100%);
            }
        }

        #announcements-scroll {
            animation: scroll-marquee 30s linear infinite;
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php 
    require_once '../admin/communication/fonctions_annonces.php';
    $nombreAnnonces = compterAnnoncesActives('site');
    ?>

    <?php if ($nombreAnnonces > 0): ?>
    <!-- Barre d'annonces compacte -->
    <div id="announcements-bar" class="fixed top-0 left-0 right-0 z-[60] bg-gradient-to-r from-amber-600 to-yellow-600 px-4 py-2 overflow-hidden border-b border-black/10 shadow-lg h-10 flex items-center">
        <div class="w-full overflow-hidden whitespace-nowrap relative h-full">
            <div id="announcements-scroll" class="inline-block whitespace-nowrap animate-scroll pr-full h-full flex items-center">
                <?php afficherAnnonces('site', 'top'); ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Header avec glassmorphism -->
    <header id="header" class="fixed left-0 right-0 z-50 backdrop-blur-xl bg-white/95 border-b border-amber-200/30 shadow-xl transition-all duration-300" style="top: <?= $nombreAnnonces > 0 ? '40px' : '0' ?>;">
        <div class="w-full mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20 lg:h-24 relative">

                <!-- Logo Section -->
                <div class="flex-shrink-0 z-10 flex-1">
                    <a href="index.php" class="flex items-center group logo-container transition-transform duration-500 hover:scale-110">
                        <div class="relative w-14 h-14 rounded-xl overflow-hidden shadow-md hover:shadow-lg transition-shadow">
                            <img src="assets/img/logo.jpg" alt="Logo Mulho" class="logo-image w-full h-full object-cover">
                        </div>
                    </a>
                </div>

                <!-- Menu Horizontal (Desktop) -->
                <nav id="horizontal-menu" class="hidden lg:flex items-center gap-8 flex-1 justify-center">
                    <a href="#hero" class="nav-horizontal text-gray-700 hover:text-amber-600 font-medium transition-all duration-300 relative text-base"><?= t('nav.home') ?></a>
                    <a href="#about" class="nav-horizontal text-gray-700 hover:text-amber-600 font-medium transition-all duration-300 relative text-base"><?= t('nav.about') ?></a>
                    <a href="cartes.php" class="nav-horizontal text-amber-700 italic font-playfair font-bold transition-all duration-300 relative text-lg tracking-wide"><?= t('nav.carte') ?></a>
                    <a href="evenements.php" class="nav-horizontal text-gray-700 hover:text-amber-600 font-medium transition-all duration-300 relative text-base"><?= t('nav.events') ?></a>
                    <a href="gallery_public.php" class="nav-horizontal text-gray-700 hover:text-amber-600 font-medium transition-all duration-300 relative text-base"><?= t('nav.gallery') ?></a>
                    <a href="#contact" class="nav-horizontal text-gray-700 hover:text-amber-600 font-medium transition-all duration-300 relative text-base"><?= t('nav.contact') ?></a>
                </nav>

                <!-- Actions Right Side -->
                <div class="flex items-center gap-3 lg:gap-4 z-10 flex-1 justify-end">
                    <!-- Language Selector -->
                    <div class="relative" id="lang-selector">
                        <button onclick="toggleLangMenu(event)" class="p-2.5 rounded-lg hover:bg-amber-100 transition-colors duration-200 flex items-center gap-1.5 border border-amber-300/40 hover:border-amber-400" title="Changer la langue">
                            <span id="current-flag" class="text-lg">🇫🇷</span>
                            <i class="fas fa-chevron-down text-xs text-amber-700"></i>
                        </button>
                        <div id="lang-menu" class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-2xl border border-gray-300 hidden overflow-hidden z-[1001]">
                            <a href="?lang=fr" class="flex items-center gap-3 px-4 py-3 hover:bg-amber-50 transition-colors border-b border-gray-200 last:border-b-0" onclick="changeLang(event, 'fr', '🇫🇷')">
                                <span class="text-lg">🇫🇷</span>
                                <span class="text-gray-700 font-medium">Français</span>
                            </a>
                            <a href="?lang=en" class="flex items-center gap-3 px-4 py-3 hover:bg-amber-50 transition-colors border-b border-gray-200 last:border-b-0" onclick="changeLang(event, 'en', '🇬🇧')">
                                <span class="text-lg">🇬🇧</span>
                                <span class="text-gray-700 font-medium">English</span>
                            </a>
                            <a href="?lang=es" class="flex items-center gap-3 px-4 py-3 hover:bg-amber-50 transition-colors border-b border-gray-200 last:border-b-0" onclick="changeLang(event, 'es', '🇪🇸')">
                                <span class="text-lg">🇪🇸</span>
                                <span class="text-gray-700 font-medium">Español</span>
                            </a>
                            <a href="?lang=wo" class="flex items-center gap-3 px-4 py-3 hover:bg-amber-50 transition-colors" onclick="changeLang(event, 'wo', '🇸🇳')">
                                <span class="text-lg">🇸🇳</span>
                                <span class="text-gray-700 font-medium">Wolof</span>
                            </a>
                        </div>
                    </div>

                    <!-- Bouton Réserver -->
                    <a href="#book-a-table" class="hidden sm:inline-flex items-center bg-gradient-to-r from-pink-500 via-pink-600 to-orange-500 text-white px-6 py-3 rounded-2xl font-bold hover:from-pink-600 hover:via-pink-700 hover:to-orange-600 active:scale-95 transition-all duration-300 text-base whitespace-nowrap shadow-lg hover:shadow-xl hover:-translate-y-1">
                        <?= t('nav.reserve') ?>
                    </a>

                    <!-- Menu Toggle Button (Mobile) -->
                    <button id="menu-toggle" class="lg:hidden flex flex-col gap-1.5 p-2.5 hover:opacity-70 transition-opacity z-[1001]" aria-label="Toggle menu">
                        <span class="block w-6 h-0.5 bg-amber-700 rounded transition-all duration-300 origin-center"></span>
                        <span class="block w-6 h-0.5 bg-amber-700 rounded transition-all duration-300"></span>
                        <span class="block w-6 h-0.5 bg-amber-700 rounded transition-all duration-300 origin-center"></span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Dropdown Menu -->
        <div id="mobile-dropdown" class="lg:hidden fixed top-20 left-4 right-4 bg-white border-2 border-gray-200 shadow-2xl rounded-2xl opacity-0 invisible transform -translate-y-3 scale-95 transition-all duration-300 z-[999]">
            <nav class="flex flex-col gap-1 p-5">
                <a href="#hero" class="mobile-nav-link text-slate-800 hover:text-amber-600 hover:bg-amber-50 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-300"><?= t('nav.home') ?></a>
                <a href="#about" class="mobile-nav-link text-slate-800 hover:text-amber-600 hover:bg-amber-50 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-300"><?= t('nav.about') ?></a>
                <a href="menu.php" class="mobile-nav-link text-slate-800 hover:text-amber-600 hover:bg-amber-50 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-300"><?= t('nav.menu') ?></a>
                <a href="cartes.php" class="mobile-nav-link text-amber-600 italic font-playfair font-semibold px-3 py-2.5 rounded-lg text-sm transition-all duration-300"><?= t('nav.carte') ?></a>
                <a href="evenements.php" class="mobile-nav-link text-slate-800 hover:text-amber-600 hover:bg-amber-50 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-300"><?= t('nav.events') ?></a>
                <a href="gallery_public.php" class="mobile-nav-link text-slate-800 hover:text-amber-600 hover:bg-amber-50 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-300"><?= t('nav.gallery') ?></a>
                <a href="#contact" class="mobile-nav-link text-slate-800 hover:text-amber-600 hover:bg-amber-50 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-300"><?= t('nav.contact') ?></a>
                <div class="border-t border-gray-200 mt-3 pt-3">
                    <a href="#book-a-table" class="block w-full bg-gradient-to-r from-pink-500 via-pink-600 to-orange-500 text-white px-4 py-2.5 rounded-lg font-semibold text-center text-sm transition-all duration-300">
                        <?= t('nav.reserve') ?>
                    </a>
                </div>
            </nav>
        </div>
    </header>

    <script>
        // JavaScript pour les fonctionnalités du navbar
        document.addEventListener('DOMContentLoaded', function() {

            // === MENU MOBILE TOGGLE ===
            const menuToggle = document.getElementById('menu-toggle');
            const mobileDropdown = document.getElementById('mobile-dropdown');

            // Vérifier que les éléments existent
            if (!menuToggle) {
                console.error('ERROR: menu-toggle button not found');
                return;
            }
            if (!mobileDropdown) {
                console.error('ERROR: mobile-dropdown not found');
                return;
            }

            function toggleMobileMenu() {
                console.log('Toggling mobile menu. Current state:', {
                    isActive: mobileDropdown.classList.contains('active'),
                    screenWidth: window.innerWidth
                });

                mobileDropdown.classList.toggle('active');
                menuToggle.classList.toggle('active');
            }

            // Click handler pour le hamburger button
            menuToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Menu toggle clicked');
                toggleMobileMenu();
            });
            
            // Fermer le menu en cliquant à l'extérieur
            document.addEventListener('click', function(e) {
                const isMenuOpen = mobileDropdown.classList.contains('active');

                if (isMenuOpen &&
                    !mobileDropdown.contains(e.target) &&
                    e.target !== menuToggle &&
                    !menuToggle.contains(e.target)) {
                    mobileDropdown.classList.remove('active');
                    menuToggle.classList.remove('active');
                    console.log('Menu closed - click outside');
                }
            });

            // Fermer le menu en cliquant sur un lien
            document.querySelectorAll('.mobile-nav-link').forEach(item => {
                item.addEventListener('click', function() {
                    mobileDropdown.classList.remove('active');
                    menuToggle.classList.remove('active');
                    console.log('Menu closed - link clicked');
                });
            });

            // Fermer avec la touche Escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && mobileDropdown.classList.contains('active')) {
                    mobileDropdown.classList.remove('active');
                    menuToggle.classList.remove('active');
                    console.log('Menu closed - Escape key');
                }
            });

            // Gérer le changement de taille d'écran
            let resizeTimer;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    // Fermer le menu lors du redimensionnement
                    if (window.innerWidth > 1024) {
                        mobileDropdown.classList.remove('active');
                        menuToggle.classList.remove('active');
                        console.log('Menu closed - window resized to desktop');
                    }
                }, 250);
            });

            // === 🔗 Scroll fluide vers les ancres ===
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    const target = document.querySelector(targetId);
                    if (target) {
                        const headerHeight = document.getElementById('header').offsetHeight;
                        const targetPosition = target.offsetTop - headerHeight;
                        
                        window.scrollTo({
                            top: targetPosition,
                            behavior: 'smooth'
                        });
                    }
                });
            });

            // === 🖼️ Gestion des logos images ===
            function handleLogoImages() {
                const logoImages = document.querySelectorAll('.logo-image');
                
                logoImages.forEach(img => {
                    img.addEventListener('error', function() {
                        const fallback = this.nextElementSibling;
                        if (fallback && fallback.classList.contains('logo-fallback')) {
                            this.style.display = 'none';
                            fallback.classList.add('show');
                        }
                    });
                    
                    img.addEventListener('load', function() {
                        const fallback = this.nextElementSibling;
                        if (fallback && fallback.classList.contains('logo-fallback')) {
                            fallback.classList.remove('show');
                        }
                    });
                });
            }

            handleLogoImages();
        });

        // === 🔧 Performance et optimisations ===
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // === 🌐 Gestion du sélecteur de langue ===
        function toggleLangMenu(event) {
            event.stopPropagation();
            const langMenu = document.getElementById('lang-menu');
            langMenu.classList.toggle('hidden');
        }

        function changeLang(event, lang, flag) {
            event.preventDefault();
            document.getElementById('current-flag').textContent = flag;
            localStorage.setItem('selectedLang', lang);
            localStorage.setItem('selectedFlag', flag);
            window.location.href = '?lang=' + lang;
        }

        // Charger la langue sauvegardée
        document.addEventListener('DOMContentLoaded', function() {
            // Mapper les langues aux drapeaux
            const langFlags = {
                'fr': '🇫🇷',
                'en': '🇬🇧',
                'es': '🇪🇸',
                'wo': '🇸🇳'
            };

            // Obtenir la langue depuis l'URL ou localStorage
            const urlParams = new URLSearchParams(window.location.search);
            const langFromUrl = urlParams.get('lang');
            const savedLang = langFromUrl || localStorage.getItem('selectedLang') || 'fr';
            const currentFlag = document.getElementById('current-flag');

            if (currentFlag) {
                currentFlag.textContent = langFlags[savedLang] || '🇫🇷';
            }

            // Sauvegarder dans localStorage
            if (langFromUrl) {
                localStorage.setItem('selectedLang', langFromUrl);
                localStorage.setItem('selectedFlag', langFlags[langFromUrl]);
            }

            // Fermer le menu de langue en cliquant à l'extérieur
            document.addEventListener('click', function(e) {
                const langSelector = document.getElementById('lang-selector');
                const langMenu = document.getElementById('lang-menu');
                if (langSelector && langMenu && !langSelector.contains(e.target)) {
                    langMenu.classList.add('hidden');
                }
            });
        });
    </script>

</body>
</html>