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
                        'secondary': '#1f2937',
                        'secondary-light': '#374151',
                        'accent': '#f59e0b',
                        'accent-light': '#fbbf24',
                        'danger': '#ef4444',
                        'surface': '#111827',
                        'surface-light': '#1f2937'
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-in-left': 'slideInLeft 0.3s ease-out',
                        'bounce-subtle': 'bounceSubtle 2s infinite',
                        'glow': 'glow 2s ease-in-out infinite alternate'
                    },
                    keyframes: {
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        },
                        slideInLeft: {
                            '0%': { transform: 'translateX(-100%)', opacity: '0' },
                            '100%': { transform: 'translateX(0)', opacity: '1' }
                        },
                        bounceSubtle: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-4px)' }
                        },
                        glow: {
                            '0%': { boxShadow: '0 0 5px #10b981' },
                            '100%': { boxShadow: '0 0 20px #10b981, 0 0 30px #10b981' }
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
            background: rgba(17, 24, 39, 0.85);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .nav-item {
            position: relative;
            overflow: hidden;
        }
        
        .nav-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(16, 185, 129, 0.2), transparent);
            transition: left 0.6s ease-out;
        }
        
        .nav-item:hover::before {
            left: 100%;
        }
        
        .active-nav {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            box-shadow: 0 8px 32px rgba(16, 185, 129, 0.3);
            border: 1px solid rgba(16, 185, 129, 0.5);
        }
        
        .nav-icon {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .nav-item:hover .nav-icon {
            transform: scale(1.1) rotate(5deg);
            color: #10b981;
        }
        
        .active-nav .nav-icon {
            color: white;
            transform: scale(1.1);
        }
        
        .danger-hover:hover {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            box-shadow: 0 8px 32px rgba(239, 68, 68, 0.3);
        }
        
        .sidebar-gradient {
            background: linear-gradient(135deg, #111827 0%, #1f2937 50%, #111827 100%);
        }
        
        .logo-glow {
            animation: glow 3s ease-in-out infinite alternate;
        }
        
        .scrollbar-thin::-webkit-scrollbar {
            width: 4px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: rgba(16, 185, 129, 0.5);
            border-radius: 4px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: rgba(16, 185, 129, 0.8);
        }
        
        .mobile-overlay {
            backdrop-filter: blur(8px);
            background: rgba(0, 0, 0, 0.5);
        }
        
        @media (max-width: 1023px) {
            .sidebar-mobile {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
            }
            
            .sidebar-mobile.open {
                transform: translateX(0);
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 via-gray-100 to-gray-200 min-h-screen">

<!-- Mobile overlay -->
<div id="mobile-overlay" class="fixed inset-0 z-40 mobile-overlay opacity-0 pointer-events-none lg:hidden transition-opacity duration-300"></div>

<!-- Mobile menu button -->
<button id="mobile-menu-btn" class="fixed top-4 left-4 z-50 lg:hidden bg-surface text-white p-3 rounded-xl shadow-lg hover:bg-surface-light transition-all duration-200">
    <i class="fas fa-bars text-lg"></i>
</button>

<!-- Sidebar -->
<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-80 sidebar-gradient shadow-2xl sidebar-mobile lg:relative lg:translate-x-0 animate-slide-in-left">
    
    <!-- Header -->
    <div class="flex items-center justify-between p-6 border-b border-gray-700/30">
        <div class="flex items-center space-x-4 group cursor-pointer">
            <div class="relative">
                <div class="w-12 h-12 bg-gradient-to-r from-primary to-accent rounded-xl flex items-center justify-center logo-glow">
                    <i class="fas fa-leaf text-white text-xl"></i>
                </div>
                <div class="absolute -top-1 -right-1 w-4 h-4 bg-accent rounded-full animate-bounce-subtle"></div>
            </div>
            <div>
                <h1 class="text-2xl font-bold bg-gradient-to-r from-primary via-accent to-primary bg-clip-text text-transparent">
                    Jungle
                </h1>
                <p class="text-sm text-gray-400 font-medium">Restaurant Admin</p>
            </div>
        </div>
        
        <button id="close-sidebar" class="lg:hidden text-gray-400 hover:text-white p-2 rounded-lg hover:bg-surface-light transition-all duration-200">
            <i class="fas fa-times text-xl"></i>
        </button>
    </div>
    
    <!-- Navigation -->
    <nav class="px-4 py-6 space-y-6 overflow-y-auto h-full scrollbar-thin">
        
        <!-- Dashboard Section -->
        <div class="space-y-3">
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wider px-4 flex items-center">
                <div class="w-2 h-2 bg-primary rounded-full mr-2"></div>
                Tableau de bord
            </h2>
            <a href="dashboard.php" class="nav-item active-nav flex items-center px-4 py-4 text-white rounded-2xl transition-all duration-300 hover:shadow-xl group">
                <div class="flex items-center justify-center w-12 h-12 bg-white/20 rounded-xl mr-4 group-hover:bg-white/30 transition-all duration-300">
                    <i class="fas fa-chart-bar nav-icon text-lg"></i>
                </div>
                <div class="flex-1">
                    <span class="font-semibold text-base">Dashboard</span>
                    <p class="text-sm text-gray-200 opacity-90">Vue d'ensemble</p>
                </div>
                <div class="w-2 h-2 bg-white rounded-full opacity-60"></div>
            </a>
        </div>
        
        <!-- Restaurant Management -->
        <div class="space-y-3">
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wider px-4 flex items-center">
                <div class="w-2 h-2 bg-accent rounded-full mr-2"></div>
                Gestion Restaurant
            </h2>
            <div class="space-y-2">
                <a href="reservations.php" class="nav-item flex items-center px-4 py-4 text-gray-300 hover:bg-surface-light hover:text-white rounded-2xl transition-all duration-300 group hover:shadow-lg">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/5 rounded-xl mr-4 group-hover:bg-white/10 transition-all duration-300">
                        <i class="fas fa-calendar-check nav-icon text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <span class="font-medium text-base">Réservations</span>
                        <p class="text-sm text-gray-400 opacity-80">Gestion des réservations</p>
                    </div>
                    <i class="fas fa-chevron-right text-xs opacity-0 group-hover:opacity-60 transition-all duration-300"></i>
                </a>
                
                <a href="gestion_plats.php" class="nav-item flex items-center px-4 py-4 text-gray-300 hover:bg-surface-light hover:text-white rounded-2xl transition-all duration-300 group hover:shadow-lg">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/5 rounded-xl mr-4 group-hover:bg-white/10 transition-all duration-300">
                        <i class="fas fa-utensils nav-icon text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <span class="font-medium text-base">Menus</span>
                        <p class="text-sm text-gray-400 opacity-80">Gestion des menus</p>
                    </div>
                    <i class="fas fa-chevron-right text-xs opacity-0 group-hover:opacity-60 transition-all duration-300"></i>
                </a>
                
                <a href="categories_plats.php" class="nav-item flex items-center px-4 py-4 text-gray-300 hover:bg-surface-light hover:text-white rounded-2xl transition-all duration-300 group hover:shadow-lg">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/5 rounded-xl mr-4 group-hover:bg-white/10 transition-all duration-300">
                        <i class="fas fa-folder nav-icon text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <span class="font-medium text-base">Catégories</span>
                        <p class="text-sm text-gray-400 opacity-80">Catégories de plats</p>
                    </div>
                    <i class="fas fa-chevron-right text-xs opacity-0 group-hover:opacity-60 transition-all duration-300"></i>
                </a>
                
                <a href="commandes.php" class="nav-item flex items-center px-4 py-4 text-gray-300 hover:bg-surface-light hover:text-white rounded-2xl transition-all duration-300 group hover:shadow-lg">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/5 rounded-xl mr-4 group-hover:bg-white/10 transition-all duration-300">
                        <i class="fas fa-receipt nav-icon text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <span class="font-medium text-base">Commandes</span>
                        <p class="text-sm text-gray-400 opacity-80">Gestion des commandes</p>
                    </div>
                    <i class="fas fa-chevron-right text-xs opacity-0 group-hover:opacity-60 transition-all duration-300"></i>
                </a>
                
                <a href="recu.php" class="nav-item flex items-center px-4 py-4 text-gray-300 hover:bg-surface-light hover:text-white rounded-2xl transition-all duration-300 group hover:shadow-lg">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/5 rounded-xl mr-4 group-hover:bg-white/10 transition-all duration-300">
                        <i class="fas fa-file-invoice nav-icon text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <span class="font-medium text-base">Reçus</span>
                        <p class="text-sm text-gray-400 opacity-80">Imprimer les reçus</p>
                    </div>
                    <i class="fas fa-chevron-right text-xs opacity-0 group-hover:opacity-60 transition-all duration-300"></i>
                </a>
                
                <a href="horaires.php" class="nav-item flex items-center px-4 py-4 text-gray-300 hover:bg-surface-light hover:text-white rounded-2xl transition-all duration-300 group hover:shadow-lg">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/5 rounded-xl mr-4 group-hover:bg-white/10 transition-all duration-300">
                        <i class="fas fa-clock nav-icon text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <span class="font-medium text-base">Horaires</span>
                        <p class="text-sm text-gray-400 opacity-80">Horaires d'ouverture</p>
                    </div>
                    <i class="fas fa-chevron-right text-xs opacity-0 group-hover:opacity-60 transition-all duration-300"></i>
                </a>
            </div>
        </div>
        
        <!-- Administration Section -->
        <div class="space-y-3">
            <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wider px-4 flex items-center">
                <div class="w-2 h-2 bg-blue-500 rounded-full mr-2"></div>
                Administration
            </h2>
            <div class="space-y-2">
                <a href="gestion_stock.php" class="nav-item flex items-center px-4 py-4 text-gray-300 hover:bg-surface-light hover:text-white rounded-2xl transition-all duration-300 group hover:shadow-lg">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/5 rounded-xl mr-4 group-hover:bg-white/10 transition-all duration-300">
                        <i class="fas fa-boxes nav-icon text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <span class="font-medium text-base">Stocks</span>
                        <p class="text-sm text-gray-400 opacity-80">Gestion des stocks</p>
                    </div>
                    <i class="fas fa-chevron-right text-xs opacity-0 group-hover:opacity-60 transition-all duration-300"></i>
                </a>
                
                <a href="admin_gestion.php" class="nav-item flex items-center px-4 py-4 text-gray-300 hover:bg-surface-light hover:text-white rounded-2xl transition-all duration-300 group hover:shadow-lg">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/5 rounded-xl mr-4 group-hover:bg-white/10 transition-all duration-300">
                        <i class="fas fa-users-cog nav-icon text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <span class="font-medium text-base">Admins</span>
                        <p class="text-sm text-gray-400 opacity-80">Gestion des admins</p>
                    </div>
                    <i class="fas fa-chevron-right text-xs opacity-0 group-hover:opacity-60 transition-all duration-300"></i>
                </a>
                
                <a href="statistiques.php" class="nav-item flex items-center px-4 py-4 text-gray-300 hover:bg-surface-light hover:text-white rounded-2xl transition-all duration-300 group hover:shadow-lg">
                    <div class="flex items-center justify-center w-12 h-12 bg-white/5 rounded-xl mr-4 group-hover:bg-white/10 transition-all duration-300">
                        <i class="fas fa-chart-line nav-icon text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <span class="font-medium text-base">Statistiques</span>
                        <p class="text-sm text-gray-400 opacity-80">Analyses et rapports</p>
                    </div>
                    <i class="fas fa-chevron-right text-xs opacity-0 group-hover:opacity-60 transition-all duration-300"></i>
                </a>
            </div>
        </div>
        
        <!-- Divider -->
        <div class="relative px-4 py-4">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-700/50"></div>
            </div>
            <div class="relative flex justify-center">
                <div class="bg-surface px-4 flex items-center space-x-2">
                    <div class="w-2 h-2 bg-primary rounded-full animate-pulse"></div>
                    <div class="w-1 h-1 bg-accent rounded-full animate-pulse delay-100"></div>
                    <div class="w-2 h-2 bg-primary rounded-full animate-pulse delay-200"></div>
                </div>
            </div>
        </div>
        
        <!-- Logout Section -->
        <div class="space-y-3 pb-6">
            <a href="logout.php" class="nav-item danger-hover flex items-center px-4 py-4 text-gray-300 hover:text-white rounded-2xl transition-all duration-300 group hover:shadow-lg">
                <div class="flex items-center justify-center w-12 h-12 bg-white/5 rounded-xl mr-4 group-hover:bg-white/10 transition-all duration-300">
                    <i class="fas fa-sign-out-alt nav-icon text-lg"></i>
                </div>
                <div class="flex-1">
                    <span class="font-medium text-base">Déconnexion</span>
                    <p class="text-sm text-gray-400 opacity-80">Se déconnecter</p>
                </div>
                <i class="fas fa-chevron-right text-xs opacity-0 group-hover:opacity-60 transition-all duration-300"></i>
            </a>
        </div>
        
        <!-- Footer -->
        <div class="px-4 pb-4 text-center border-t border-gray-700/30 pt-6">
            <div class="glass-morphism rounded-xl p-4">
                <div class="text-xs text-gray-400">
                    <div class="flex items-center justify-center space-x-2 mb-2">
                        <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                        <span class="font-semibold text-green-400">Système en ligne</span>
                    </div>
                    <p class="font-medium text-gray-300">Version 2.0</p>
                    <p class="opacity-70">© 2024 Jungle Restaurant</p>
                </div>
            </div>
        </div>
    </nav>
</aside>


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
        }
    });
</script>

</body>
</html>