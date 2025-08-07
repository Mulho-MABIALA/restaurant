<?php
require_once __DIR__ . '/../config.php';

try {
    // Récupération des catégories depuis la table dédiée 'categories'
    $stmt = $conn->query("
        SELECT id, nom 
        FROM categories 
        ORDER BY nom ASC
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Gestion d'erreur et fallback
    error_log("Erreur de récupération des catégories: " . $e->getMessage());
    $categories = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sidebar Moderne - Restaurant Jungle</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#10b981',
                        'primary-dark': '#059669',
                        'primary-light': '#34d399',
                        'secondary': '#1f2937',
                        'secondary-light': '#374151',
                        'accent': '#f59e0b',
                        'accent-light': '#fbbf24',
                        'danger': '#ef4444',
                        'surface': '#111827',
                        'surface-light': '#1f2937',
                        'surface-lighter': '#374151'
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.6s ease-out',
                        'slide-in-left': 'slideInLeft 0.4s cubic-bezier(0.4, 0, 0.2, 1)',
                        'bounce-subtle': 'bounceSubtle 3s ease-in-out infinite',
                        'glow': 'glow 3s ease-in-out infinite alternate',
                        'pulse-soft': 'pulseSoft 2s ease-in-out infinite',
                        'float': 'float 4s ease-in-out infinite',
                        'shimmer': 'shimmer 2s linear infinite'
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0', transform: 'translateY(10px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        },
                        slideInLeft: {
                            '0%': { transform: 'translateX(-100%)', opacity: '0' },
                            '100%': { transform: 'translateX(0)', opacity: '1' }
                        },
                        bounceSubtle: {
                            '0%, 100%': { transform: 'translateY(0) scale(1)' },
                            '50%': { transform: 'translateY(-2px) scale(1.05)' }
                        },
                        glow: {
                            '0%': { boxShadow: '0 0 10px rgba(16, 185, 129, 0.3)' },
                            '100%': { boxShadow: '0 0 25px rgba(16, 185, 129, 0.6), 0 0 40px rgba(16, 185, 129, 0.3)' }
                        },
                        pulseSoft: {
                            '0%, 100%': { opacity: '0.6' },
                            '50%': { opacity: '1' }
                        },
                        float: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-3px)' }
                        },
                        shimmer: {
                            '0%': { backgroundPosition: '-200% center' },
                            '100%': { backgroundPosition: '200% center' }
                        }
                    },
                    backdropBlur: {
                        'xs': '2px'
                    }
                }
            }
        }
    </script>
    <style>
        .glass-morphism {
            background: rgba(17, 24, 39, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        
        .nav-item {
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .nav-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(16, 185, 129, 0.15), transparent);
            transition: left 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .nav-item:hover::before {
            left: 100%;
        }
        
        .nav-item::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            width: 3px;
            height: 0;
            background: linear-gradient(to bottom, #10b981, #34d399);
            transition: all 0.3s ease;
            transform: translateY(-50%);
            border-radius: 0 3px 3px 0;
        }
        
        .nav-item:hover::after {
            height: 70%;
        }
        
        .active-nav {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(5, 150, 105, 0.3) 100%);
            border: 1px solid rgba(16, 185, 129, 0.4);
            box-shadow: 0 8px 32px rgba(16, 185, 129, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.1);
        }
        
        .active-nav::after {
            height: 70%;
            background: linear-gradient(to bottom, #34d399, #10b981);
        }
        
        .nav-icon {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .nav-item:hover .nav-icon {
            transform: scale(1.15) rotate(8deg);
            color: #10b981;
            filter: drop-shadow(0 0 8px rgba(16, 185, 129, 0.4));
        }
        
        .active-nav .nav-icon {
            color: #34d399;
            transform: scale(1.1);
            filter: drop-shadow(0 0 8px rgba(52, 211, 153, 0.4));
        }
        
        .danger-hover:hover {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2) 0%, rgba(220, 38, 38, 0.3) 100%);
            border: 1px solid rgba(239, 68, 68, 0.4);
            box-shadow: 0 8px 32px rgba(239, 68, 68, 0.2);
        }
        
        .danger-hover:hover .nav-icon {
            color: #ef4444;
            filter: drop-shadow(0 0 8px rgba(239, 68, 68, 0.4));
        }
        
        .sidebar-gradient {
            background: linear-gradient(180deg, #0f172a 0%, #111827 25%, #1f2937 75%, #111827 100%);
            position: relative;
        }
        
        .sidebar-gradient::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, transparent 50%, rgba(245, 158, 11, 0.05) 100%);
            pointer-events: none;
        }
        
        .logo-glow {
            animation: glow 4s ease-in-out infinite alternate;
        }
        
        .scrollbar-thin::-webkit-scrollbar {
            width: 6px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 6px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: linear-gradient(to bottom, rgba(16, 185, 129, 0.6), rgba(16, 185, 129, 0.3));
            border-radius: 6px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(to bottom, rgba(16, 185, 129, 0.8), rgba(16, 185, 129, 0.5));
        }
        
        .mobile-overlay {
            backdrop-filter: blur(12px);
            background: rgba(0, 0, 0, 0.6);
        }
        
        .section-title {
            position: relative;
            overflow: hidden;
        }
        
        .section-title::before {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 20px;
            height: 2px;
            background: linear-gradient(to right, #10b981, #34d399);
            border-radius: 1px;
        }
        
        .nav-description {
            transition: all 0.3s ease;
        }
        
        .nav-item:hover .nav-description {
            color: #d1d5db;
        }
        
        .floating-elements {
            position: absolute;
            top: 20%;
            right: 10px;
            opacity: 0.1;
            pointer-events: none;
        }
        
        .floating-elements i {
            display: block;
            margin: 20px 0;
            animation: float 4s ease-in-out infinite;
        }
        
        .floating-elements i:nth-child(2) {
            animation-delay: 1s;
        }
        
        .floating-elements i:nth-child(3) {
            animation-delay: 2s;
        }
        
        .status-indicator {
            position: relative;
        }
        
        .status-indicator::before {
            content: '';
            position: absolute;
            top: -2px;
            right: -2px;
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            animation: pulse-soft 2s ease-in-out infinite;
        }
        
        @media (max-width: 1023px) {
            .sidebar-mobile {
                transform: translateX(-100%);
                transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            }
            
            .sidebar-mobile.open {
                transform: translateX(0);
            }
        }
        
        /* Amélioration des dropdowns */
        .dropdown-menu {
            background: rgba(31, 41, 55, 0.95);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
        }
        
        .dropdown-item {
            position: relative;
            overflow: hidden;
        }
        
        .dropdown-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 2px;
            height: 0;
            background: #10b981;
            transition: height 0.3s ease;
            transform: translateY(-50%);
        }
        
        .dropdown-item:hover::before {
            height: 60%;
        }
        
        /* Animation d'entrée améliorée */
        .animate-fade-in-up {
            animation: fadeInUp 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Shimmer effect pour les éléments interactifs */
        .shimmer {
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.05), transparent);
            background-size: 200% 100%;
            animation: shimmer 3s linear infinite;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 via-gray-100 to-gray-200 min-h-screen">

