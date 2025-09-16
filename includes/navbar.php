<!DOCTYPE html>
<html lang="fr">
<meta name="google" content="notranslate">   
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
        /* Styles existants conservés */
        .annonces-section {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .menu-annonces-section {
            margin-bottom: 30px;
        }

        .annonce-banner.urgent {
            border-left: 4px solid #dc3545 !important;
            background-color: #dc354520 !important;
            animation: pulse 2s infinite;
        }

        .annonce-banner.urgent .annonce-titre {
            color: #dc3545 !important;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }

        @media (max-width: 576px) {
            .annonces-container {
                margin: 10px -15px 20px -15px;
            }
            
            .annonce-banner {
                border-radius: 0;
                margin-bottom: 5px;
            }
        }

        .menu-sidebar .annonces-container {
            position: sticky;
            top: 20px;
        }

        .menu-sidebar .annonce-banner {
            margin-bottom: 15px;
            font-size: 0.85em;
        }

        .annonce-banner[data-type="promo"] .annonce-titre::before {
            content: "🎉 ";
        }

        .annonce-banner[data-type="fermeture"] .annonce-titre::before {
            content: "⚠️ ";
        }

        .annonce-banner[data-type="nouveau"] .annonce-titre::before {
            content: "✨ ";
        }

        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap');
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes bounceGentle {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        
        @keyframes glow {
            from { box-shadow: 0 0 20px rgba(236, 72, 153, 0.3); }
            to { box-shadow: 0 0 30px rgba(236, 72, 153, 0.6); }
        }

        .glass-effect {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.95);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 1023px) {
            .glass-effect {
                backdrop-filter: none;
                background: #ffffff;
                border-bottom: 1px solid rgba(0, 0, 0, 0.1);
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            }
        }

        .gradient-text {
            background: linear-gradient(135deg, #ec4899, #f97316, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-item {
            position: relative;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .nav-item::before {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -6px;
            left: 50%;
            background: linear-gradient(90deg, #ec4899, #f97316);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            transform: translateX(-50%);
            border-radius: 1px;
        }

        .nav-item:hover::before,
        .nav-item.active::before {
            width: 100%;
        }

        .nav-item:hover {
            transform: translateY(-1px);
        }

        .mobile-menu {
            transform: translateX(100%);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .mobile-menu.open {
            transform: translateX(0);
        }

        .btn-primary {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(236, 72, 153, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(236, 72, 153, 0.4);
        }

        .mobile-nav-item {
            transform: translateX(20px);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .mobile-menu.open .mobile-nav-item {
            transform: translateX(0);
            opacity: 1;
        }

        .mobile-nav-item:nth-child(1) { transition-delay: 0.1s; }
        .mobile-nav-item:nth-child(2) { transition-delay: 0.15s; }
        .mobile-nav-item:nth-child(3) { transition-delay: 0.2s; }
        .mobile-nav-item:nth-child(4) { transition-delay: 0.25s; }
        .mobile-nav-item:nth-child(5) { transition-delay: 0.3s; }
        .mobile-nav-item:nth-child(6) { transition-delay: 0.35s; }

        .logo-image {
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .logo-container:hover .logo-image {
            transform: scale(1.05);
        }

        .logo-fallback {
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, #ec4899, #f59e0b, #3b82f6);
            border-radius: inherit;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .logo-fallback.show {
            opacity: 1;
        }

        .hamburger-line {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .hamburger-active .hamburger-line:nth-child(1) {
            transform: rotate(45deg) translate(6px, 6px);
        }

        .hamburger-active .hamburger-line:nth-child(2) {
            opacity: 0;
        }

        .hamburger-active .hamburger-line:nth-child(3) {
            transform: rotate(-45deg) translate(6px, -6px);
        }

        @media (max-width: 640px) {
            .mobile-menu {
                width: 100vw;
            }
        }

        html {
            scroll-behavior: smooth;
        }

        .demo-section {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Styles pour le défilement des annonces */
        .site-annonces-section {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 60;
            background: linear-gradient(135deg, #d4a574, #c19654);
            padding: 8px 0;
            overflow: hidden;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: 40px;
            display: flex;
            align-items: center;
        }

        .annonces-container {
            width: 100%;
            overflow: hidden;
            white-space: nowrap;
            position: relative;
            height: 100%;
        }

        .annonce-wrapper {
            display: inline-block;
            white-space: nowrap;
            animation: defilement-annonces 30s linear infinite;
            padding-right: 100%;
            height: 100%;
            display: flex;
            align-items: center;
        }

        .site-annonces-section .annonce {
            display: inline-flex;
            align-items: center;
            padding: 0 20px;
            margin: 0 20px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            color: #2c3e50;
            text-align: center;
            white-space: nowrap;
            height: 28px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            font-size: 14px;
            font-weight: 500;
        }

        .site-annonces-section .annonce strong {
            display: inline;
            font-size: 14px;
            color: #c19654;
            font-weight: 700;
            margin-right: 6px;
        }

        .site-annonces-section .annonce-message {
            display: inline;
            font-size: 14px;
        }

        @keyframes defilement-annonces {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }

        @media (max-width: 1023px) {
            .site-annonces-section {
                height: 36px;
                padding: 6px 0;
            }
            
            .glass-effect {
                top: 36px !important;
            }
            
            .site-annonces-section .annonce {
                height: 26px;
                padding: 0 15px;
                font-size: 13px;
            }
        }

        /* === NOUVEAUX STYLES POUR LE MENU HORIZONTAL === */
        
        /* Container du menu horizontal */
        .horizontal-menu-container {
            position: absolute;
            left: 0;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            justify-content: center;
            align-items: center;
            flex: 1;
            padding: 0 20px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .horizontal-menu-container.active {
            opacity: 1;
            visibility: visible;
        }

        /* Navigation horizontale */
        .horizontal-nav {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .horizontal-nav-item {
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            color: #2c3e50;
            text-decoration: none;
            position: relative;
            transition: all 0.3s ease;
            padding: 5px 10px;
            white-space: nowrap;
            opacity: 0;
            transform: translateY(-10px);
        }

        .horizontal-menu-container.active .horizontal-nav-item {
            opacity: 1;
            transform: translateY(0);
        }

        .horizontal-nav-item:nth-child(1) { transition-delay: 0.05s; }
        .horizontal-nav-item:nth-child(2) { transition-delay: 0.1s; }
        .horizontal-nav-item:nth-child(3) { transition-delay: 0.15s; }
        .horizontal-nav-item:nth-child(4) { transition-delay: 0.2s; }
        .horizontal-nav-item:nth-child(5) { transition-delay: 0.25s; }
        .horizontal-nav-item:nth-child(6) { transition-delay: 0.3s; }
        .horizontal-nav-item:nth-child(7) { transition-delay: 0.35s; }

        .horizontal-nav-item:hover {
            color: #c19654;
            transform: translateY(-2px);
        }

        .horizontal-nav-item::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 50%;
            background: linear-gradient(90deg, #c19654, #d4a574);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .horizontal-nav-item:hover::after {
            width: 100%;
        }

        .la-carte-text {
            font-family: 'Playfair Display', serif;
            font-style: italic;
            color: #c19654;
            font-weight: 600;
        }

        /* Menu toggle button */
        .menu-toggle-btn {
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            transition: all 0.3s ease;
            z-index: 1001;
            position: relative;
            margin-left: 10px;
        }

        .menu-toggle-btn span {
            display: block;
            width: 22px;
            height: 2px;
            background: #d4a574;
            transition: all 0.3s ease;
        }

        .menu-toggle-btn.active span:nth-child(1) {
            transform: rotate(45deg) translate(5px, 5px);
            background: #c19654;
        }

        .menu-toggle-btn.active span:nth-child(2) {
            opacity: 0;
        }

        .menu-toggle-btn.active span:nth-child(3) {
            transform: rotate(-45deg) translate(5px, -5px);
            background: #c19654;
        }

        /* Responsive - Mobile */
        @media (max-width: 768px) {
            .horizontal-menu-container {
                display: none;
            }
            
            /* Menu mobile dropdown */
            .mobile-dropdown-menu {
                position: absolute;
                top: 100%;
                right: 0;
                width: 280px;
                background: rgba(255, 255, 255, 0.98);
                backdrop-filter: blur(20px);
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
                z-index: 1000;
                padding: 20px;
                margin-top: 10px;
                opacity: 0;
                visibility: hidden;
                transform: translateY(-10px);
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                border: 1px solid rgba(255, 255, 255, 0.2);
            }

            .mobile-dropdown-menu.active {
                opacity: 1;
                visibility: visible;
                transform: translateY(0);
            }

            .mobile-nav-items {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .mobile-nav-link {
                font-family: 'Poppins', sans-serif;
                font-size: 1rem;
                color: #2c3e50;
                text-decoration: none;
                position: relative;
                transition: all 0.2s ease;
                padding: 8px 12px;
                border-radius: 6px;
            }

            .mobile-nav-link:hover {
                background-color: rgba(212, 165, 116, 0.1);
                color: #c19654;
                transform: translateX(5px);
            }
        }

        /* Ajustements pour grands écrans */
        @media (min-width: 1024px) {
            .mobile-dropdown-menu {
                display: none !important;
            }
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php 
    require_once 'admin/communication/fonctions_annonces.php';
    $nombreAnnonces = compterAnnoncesActives('site');
    ?>

    <?php if ($nombreAnnonces > 0): ?>
    <!-- Barre d'annonces compacte -->
    <div class="site-annonces-section">
        <div class="annonces-container">
            <div class="annonce-wrapper">
                <?php afficherAnnonces('site', 'top'); ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Header avec effet glassmorphism -->
    <header id="header" class="glass-effect fixed top-[40px] left-0 right-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 sm:h-18 lg:h-20 relative">
                
                <!-- Logo Section -->
                <div class="flex-shrink-0 z-10">
                    <a href="index.php" class="flex items-center space-x-2 group logo-container">
                        <div class="relative">
                            <div style="width:70px; height:70px; border-radius:12px; overflow:hidden; box-shadow:0 2px 6px rgba(0,0,0,0.2);">
                                <img src="assets/img/logo.jpg" alt="Logo Mulho" class="logo-image" style="width:100%; height:100%; object-fit:cover; border-radius:100px;">
                            </div>
                        </div>
                    </a>
                </div>
                
                <!-- Menu Horizontal (Desktop) -->
                <div class="horizontal-menu-container" id="horizontal-menu">
                    <nav class="horizontal-nav">
                        <a href="#hero" class="horizontal-nav-item">Accueil</a>
                        <a href="#about" class="horizontal-nav-item">A propos</a>
                        <a href="cartes.php" class="horizontal-nav-item la-carte-text">La Carte</a>
                        <a href="evenements.php" class="horizontal-nav-item">Evénements</a>
                        <a href="gallery_public.php" class="horizontal-nav-item">Galerie!</a>
                        <a href="#contact" class="horizontal-nav-item">Contact</a>
                    </nav>
                </div>
                
                <!-- Actions Desktop -->
                <div class="flex items-center space-x-3 z-10">
                    <!-- Bouton Réserver -->
                    <a href="#book-a-table" class="btn-primary bg-gradient-to-r from-pink-500 via-pink-600 to-orange-500 text-white px-4 py-2 rounded-xl font-semibold hover:from-pink-600 hover:via-pink-700 hover:to-orange-600 transition-all duration-300 text-sm whitespace-nowrap shadow-lg hover:shadow-xl">
                        <span>Réserver</span>
                    </a>

                    <!-- Menu Toggle Button -->
                    <button id="menu-toggle" class="menu-toggle-btn">
                        <span></span>
                        <span></span>
                        <span></span>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Mobile Dropdown Menu -->
        <div id="mobile-dropdown" class="mobile-dropdown-menu lg:hidden">
            <nav class="mobile-nav-items">
                <a href="#hero" class="mobile-nav-link">Accueil</a>
                <a href="#about" class="mobile-nav-link">A propos</a>
                <a href="menu.php" class="mobile-nav-link">Menu</a>
                <a href="carte.php" class="mobile-nav-link la-carte-text">La Carte</a>
                <a href="evenements.php" class="mobile-nav-link">Evénements</a>
                <a href="gallery_public.php" class="mobile-nav-link">Galerie!</a>
                <a href="#contact" class="mobile-nav-link">Contact</a>
            </nav>
        </div>
    </header>

    <script>
        // JavaScript pour les fonctionnalités du navbar
        document.addEventListener('DOMContentLoaded', function() {
            
            // === MENU HORIZONTAL TOGGLE ===
            const menuToggle = document.getElementById('menu-toggle');
            const horizontalMenu = document.getElementById('horizontal-menu');
            const mobileDropdown = document.getElementById('mobile-dropdown');
            
            function toggleMenu() {
                const isMobile = window.innerWidth <= 768;
                
                if (isMobile) {
                    // Mobile: toggle dropdown
                    mobileDropdown.classList.toggle('active');
                } else {
                    // Desktop: toggle horizontal menu
                    horizontalMenu.classList.toggle('active');
                }
                
                menuToggle.classList.toggle('active');
            }
            
            menuToggle?.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleMenu();
            });
            
            // Fermer le menu en cliquant à l'extérieur
            document.addEventListener('click', function(e) {
                const isMenuOpen = horizontalMenu.classList.contains('active') || mobileDropdown.classList.contains('active');
                
                if (isMenuOpen && 
                    !horizontalMenu.contains(e.target) && 
                    !mobileDropdown.contains(e.target) &&
                    e.target !== menuToggle && 
                    !menuToggle.contains(e.target)) {
                    horizontalMenu.classList.remove('active');
                    mobileDropdown.classList.remove('active');
                    menuToggle.classList.remove('active');
                }
            });
            
            // Fermer le menu en cliquant sur un lien
            document.querySelectorAll('.horizontal-nav-item, .mobile-nav-link').forEach(item => {
                item.addEventListener('click', function() {
                    horizontalMenu.classList.remove('active');
                    mobileDropdown.classList.remove('active');
                    menuToggle.classList.remove('active');
                });
            });
            
            // Fermer avec la touche Escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && (horizontalMenu.classList.contains('active') || mobileDropdown.classList.contains('active'))) {
                    horizontalMenu.classList.remove('active');
                    mobileDropdown.classList.remove('active');
                    menuToggle.classList.remove('active');
                }
            });
            
            // Gérer le changement de taille d'écran
            let resizeTimer;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    // Fermer tous les menus lors du redimensionnement
                    horizontalMenu.classList.remove('active');
                    mobileDropdown.classList.remove('active');
                    menuToggle.classList.remove('active');
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
    </script>

</body>
</html>