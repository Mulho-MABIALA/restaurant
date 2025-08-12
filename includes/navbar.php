<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mulho - Navbar Moderne</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'sans': ['Inter', 'system-ui', 'sans-serif'],
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
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        /* Animations personnalisées */
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

        /* Glassmorphism effet */
        .glass-effect {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.95);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        /* Mobile - navbar non transparent */
        @media (max-width: 1023px) {
            .glass-effect {
                backdrop-filter: none;
                background: #ffffff;
                border-bottom: 1px solid rgba(0, 0, 0, 0.1);
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            }
        }

        /* Gradient text */
        .gradient-text {
            background: linear-gradient(135deg, #ec4899, #f97316, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Navigation hover effects */
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

        /* Mobile menu animations */
        .mobile-menu {
            transform: translateX(100%);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .mobile-menu.open {
            transform: translateX(0);
        }

        /* Dropdown animations */
        .dropdown-content {
            opacity: 0;
            visibility: hidden;
            transform: translateY(-15px) scale(0.95);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .dropdown:hover .dropdown-content {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }

        /* Button hover effects */
        .btn-primary {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(236, 72, 153, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(236, 72, 153, 0.4);
        }

        /* Mobile menu items animation */
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

        /* Logo image styles */
        .logo-image {
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .logo-container:hover .logo-image {
            transform: scale(1.05);
        }

        /* Fallback pour les images qui ne chargent pas */
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

        /* Hamburger menu animation */
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

        /* Responsive adjustments */
        @media (max-width: 640px) {
            .mobile-menu {
                width: 100vw;
            }
        }

        /* Scroll behavior */
        html {
            scroll-behavior: smooth;
        }

        /* Demo content styling */
        .demo-section {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body class="bg-gray-50">

    <!-- Header avec effet glassmorphism -->
    <header id="header" class="glass-effect fixed top-0 left-0 right-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 sm:h-18 lg:h-20">
                
                <!-- Logo Section -->
                <div class="flex-shrink-0">
                    <a href="index.php" class="flex items-center space-x-2 group logo-container">
                        <div class="relative">
                            <div style="width:70px; height:70px; border-radius:12px; overflow:hidden; box-shadow:0 2px 6px rgba(0,0,0,0.2);">
    <img src="assets/img/logo.jpg" alt="Logo Mulho" style="width:100%; height:100%; object-fit:cover; border-radius:12px;">
                                <div class="logo-fallback">
                                    <span class="text-white font-bold text-sm sm:text-base lg:text-lg">M</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col">
                            <h1 class="text-lg sm:text-xl lg:text-2xl font-bold gradient-text tracking-tight">MULHO</h1>
                            <span class="text-xs text-gray-500 font-medium hidden sm:block">Restaurant & Bar</span>
                        </div>
                    </a>
                </div>
                
                <!-- Navigation Desktop -->
                <nav class="hidden lg:flex items-center space-x-1">
                    <a href="#hero" class="nav-item active px-3 py-2 text-sm text-gray-700 hover:text-pink-600 font-medium rounded-lg hover:bg-gray-50/50 transition-all duration-300 flex items-center space-x-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        <span>Accueil</span>
                    </a>
                    <a href="#about" class="nav-item px-3 py-2 text-sm text-gray-700 hover:text-pink-600 font-medium rounded-lg hover:bg-gray-50/50 transition-all duration-300 flex items-center space-x-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>À propos</span>
                    </a>
                    <a href="menu.php" class="nav-item px-3 py-2 text-sm text-gray-700 hover:text-pink-600 font-medium rounded-lg hover:bg-gray-50/50 transition-all duration-300 flex items-center space-x-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v6a2 2 0 002 2h2m0 0h2a2 2 0 002-2V7a2 2 0 00-2-2H9m0 0V3a2 2 012-2h2a2 2 012 2v2M7 13h10l-4-8H7l4 8z"></path>
                        </svg>
                        <span>Menu</span>
                    </a>
                    <a href="#events" class="nav-item px-3 py-2 text-sm text-gray-700 hover:text-pink-600 font-medium rounded-lg hover:bg-gray-50/50 transition-all duration-300 flex items-center space-x-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span>Événements</span>
                    </a>
                    <a href="gallery_public.php" class="nav-item px-3 py-2 text-sm text-gray-700 hover:text-pink-600 font-medium rounded-lg hover:bg-gray-50/50 transition-all duration-300 flex items-center space-x-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span>Galerie</span>
                    </a>
                    <a href="#contact" class="nav-item px-3 py-2 text-sm text-gray-700 hover:text-pink-600 font-medium rounded-lg hover:bg-gray-50/50 transition-all duration-300 flex items-center space-x-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        <span>Contact</span>
                    </a>

                    <!-- Language Dropdown -->
                    <div class="relative dropdown ml-2">
                        <button class="flex items-center space-x-1.5 px-3 py-2 text-sm text-gray-700 hover:text-pink-600 font-medium rounded-lg hover:bg-gray-50/50 transition-all duration-300">
                            <span class="text-base">🌐</span>
                            <svg class="w-3 h-3 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div class="dropdown-content absolute right-0 mt-2 w-40 bg-white rounded-xl shadow-xl border border-gray-100 py-1 overflow-hidden">
                            <a href="?lang=fr" onclick="changeLanguage('fr')" class="flex items-center space-x-2 px-3 py-2.5 text-sm hover:bg-gradient-to-r hover:from-pink-50 hover:to-orange-50 transition-all duration-200">
                                <span class="text-sm">🇫🇷</span>
                                <span class="text-gray-700 font-medium">Français</span>
                            </a>
                            <a href="?lang=en" onclick="changeLanguage('en')" class="flex items-center space-x-2 px-3 py-2.5 text-sm hover:bg-gradient-to-r hover:from-pink-50 hover:to-orange-50 transition-all duration-200">
                                <span class="text-sm">🇬🇧</span>
                                <span class="text-gray-700 font-medium">English</span>
                            </a>
                            <a href="?lang=wo" onclick="changeLanguage('wo')" class="flex items-center space-x-2 px-3 py-2.5 text-sm hover:bg-gradient-to-r hover:from-pink-50 hover:to-orange-50 transition-all duration-200">
                                <span class="text-sm">🇸🇳</span>
                                <span class="text-gray-700 font-medium">Wolof</span>
                            </a>
                        </div>
                    </div>
                </nav>
                
                <!-- Actions Desktop -->
                <div class="hidden lg:flex items-center">
                    <a href="#book-a-table" class="btn-primary bg-gradient-to-r from-pink-500 to-orange-500 text-white px-4 py-2 rounded-full font-medium hover:from-pink-600 hover:to-orange-600 transition-all duration-300 text-sm whitespace-nowrap">
                        <span class="flex items-center space-x-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <span>Réserver</span>
                        </span>
                    </a>
                </div>

                <!-- Mobile Menu Button -->
                <button id="mobile-menu-toggle" class="lg:hidden p-1.5 text-gray-700 hover:text-pink-600 focus:outline-none focus:ring-2 focus:ring-pink-500 focus:ring-offset-2 rounded-lg transition-all duration-300">
                    <div class="w-5 h-5 flex flex-col justify-center items-center space-y-1">
                        <span class="hamburger-line block w-4 h-0.5 bg-current"></span>
                        <span class="hamburger-line block w-4 h-0.5 bg-current"></span>
                        <span class="hamburger-line block w-4 h-0.5 bg-current"></span>
                    </div>
                </button>
            </div>
        </div>

        <!-- Mobile Menu Overlay -->
        <div id="mobile-menu" class="mobile-menu lg:hidden fixed inset-y-0 right-0 w-full sm:w-80 bg-white shadow-2xl z-50">
            <div class="flex flex-col h-full">
                <!-- Mobile Header -->
                <div class="flex items-center justify-between p-4 border-b border-gray-100">
                    <div class="flex items-center space-x-2">
                        <div class="w-6 h-6 rounded-lg overflow-hidden relative">
                            <!-- Utilisez la même image que pour le logo principal -->
                            <img src="assets/img/logo.jpg" alt="Logo Mulho" class="logo-image w-full h-full object-cover rounded-lg">
                            <!-- Fallback pour mobile -->
                            <div class="logo-fallback">
                                <span class="text-white font-bold text-xs">M</span>
                            </div>
                        </div>
                        <h2 class="text-base font-bold gradient-text">MULHO</h2>
                    </div>
                    <button id="mobile-menu-close" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-all duration-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <!-- Mobile Navigation -->
                <nav class="flex-1 px-4 py-6 overflow-y-auto">
                    <div class="space-y-1">
                        <a href="#hero" class="mobile-nav-item flex items-center space-x-3 text-gray-700 hover:text-pink-600 hover:bg-pink-50 font-medium py-3 px-3 rounded-lg transition-all duration-300">
                            <svg class="w-4 h-4 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                            </svg>
                            <span>Accueil</span>
                        </a>
                        <a href="#about" class="mobile-nav-item flex items-center space-x-3 text-gray-700 hover:text-pink-600 hover:bg-pink-50 font-medium py-3 px-3 rounded-lg transition-all duration-300">
                            <svg class="w-4 h-4 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>À propos</span>
                        </a>
                        <a href="menu.php" class="mobile-nav-item flex items-center space-x-3 text-gray-700 hover:text-pink-600 hover:bg-pink-50 font-medium py-3 px-3 rounded-lg transition-all duration-300">
                            <svg class="w-4 h-4 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v6a2 2 0 002 2h2m0 0h2a2 2 0 002-2V7a2 2 0 00-2-2H9m0 0V3a2 2 012-2h2a2 2 012 2v2M7 13h10l-4-8H7l4 8z"></path>
                            </svg>
                            <span>Menu</span>
                        </a>
                        <a href="#events" class="mobile-nav-item flex items-center space-x-3 text-gray-700 hover:text-pink-600 hover:bg-pink-50 font-medium py-3 px-3 rounded-lg transition-all duration-300">
                            <svg class="w-4 h-4 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <span>Événements</span>
                        </a>
                        <a href="gallery_public.php" class="mobile-nav-item flex items-center space-x-3 text-gray-700 hover:text-pink-600 hover:bg-pink-50 font-medium py-3 px-3 rounded-lg transition-all duration-300">
                            <svg class="w-4 h-4 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <span>Galerie</span>
                        </a>
                        <a href="#contact" class="mobile-nav-item flex items-center space-x-3 text-gray-700 hover:text-pink-600 hover:bg-pink-50 font-medium py-3 px-3 rounded-lg transition-all duration-300">
                            <svg class="w-4 h-4 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            <span>Contact</span>
                        </a>
                    </div>

                    <!-- Languages Section Mobile -->
                    <div class="mt-6">
                        <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3 px-3">Langues</h3>
                        <div class="space-y-1">
                            <a href="?lang=fr" onclick="changeLanguage('fr')" class="mobile-nav-item flex items-center space-x-3 text-gray-700 hover:text-pink-600 hover:bg-pink-50 font-medium py-2.5 px-3 rounded-lg transition-all duration-300">
                                <span class="text-base">🇫🇷</span>
                                <span class="text-sm">Français</span>
                            </a>
                            <a href="?lang=en" onclick="changeLanguage('en')" class="mobile-nav-item flex items-center space-x-3 text-gray-700 hover:text-pink-600 hover:bg-pink-50 font-medium py-2.5 px-3 rounded-lg transition-all duration-300">
                                <span class="text-base">🇬🇧</span>
                                <span class="text-sm">English</span>
                            </a>
                            <a href="?lang=wo" onclick="changeLanguage('wo')" class="mobile-nav-item flex items-center space-x-3 text-gray-700 hover:text-pink-600 hover:bg-pink-50 font-medium py-2.5 px-3 rounded-lg transition-all duration-300">
                                <span class="text-base">🇸🇳</span>
                                <span class="text-sm">Wolof</span>
                            </a>
                        </div>
                    </div>
                </nav>
                
                <!-- Mobile CTA -->
                <div class="p-4 border-t border-gray-100">
                    <a href="#book-a-table" class="btn-primary block w-full bg-gradient-to-r from-pink-500 to-orange-500 text-white text-center px-4 py-3 rounded-xl font-medium hover:from-pink-600 hover:to-orange-600 transition-all duration-300">
                        <span class="flex items-center justify-center space-x-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <span>Réserver une table</span>
                        </span>
                    </a>
                </div>
            </div>
        </div>
    </header>

  

    <script>
        // JavaScript pour les fonctionnalités du navbar
        document.addEventListener('DOMContentLoaded', function() {
            // === 🌐 Changement de langue ===
            function changeLanguage(lang) {
                console.log('Langue sélectionnée:', lang);
                // Ici vous pouvez ajouter la logique pour changer la langue
                // Par exemple: window.location.search = `?lang=${lang}`;
            }
            window.changeLanguage = changeLanguage;

            // === 📱 Mobile menu ===
            const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
            const mobileMenuClose = document.getElementById('mobile-menu-close');
            const mobileMenu = document.getElementById('mobile-menu');

            function openMobileMenu() {
                mobileMenu?.classList.add('open');
                mobileMenuToggle?.classList.add('hamburger-active');
                document.body.style.overflow = 'hidden';
            }

            function closeMobileMenu() {
                mobileMenu?.classList.remove('open');
                mobileMenuToggle?.classList.remove('hamburger-active');
                document.body.style.overflow = 'auto';
            }

            mobileMenuToggle?.addEventListener('click', function() {
                if (mobileMenu.classList.contains('open')) {
                    closeMobileMenu();
                } else {
                    openMobileMenu();
                }
            });

            mobileMenuClose?.addEventListener('click', closeMobileMenu);

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
                    closeMobileMenu();
                });
            });

            // === 🎯 Navigation active state ===
            function updateActiveNavItem() {
                const sections = document.querySelectorAll('section[id]');
                const navItems = document.querySelectorAll('.nav-item');
                const headerHeight = document.getElementById('header').offsetHeight;
                
                let currentSection = '';
                sections.forEach(section => {
                    const sectionTop = section.offsetTop - headerHeight - 100;
                    const sectionHeight = section.offsetHeight;
                    if (window.scrollY >= sectionTop && window.scrollY < sectionTop + sectionHeight) {
                        currentSection = section.getAttribute('id');
                    }
                });

                navItems.forEach(item => {
                    item.classList.remove('active');
                    if (item.getAttribute('href') === `#${currentSection}`) {
                        item.classList.add('active');
                    }
                });
            }

            // === 🎪 Effets de scroll ===
            window.addEventListener('scroll', debounce(function() {
                updateActiveNavItem();
            }, 16));

            // Fermer le menu mobile en cliquant à l'extérieur
            document.addEventListener('click', function(e) {
                if (mobileMenu && mobileMenu.classList.contains('open')) {
                    if (!mobileMenu.contains(e.target) && !mobileMenuToggle.contains(e.target)) {
                        closeMobileMenu();
                    }
                }
            });

            // Fermer le menu mobile avec la touche Escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && mobileMenu && mobileMenu.classList.contains('open')) {
                    closeMobileMenu();
                }
            });

            // === 📱 Détection du type d'appareil ===
            function detectDevice() {
                const isMobile = window.innerWidth <= 768;
                const isTablet = window.innerWidth > 768 && window.innerWidth <= 1024;
                const isDesktop = window.innerWidth > 1024;
                
                document.body.classList.toggle('is-mobile', isMobile);
                document.body.classList.toggle('is-tablet', isTablet);
                document.body.classList.toggle('is-desktop', isDesktop);
            }

            // === 🖼️ Gestion des logos images ===
            function handleLogoImages() {
                const logoImages = document.querySelectorAll('.logo-image');
                
                logoImages.forEach(img => {
                    img.addEventListener('error', function() {
                        // Si l'image ne charge pas, afficher le fallback
                        const fallback = this.nextElementSibling;
                        if (fallback && fallback.classList.contains('logo-fallback')) {
                            this.style.display = 'none';
                            fallback.classList.add('show');
                        }
                    });
                    
                    img.addEventListener('load', function() {
                        // Si l'image charge correctement, cacher le fallback
                        const fallback = this.nextElementSibling;
                        if (fallback && fallback.classList.contains('logo-fallback')) {
                            fallback.classList.remove('show');
                        }
                    });
                });
            }

            // Initialiser la gestion des logos
            handleLogoImages();
            
            // Détecter au chargement et au redimensionnement
            detectDevice();
            window.addEventListener('resize', debounce(detectDevice, 250));

            // === 🎨 Effets visuels avancés ===
            // Effet de particules subtiles pour desktop
            if (window.innerWidth > 1024) {
                const particles = document.createElement('div');
                particles.className = 'fixed inset-0 pointer-events-none z-0';
                particles.style.background = `
                    radial-gradient(circle at 25% 25%, rgba(236, 72, 153, 0.05) 0%, transparent 50%),
                    radial-gradient(circle at 75% 75%, rgba(249, 115, 22, 0.05) 0%, transparent 50%)
                `;
                document.body.appendChild(particles);
            }
        });

        // === 🔧 Performance et optimisations ===
        // Debounce function pour optimiser les événements
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

        // === 🎯 Gestion des erreurs et fallbacks ===
        window.addEventListener('error', function(e) {
            console.warn('Erreur détectée:', e.message);
        });
    </script>

</body>
</html>