<!-- Mobile overlay -->
<div id="mobile-overlay" class="fixed inset-0 z-40 mobile-overlay opacity-0 pointer-events-none lg:hidden transition-opacity duration-300"></div>

<!-- Mobile menu button -->
<button id="mobile-menu-btn" class="fixed top-4 left-4 z-50 lg:hidden bg-surface text-white p-3 rounded-xl shadow-2xl hover:bg-surface-light transition-all duration-300 hover:scale-105">
    <i class="fas fa-bars text-lg"></i>
</button>

<!-- Sidebar -->
<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-80 sidebar-gradient shadow-2xl sidebar-mobile lg:relative lg:translate-x-0 animate-slide-in-left">
    
    <!-- Floating decorative elements -->
    <div class="floating-elements">
        <i class="fas fa-leaf text-primary text-lg"></i>
        <i class="fas fa-utensils text-accent text-sm"></i>
        <i class="fas fa-star text-primary-light text-xs"></i>
    </div>
    
    <!-- Header -->
    <div class="flex items-center justify-between p-6 border-b border-gray-700/40 animate-fade-in-up">
        <div class="flex items-center space-x-4 group cursor-pointer">
            <div class="relative">
                <div class="w-14 h-14 bg-gradient-to-br from-primary via-primary-light to-accent rounded-2xl flex items-center justify-center logo-glow shadow-lg">
                    <i class="fas fa-leaf text-white text-2xl"></i>
                </div>
                <div class="absolute -top-2 -right-2 w-5 h-5 bg-gradient-to-r from-accent to-accent-light rounded-full animate-bounce-subtle shadow-lg"></div>
            </div>
            <div>
                <h1 class="text-2xl font-bold bg-gradient-to-r from-primary via-accent to-primary-light bg-clip-text text-transparent">
                    Jungle
                </h1>
                <p class="text-sm text-gray-400 font-medium opacity-90">Restaurant Admin</p>
            </div>
        </div>
        
        <button id="close-sidebar" class="lg:hidden text-gray-400 hover:text-white p-2.5 rounded-xl hover:bg-surface-light transition-all duration-300 hover:scale-105">
            <i class="fas fa-times text-xl"></i>
        </button>
    </div>
    
    <!-- Navigation -->
    <nav class="px-4 py-6 space-y-6 overflow-y-auto h-full scrollbar-thin">
        
        <!-- Dashboard Section -->
        <div class="space-y-3 animate-fade-in">
            <h2 class="section-title text-xs font-semibold text-gray-400 uppercase tracking-wider px-4 flex items-center pb-2">
                <div class="w-2 h-2 bg-primary rounded-full mr-3 animate-pulse-soft"></div>
                Tableau de bord
            </h2>
            <a href="dashboard.php" class="nav-item active-nav flex items-center px-4 py-4 text-white rounded-2xl transition-all duration-300 hover:shadow-2xl group">
                <div class="flex items-center justify-center w-12 h-12 bg-white/10 rounded-xl mr-4 group-hover:bg-white/20 transition-all duration-300 shimmer">
                    <i class="fas fa-chart-bar nav-icon text-lg"></i>
                </div>
                <div class="flex-1">
                    <span class="font-semibold text-base">Dashboard</span>
                    <p class="nav-description text-sm text-gray-300 opacity-90">Vue d'ensemble</p>
                </div>
                <div class="w-2 h-2 bg-white rounded-full opacity-80 group-hover:opacity-100 transition-opacity"></div>
            </a>
        </div>
        
        <!-- Restaurant Management -->
        <div class="space-y-3 animate-fade-in" style="animation-delay: 0.1s">
            <h2 class="section-title text-xs font-semibold text-gray-400 uppercase tracking-wider px-4 flex items-center pb-2">
                <div class="w-2 h-2 bg-accent rounded-full mr-3 animate-pulse-soft" style="animation-delay: 0.5s"></div>
                Gestion Restaurant
            </h2>
            <div class="space-y-2">
                <a href="reservations.php" class="nav-item flex items-center px-4 py-4 text-gray-300 hover:bg-surface-lighter/50 hover:text-white rounded-2xl transition-all duration-300 group hover:shadow-xl">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/5 rounded-xl mr-4 group-hover:bg-white/10 transition-all duration-300">
                        <i class="fas fa-calendar-check nav-icon text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <span class="font-medium text-base">Réservations</span>
                        <p class="nav-description text-sm text-gray-400 opacity-80">Gestion des réservations</p>
                    </div>
                    <i class="fas fa-chevron-right text-xs opacity-0 group-hover:opacity-60 transition-all duration-300 transform group-hover:translate-x-1"></i>
                </a>
                <a href="commandes.php" class="nav-item flex items-center px-4 py-4 text-gray-300 hover:bg-surface-lighter/50 hover:text-white rounded-2xl transition-all duration-300 group hover:shadow-xl">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/5 rounded-xl mr-4 group-hover:bg-white/10 transition-all duration-300">
                        <i class="fas fa-receipt nav-icon text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <span class="font-medium text-base">Commandes</span>
                        <p class="nav-description text-sm text-gray-400 opacity-80">Gestion des commandes</p>
                    </div>
                    <i class="fas fa-chevron-right text-xs opacity-0 group-hover:opacity-60 transition-all duration-300 transform group-hover:translate-x-1"></i>
                </a>
                
                <a href="gestion_plats.php" class="nav-item flex items-center px-4 py-4 text-gray-300 hover:bg-surface-lighter/50 hover:text-white rounded-2xl transition-all duration-300 group hover:shadow-xl">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/5 rounded-xl mr-4 group-hover:bg-white/10 transition-all duration-300">
                        <i class="fas fa-utensils nav-icon text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <span class="font-medium text-base">Menus</span>
                        <p class="nav-description text-sm text-gray-400 opacity-80">Gestion des menus</p>
                    </div>
                    <i class="fas fa-chevron-right text-xs opacity-0 group-hover:opacity-60 transition-all duration-300 transform group-hover:translate-x-1"></i>
                </a>
                
                <a href="categories_plats.php" class="nav-item flex items-center px-4 py-4 text-gray-300 hover:bg-surface-lighter/50 hover:text-white rounded-2xl transition-all duration-300 group hover:shadow-xl">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/5 rounded-xl mr-4 group-hover:bg-white/10 transition-all duration-300">
                        <i class="fas fa-folder nav-icon text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <span class="font-medium text-base">Catégories</span>
                        <p class="nav-description text-sm text-gray-400 opacity-80">Catégories de plats</p>
                    </div>
                    <i class="fas fa-chevron-right text-xs opacity-0 group-hover:opacity-60 transition-all duration-300 transform group-hover:translate-x-1"></i>
                </a>
        
                
                <a href="cuisine.php" class="nav-item flex items-center px-4 py-4 text-gray-300 hover:bg-surface-lighter/50 hover:text-white rounded-2xl transition-all duration-300 group hover:shadow-xl">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/5 rounded-xl mr-4 group-hover:bg-white/10 transition-all duration-300">
                        <i class="fas fa-fire nav-icon text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <span class="font-medium text-base">Cuisine</span>
                        <p class="nav-description text-sm text-gray-400 opacity-80">Gestion de la cuisine</p>
                    </div>
                    <i class="fas fa-chevron-right text-xs opacity-0 group-hover:opacity-60 transition-all duration-300 transform group-hover:translate-x-1"></i>
                </a>
                
                <a href="horaires.php" class="nav-item flex items-center px-4 py-4 text-gray-300 hover:bg-surface-lighter/50 hover:text-white rounded-2xl transition-all duration-300 group hover:shadow-xl">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/5 rounded-xl mr-4 group-hover:bg-white/10 transition-all duration-300">
                        <i class="fas fa-clock nav-icon text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <span class="font-medium text-base">Horaires</span>
                        <p class="nav-description text-sm text-gray-400 opacity-80">Horaires d'ouverture</p>
                    </div>
                    <i class="fas fa-chevron-right text-xs opacity-0 group-hover:opacity-60 transition-all duration-300 transform group-hover:translate-x-1"></i>
                </a>
                
                <a href="badgeuse.php" class="nav-item flex items-center px-4 py-4 text-gray-300 hover:bg-surface-lighter/50 hover:text-white rounded-2xl transition-all duration-300 group hover:shadow-xl">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/5 rounded-xl mr-4 group-hover:bg-white/10 transition-all duration-300">
                        <i class="fas fa-id-card nav-icon text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <span class="font-medium text-base">Badgeuse</span>
                        <p class="nav-description text-sm text-gray-400 opacity-80">Pointage employé</p>
                    </div>
                    <i class="fas fa-chevron-right text-xs opacity-0 group-hover:opacity-60 transition-all duration-300 transform group-hover:translate-x-1"></i>
                </a>
                
                <a href="presence.php" class="nav-item flex items-center px-4 py-4 text-gray-300 hover:bg-surface-lighter/50 hover:text-white rounded-2xl transition-all duration-300 group hover:shadow-xl">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/5 rounded-xl mr-4 group-hover:bg-white/10 transition-all duration-300">
                        <i class="fas fa-user-check nav-icon text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <span class="font-medium text-base">Présence</span>
                        <p class="nav-description text-sm text-gray-400 opacity-80">Gestion présence</p>
                    </div>
                    <i class="fas fa-chevron-right text-xs opacity-0 group-hover:opacity-60 transition-all duration-300 transform group-hover:translate-x-1"></i>
                </a>

                <!-- Communication Dropdown -->
                <div x-data="{ open: false }" class="relative">
                    <button type="button"
                        class="nav-item flex items-center px-4 py-4 text-gray-300 hover:bg-surface-lighter/50 hover:text-white rounded-2xl transition-all duration-300 group hover:shadow-xl w-full focus:outline-none"
                        @click="open = !open"
                        aria-haspopup="true"
                        :aria-expanded="open"
                    >
                        <div class="flex items-center justify-center w-12 h-12 bg-white/5 rounded-xl mr-4 group-hover:bg-white/10 transition-all duration-300">
                            <i class="fas fa-comments nav-icon text-lg"></i>
                        </div>
                        <div class="flex-1 text-left">
                            <span class="font-medium text-base">Communication</span>
                            <p class="nav-description text-sm text-gray-400 opacity-80">Outils internes</p>
                        </div>
                        <i class="fas fa-chevron-down text-xs ml-2 transition-transform duration-300"
                           :class="open ? 'rotate-180' : ''"></i>
                    </button>
                    <div x-show="open" @click.away="open = false"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
                         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-200"
                         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                         x-transition:leave-end="opacity-0 scale-95 -translate-y-2"
                         class="mt-3 ml-8 space-y-1 dropdown-menu rounded-xl shadow-2xl py-3 w-[85%] z-10 absolute left-0"
                         style="display: none;"
                    >
                        <a href="communication/annonces.php" class="dropdown-item flex items-center px-4 py-3 text-gray-300 hover:bg-primary/20 hover:text-white rounded-lg transition-all duration-300">
                            <i class="fas fa-bullhorn mr-3 w-5 text-sm"></i>
                            <span class="font-medium">Annonces internes</span>
                        </a>
                        <a href="communication/messagerie.php" class="dropdown-item flex items-center px-4 py-3 text-gray-300 hover:bg-primary/20 hover:text-white rounded-lg transition-all duration-300">
                            <i class="fas fa-envelope mr-3 w-5 text-sm"></i>
                            <span class="font-medium">Messagerie interne</span>
                        </a>
                        <a href="communication/procedures.php" class="dropdown-item flex items-center px-4 py-3 text-gray-300 hover:bg-primary/20 hover:text-white rounded-lg transition-all duration-300">
                            <i class="fas fa-book mr-3 w-5 text-sm"></i>
                            <span class="font-medium">Procédures internes</span>
                        </a>
                        <a href="communication/signalements.php" class="dropdown-item flex items-center px-4 py-3 text-gray-300 hover:bg-primary/20 hover:text-white rounded-lg transition-all duration-300">
                            <i class="fas fa-exclamation-triangle mr-3 w-5 text-sm"></i>
                            <span class="font-medium">Signalements</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Administration Section -->
        <div class="space-y-3 animate-fade-in" style="animation-delay: 0.2s">
            <h2 class="section-title text-xs font-semibold text-gray-400 uppercase tracking-wider px-4 flex items-center pb-2">
                <div class="w-2 h-2 bg-blue-500 rounded-full mr-3 animate-pulse-soft" style="animation-delay: 1s"></div>
                Administration
            </h2>
            <div class="space-y-2">
                <a href="gestion_stock.php" class="nav-item flex items-center px-4 py-4 text-gray-300 hover:bg-surface-lighter/50 hover:text-white rounded-2xl transition-all duration-300 group hover:shadow-xl">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/5 rounded-xl mr-4 group-hover:bg-white/10 transition-all duration-300">
                        <i class="fas fa-boxes nav-icon text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <span class="font-medium text-base">Stocks</span>
                        <p class="nav-description text-sm text-gray-400 opacity-80">Gestion des stocks</p>
                    </div>
                    <i class="fas fa-chevron-right text-xs opacity-0 group-hover:opacity-60 transition-all duration-300 transform group-hover:translate-x-1"></i>
                </a>
                <a href="gestion_employe.php" class="nav-item flex items-center px-4 py-4 text-gray-300 hover:bg-surface-lighter/50 hover:text-white rounded-2xl transition-all duration-300 group hover:shadow-xl">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/5 rounded-xl mr-4 group-hover:bg-white/10 transition-all duration-300">
                        <i class="fas fa-user-tie nav-icon text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <span class="font-medium text-base">Employés</span>
                        <p class="nav-description text-sm text-gray-400 opacity-80">Gestion des employés</p>
                    </div>
                    <i class="fas fa-chevron-right text-xs opacity-0 group-hover:opacity-60 transition-all duration-300 transform group-hover:translate-x-1"></i>
                </a>
                <a href="admin_gestion.php" class="nav-item flex items-center px-4 py-4 text-gray-300 hover:bg-surface-lighter/50 hover:text-white rounded-2xl transition-all duration-300 group hover:shadow-xl">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/5 rounded-xl mr-4 group-hover:bg-white/10 transition-all duration-300">
                        <i class="fas fa-users-cog nav-icon text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <span class="font-medium text-base">Admins</span>
                        <p class="nav-description text-sm text-gray-400 opacity-80">Gestion des admins</p>
                    </div>
                    <i class="fas fa-chevron-right text-xs opacity-0 group-hover:opacity-60 transition-all duration-300 transform group-hover:translate-x-1"></i>
                </a>
                
                <a href="statistiques.php" class="nav-item flex items-center px-4 py-4 text-gray-300 hover:bg-surface-lighter/50 hover:text-white rounded-2xl transition-all duration-300 group hover:shadow-xl">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/5 rounded-xl mr-4 group-hover:bg-white/10 transition-all duration-300">
                        <i class="fas fa-chart-line nav-icon text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <span class="font-medium text-base">Statistiques</span>
                        <p class="nav-description text-sm text-gray-400 opacity-80">Analyses et rapports</p>
                    </div>
                    <i class="fas fa-chevron-right text-xs opacity-0 group-hover:opacity-60 transition-all duration-300 transform group-hover:translate-x-1"></i>
                </a>
            </div>
        </div>
        
        <!-- Divider -->
        <div class="relative px-4 py-6 animate-fade-in" style="animation-delay: 0.3s">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gradient-to-r from-transparent via-gray-600/50 to-transparent"></div>
            </div>
            <div class="relative flex justify-center">
                <div class="bg-surface px-6 flex items-center space-x-3">
                    <div class="w-2 h-2 bg-primary rounded-full animate-pulse-soft"></div>
                    <div class="w-1.5 h-1.5 bg-accent rounded-full animate-pulse-soft" style="animation-delay: 0.3s"></div>
                    <div class="w-2 h-2 bg-primary-light rounded-full animate-pulse-soft" style="animation-delay: 0.6s"></div>
                </div>
            </div>
        </div>
        
        <!-- Logout Section -->
        <div class="space-y-3 pb-6 animate-fade-in" style="animation-delay: 0.4s">
            <a href="logout.php" class="nav-item danger-hover flex items-center px-4 py-4 text-gray-300 hover:text-white rounded-2xl transition-all duration-300 group hover:shadow-xl">
                <div class="flex items-center justify-center w-12 h-12 bg-white/5 rounded-xl mr-4 group-hover:bg-white/10 transition-all duration-300">
                    <i class="fas fa-sign-out-alt nav-icon text-lg"></i>
                </div>
                <div class="flex-1">
                    <span class="font-medium text-base">Déconnexion</span>
                    <p class="nav-description text-sm text-gray-400 opacity-80">Se déconnecter</p>
                </div>
                <i class="fas fa-chevron-right text-xs opacity-0 group-hover:opacity-60 transition-all duration-300 transform group-hover:translate-x-1"></i>
            </a>
        </div>
        
        <!-- Footer -->
        <div class="px-4 pb-4 text-center border-t border-gray-700/40 pt-6 animate-fade-in" style="animation-delay: 0.5s">
            <div class="glass-morphism rounded-xl p-5">
                <div class="text-xs text-gray-400">
                    <div class="flex items-center justify-center space-x-3 mb-3">
                        <div class="status-indicator w-3 h-3 bg-green-500 rounded-full"></div>
                        <span class="font-semibold text-green-400">Système en ligne</span>
                    </div>
                    <div class="space-y-1">
                        <p class="font-medium text-gray-300">Version 2.0</p>
                        <p class="opacity-70">© 2024 Jungle Restaurant</p>
                    </div>
                    <div class="mt-3 flex justify-center space-x-2">
                        <div class="w-1 h-1 bg-primary rounded-full animate-pulse-soft"></div>
                        <div class="w-1 h-1 bg-accent rounded-full animate-pulse-soft" style="animation-delay: 0.2s"></div>
                        <div class="w-1 h-1 bg-primary-light rounded-full animate-pulse-soft" style="animation-delay: 0.4s"></div>
                    </div>
                </div>
            </div>
        </div>
    </nav>
