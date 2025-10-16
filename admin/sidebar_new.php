<?php
// Vérifier si config.php a déjà été chargé
if (!isset($conn)) {
    require_once '../config.php';
}

// Vérifier si permissions.php a déjà été chargé
if (!function_exists('canAccess')) {
    require_once './permissions.php';
}

$adminId = $_SESSION['admin_id'] ?? null;
$userRole = '';

if ($adminId && isset($conn)) {
    try {
        $stmt = $conn->prepare("SELECT role FROM admin WHERE id = ?");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        $userRole = $admin['role'] ?? '';
    } catch (PDOException $e) {
        error_log("Erreur sidebar.php: " . $e->getMessage());
        $userRole = '';
    }
}

// Déterminer la page actuelle
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- Styles du Sidebar (à inclure dans le HEAD de votre page) -->
<style>
    .sidebar-glass {
        background: linear-gradient(180deg, #0f172a 0%, #111827 25%, #1f2937 75%, #111827 100%);
        position: relative;
    }

    .sidebar-glass::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, transparent 50%, rgba(245, 158, 11, 0.05) 100%);
        pointer-events: none;
    }

    .nav-item-sidebar {
        position: relative;
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .nav-item-sidebar::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(16, 185, 129, 0.15), transparent);
        transition: left 0.6s ease;
    }

    .nav-item-sidebar:hover::before {
        left: 100%;
    }

    .nav-item-sidebar::after {
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

    .nav-item-sidebar:hover::after {
        height: 70%;
    }

    .active-nav-sidebar {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(5, 150, 105, 0.3) 100%);
        border: 1px solid rgba(16, 185, 129, 0.4);
        box-shadow: 0 8px 32px rgba(16, 185, 129, 0.2);
    }

    .active-nav-sidebar::after {
        height: 70%;
    }

    /* Mode réduit du sidebar */
    #sidebar-restaurant {
        width: 280px;
        transition: width 0.3s ease;
    }

    #sidebar-restaurant.collapsed {
        width: 80px;
    }

    #sidebar-restaurant.collapsed .sidebar-text,
    #sidebar-restaurant.collapsed .sidebar-logo-text,
    #sidebar-restaurant.collapsed .sidebar-section-title,
    #sidebar-restaurant.collapsed .nav-description {
        display: none;
    }

    #sidebar-restaurant.collapsed .nav-item-sidebar {
        justify-content: center;
        padding: 1rem;
    }

    /* Responsive Mobile */
    @media (max-width: 1023px) {
        #sidebar-restaurant {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            transform: translateX(-100%);
            z-index: 9999;
            width: 280px !important;
        }

        #sidebar-restaurant.mobile-open {
            transform: translateX(0);
        }

        #sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9998;
        }

        #sidebar-overlay.active {
            display: block;
        }
    }

    /* Scrollbar personnalisée */
    .sidebar-scroll::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar-scroll::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 6px;
    }

    .sidebar-scroll::-webkit-scrollbar-thumb {
        background: linear-gradient(to bottom, rgba(16, 185, 129, 0.6), rgba(16, 185, 129, 0.3));
        border-radius: 6px;
    }

    .sidebar-scroll::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(to bottom, rgba(16, 185, 129, 0.8), rgba(16, 185, 129, 0.5));
    }

    /* Animations */
    @keyframes slideInFromLeft {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .sidebar-animate {
        animation: slideInFromLeft 0.4s ease-out;
    }
</style>

<!-- Overlay Mobile -->
<div id="sidebar-overlay" onclick="toggleSidebarMobile()"></div>

<!-- Bouton Menu Mobile (fixe en haut à gauche) -->
<button id="sidebar-mobile-btn"
        onclick="toggleSidebarMobile()"
        class="fixed top-4 left-4 z-[10000] lg:hidden bg-gray-900 text-white p-3 rounded-xl shadow-2xl hover:bg-gray-800 transition-all">
    <i class="fas fa-bars text-lg"></i>
</button>

<!-- Sidebar -->
<aside id="sidebar-restaurant" class="sidebar-glass shadow-2xl transition-all duration-300">
    <!-- Header -->
    <div class="flex items-center justify-between p-6 border-b border-gray-700/40">
        <!-- Logo -->
        <div class="flex items-center space-x-3 sidebar-logo-text">
            <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-xl flex items-center justify-center">
                <i class="fas fa-leaf text-white text-xl"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-white">Jungle</h1>
                <p class="text-xs text-gray-400">Restaurant Admin</p>
            </div>
        </div>

        <!-- Toggle Button Desktop -->
        <button onclick="toggleSidebarDesktop()"
                class="hidden lg:block text-gray-400 hover:text-white p-2 rounded-lg hover:bg-gray-700/50 transition-all">
            <i id="sidebar-toggle-icon" class="fas fa-angles-left text-lg"></i>
        </button>

        <!-- Close Button Mobile -->
        <button onclick="toggleSidebarMobile()"
                class="lg:hidden text-gray-400 hover:text-white p-2 rounded-lg hover:bg-gray-700/50 transition-all">
            <i class="fas fa-times text-lg"></i>
        </button>
    </div>

    <!-- Navigation -->
    <nav class="px-4 py-6 space-y-4 overflow-y-auto h-[calc(100vh-88px)] sidebar-scroll">

        <!-- Dashboard -->
        <?php if (canAccess($conn, $adminId, 'dashboard')): ?>
        <div class="space-y-2">
            <h2 class="text-xs font-semibold text-gray-400 uppercase px-3 mb-2 sidebar-section-title">Tableau de bord</h2>
            <a href="dashboard.php"
               class="nav-item-sidebar <?php echo ($currentPage === 'dashboard.php') ? 'active-nav-sidebar' : ''; ?> flex items-center px-3 py-3 text-gray-300 hover:text-white hover:bg-gray-700/30 rounded-xl transition-all group">
                <i class="fas fa-chart-bar text-emerald-500 text-lg mr-3"></i>
                <span class="sidebar-text font-medium">Dashboard</span>
            </a>
        </div>
        <?php endif; ?>

        <!-- Restaurant Management -->
        <?php if (anyVisible($conn, $adminId, ['reservations', 'commandes', 'gestion_plats', 'categories_plats'])): ?>
        <div class="space-y-2">
            <h2 class="text-xs font-semibold text-gray-400 uppercase px-3 mb-2 sidebar-section-title">Restaurant</h2>

            <?php if (canAccess($conn, $adminId, 'reservations')): ?>
            <a href="reservations.php"
               class="nav-item-sidebar <?php echo ($currentPage === 'reservations.php') ? 'active-nav-sidebar' : ''; ?> flex items-center px-3 py-3 text-gray-300 hover:text-white hover:bg-gray-700/30 rounded-xl transition-all group">
                <i class="fas fa-calendar-check text-blue-500 text-lg mr-3"></i>
                <span class="sidebar-text font-medium">Réservations</span>
            </a>
            <?php endif; ?>

            <?php if (canAccess($conn, $adminId, 'commandes')): ?>
            <a href="commandes.php"
               class="nav-item-sidebar <?php echo ($currentPage === 'commandes.php') ? 'active-nav-sidebar' : ''; ?> flex items-center px-3 py-3 text-gray-300 hover:text-white hover:bg-gray-700/30 rounded-xl transition-all group">
                <i class="fas fa-receipt text-purple-500 text-lg mr-3"></i>
                <span class="sidebar-text font-medium">Commandes</span>
            </a>
            <?php endif; ?>

            <?php if (canAccess($conn, $adminId, 'cuisine')): ?>
            <a href="cuisine.php"
               class="nav-item-sidebar <?php echo ($currentPage === 'cuisine.php') ? 'active-nav-sidebar' : ''; ?> flex items-center px-3 py-3 text-gray-300 hover:text-white hover:bg-gray-700/30 rounded-xl transition-all group">
                <i class="fas fa-fire text-orange-500 text-lg mr-3"></i>
                <span class="sidebar-text font-medium">Cuisine</span>
            </a>
            <?php endif; ?>

            <?php if (canAccess($conn, $adminId, 'gestion_plats')): ?>
            <a href="gestion_plats.php"
               class="nav-item-sidebar <?php echo ($currentPage === 'gestion_plats.php') ? 'active-nav-sidebar' : ''; ?> flex items-center px-3 py-3 text-gray-300 hover:text-white hover:bg-gray-700/30 rounded-xl transition-all group">
                <i class="fas fa-utensils text-yellow-500 text-lg mr-3"></i>
                <span class="sidebar-text font-medium">Menus</span>
            </a>
            <?php endif; ?>

            <?php if (canAccess($conn, $adminId, 'categories_plats')): ?>
            <a href="categories_plats.php"
               class="nav-item-sidebar <?php echo ($currentPage === 'categories_plats.php') ? 'active-nav-sidebar' : ''; ?> flex items-center px-3 py-3 text-gray-300 hover:text-white hover:bg-gray-700/30 rounded-xl transition-all group">
                <i class="fas fa-folder text-indigo-500 text-lg mr-3"></i>
                <span class="sidebar-text font-medium">Catégories</span>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Administration -->
        <?php if (anyVisible($conn, $adminId, ['gestion_stock', 'gestion_employe', 'admin_gestion', 'statistiques'])): ?>
        <div class="space-y-2">
            <h2 class="text-xs font-semibold text-gray-400 uppercase px-3 mb-2 sidebar-section-title">Administration</h2>

            <?php if (canAccess($conn, $adminId, 'gestion_stock')): ?>
            <a href="gestion_stock.php"
               class="nav-item-sidebar <?php echo ($currentPage === 'gestion_stock.php') ? 'active-nav-sidebar' : ''; ?> flex items-center px-3 py-3 text-gray-300 hover:text-white hover:bg-gray-700/30 rounded-xl transition-all group">
                <i class="fas fa-boxes text-cyan-500 text-lg mr-3"></i>
                <span class="sidebar-text font-medium">Stocks</span>
            </a>
            <?php endif; ?>

            <?php if (canAccess($conn, $adminId, 'gestion_employe')): ?>
            <a href="gestion_employe.php"
               class="nav-item-sidebar <?php echo ($currentPage === 'gestion_employe.php') ? 'active-nav-sidebar' : ''; ?> flex items-center px-3 py-3 text-gray-300 hover:text-white hover:bg-gray-700/30 rounded-xl transition-all group">
                <i class="fas fa-users text-pink-500 text-lg mr-3"></i>
                <span class="sidebar-text font-medium">Employés</span>
            </a>
            <?php endif; ?>

            <?php if (canAccess($conn, $adminId, 'admin_gestion')): ?>
            <a href="admin_gestion.php"
               class="nav-item-sidebar <?php echo ($currentPage === 'admin_gestion.php') ? 'active-nav-sidebar' : ''; ?> flex items-center px-3 py-3 text-gray-300 hover:text-white hover:bg-gray-700/30 rounded-xl transition-all group">
                <i class="fas fa-users-cog text-red-500 text-lg mr-3"></i>
                <span class="sidebar-text font-medium">Admins</span>
            </a>
            <?php endif; ?>

            <?php if (canAccess($conn, $adminId, 'statistiques')): ?>
            <a href="statistiques.php"
               class="nav-item-sidebar <?php echo ($currentPage === 'statistiques.php') ? 'active-nav-sidebar' : ''; ?> flex items-center px-3 py-3 text-gray-300 hover:text-white hover:bg-gray-700/30 rounded-xl transition-all group">
                <i class="fas fa-chart-line text-green-500 text-lg mr-3"></i>
                <span class="sidebar-text font-medium">Statistiques</span>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Divider -->
        <div class="border-t border-gray-700/40 my-4"></div>

        <!-- Déconnexion -->
        <a href="logout.php"
           class="nav-item-sidebar flex items-center px-3 py-3 text-gray-300 hover:text-red-400 hover:bg-red-500/10 rounded-xl transition-all group">
            <i class="fas fa-sign-out-alt text-red-500 text-lg mr-3"></i>
            <span class="sidebar-text font-medium">Déconnexion</span>
        </a>

    </nav>
</aside>

<!-- Script du Sidebar -->
<script>
    // Toggle Mobile
    function toggleSidebarMobile() {
        const sidebar = document.getElementById('sidebar-restaurant');
        const overlay = document.getElementById('sidebar-overlay');

        sidebar.classList.toggle('mobile-open');
        overlay.classList.toggle('active');
        document.body.classList.toggle('overflow-hidden');
    }

    // Toggle Desktop (Collapse/Expand)
    function toggleSidebarDesktop() {
        const sidebar = document.getElementById('sidebar-restaurant');
        const icon = document.getElementById('sidebar-toggle-icon');

        sidebar.classList.toggle('collapsed');

        // Changer l'icône
        if (sidebar.classList.contains('collapsed')) {
            icon.classList.remove('fa-angles-left');
            icon.classList.add('fa-angles-right');
        } else {
            icon.classList.remove('fa-angles-right');
            icon.classList.add('fa-angles-left');
        }

        // Sauvegarder l'état dans localStorage
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    }

    // Restaurer l'état au chargement
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar-restaurant');
        const icon = document.getElementById('sidebar-toggle-icon');
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';

        if (isCollapsed) {
            sidebar.classList.add('collapsed');
            icon.classList.remove('fa-angles-left');
            icon.classList.add('fa-angles-right');
        }
    });

    // Fermer sidebar mobile lors du redimensionnement
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 1024) {
            const sidebar = document.getElementById('sidebar-restaurant');
            const overlay = document.getElementById('sidebar-overlay');

            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
            document.body.classList.remove('overflow-hidden');
        }
    });
</script>
