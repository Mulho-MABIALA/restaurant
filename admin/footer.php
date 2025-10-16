<!-- Footer Minimaliste -->
<footer class="mt-auto bg-gradient-to-br from-surface via-surface-light to-surface border-t border-gray-700/40 relative overflow-hidden">
    <!-- Floating decorative elements -->
    <div class="absolute inset-0 pointer-events-none opacity-5">
        <div class="absolute top-1/4 left-1/4 w-32 h-32 bg-primary rounded-full blur-3xl animate-float"></div>
        <div class="absolute bottom-1/4 right-1/4 w-40 h-40 bg-accent rounded-full blur-3xl animate-float" style="animation-delay: 2s"></div>
    </div>

    <div class="container mx-auto px-6 py-6 relative z-10">
        <!-- Divider avec points -->
        <div class="relative pb-4">
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

        <!-- Bottom Bar Simplifié -->
        <div class="flex flex-col md:flex-row items-center justify-between space-y-3 md:space-y-0 pt-4">
            <div class="text-center md:text-left">
                <p class="text-sm text-gray-400">
                    © <?php echo date('Y'); ?> <span class="font-semibold text-gray-300">Jungle Restaurant</span>. Tous droits réservés.
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    Version <span class="font-medium text-primary">2.0</span> - Système de gestion administratif
                </p>
            </div>

            <!-- Social Links -->
            <div class="flex items-center space-x-3">
                <a href="#" class="w-9 h-9 bg-white/5 hover:bg-primary/20 rounded-lg flex items-center justify-center transition-all duration-300 group border border-gray-700/50 hover:border-primary/50">
                    <i class="fab fa-facebook-f text-gray-400 group-hover:text-primary text-sm transition-colors"></i>
                </a>
                <a href="#" class="w-9 h-9 bg-white/5 hover:bg-primary/20 rounded-lg flex items-center justify-center transition-all duration-300 group border border-gray-700/50 hover:border-primary/50">
                    <i class="fab fa-instagram text-gray-400 group-hover:text-primary text-sm transition-colors"></i>
                </a>
                <a href="#" class="w-9 h-9 bg-white/5 hover:bg-primary/20 rounded-lg flex items-center justify-center transition-all duration-300 group border border-gray-700/50 hover:border-primary/50">
                    <i class="fab fa-twitter text-gray-400 group-hover:text-primary text-sm transition-colors"></i>
                </a>
            </div>
        </div>
    </div>
</footer>

<style>
    /* Styles déjà définis dans sidebar.php, ajout uniquement si nécessaire */
    .animate-float {
        animation: float 6s ease-in-out infinite;
    }

    @keyframes float {
        0%, 100% { transform: translateY(0px) translateX(0px); }
        50% { transform: translateY(-20px) translateX(10px); }
    }

    .animate-fade-in {
        animation: fadeIn 0.6s ease-out;
    }

    @keyframes fadeIn {
        0% { opacity: 0; transform: translateY(10px); }
        100% { opacity: 1; transform: translateY(0); }
    }

    .animate-pulse-soft {
        animation: pulseSoft 2s ease-in-out infinite;
    }

    @keyframes pulseSoft {
        0%, 100% { opacity: 0.6; }
        50% { opacity: 1; }
    }

    .logo-glow {
        animation: glow 4s ease-in-out infinite alternate;
    }

    @keyframes glow {
        0% { box-shadow: 0 0 10px rgba(16, 185, 129, 0.3); }
        100% { box-shadow: 0 0 25px rgba(16, 185, 129, 0.6), 0 0 40px rgba(16, 185, 129, 0.3); }
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
        animation: pulseSoft 2s ease-in-out infinite;
    }
</style>