</aside>

<!-- Alpine.js for dropdown -->
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<script>
    const sidebar = document.getElementById('sidebar');
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const closeSidebar = document.getElementById('close-sidebar');
    const mobileOverlay = document.getElementById('mobile-overlay');
    
    function toggleSidebar() {
        sidebar.classList.toggle('open');
        mobileOverlay.classList.toggle('opacity-0');
        mobileOverlay.classList.toggle('pointer-events-none');
        document.body.classList.toggle('overflow-hidden');
        
        // Animation améliorée pour le bouton mobile
        const btn = mobileMenuBtn.querySelector('i');
        btn.style.transform = sidebar.classList.contains('open') ? 'rotate(90deg)' : 'rotate(0deg)';
    }
    
    mobileMenuBtn.addEventListener('click', toggleSidebar);
    closeSidebar.addEventListener('click', toggleSidebar);
    mobileOverlay.addEventListener('click', toggleSidebar);
    
    // Close sidebar on window resize if open on mobile
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 1024) {
            sidebar.classList.remove('open');
            mobileOverlay.classList.add('opacity-0', 'pointer-events-none');
            document.body.classList.remove('overflow-hidden');
            const btn = mobileMenuBtn.querySelector('i');
            btn.style.transform = 'rotate(0deg)';
        }
    });
    
    // Animation d'entrée progressive des éléments
    document.addEventListener('DOMContentLoaded', function() {
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach((item, index) => {
            item.style.animationDelay = `${0.1 + (index * 0.05)}s`;
            item.classList.add('animate-fade-in-up');
        });
    });
    
    // Effet de hover amélioré pour les icônes
    const navIcons = document.querySelectorAll('.nav-icon');
    navIcons.forEach(icon => {
        const navItem = icon.closest('.nav-item');
        
        navItem.addEventListener('mouseenter', () => {
            icon.style.filter = 'drop-shadow(0 0 12px currentColor)';
        });
        
        navItem.addEventListener('mouseleave', () => {
            if (!navItem.classList.contains('active-nav')) {
                icon.style.filter = '';
            }
        });
    });
</script>

</body>
</html>