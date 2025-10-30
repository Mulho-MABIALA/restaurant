<?php
require_once '../config.php';

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $query = "
        SELECT jour, heure_ouverture, heure_fermeture, ferme
        FROM horaires_ouverture
        ORDER BY FIELD(jour, 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche')
    ";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur SQL horaires_ouverture : " . $e->getMessage());
    $results = [];
}
?>

<style>
    /* Animation pour les éléments */
    @keyframes slideUpFade {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes shimmer {
        0%, 100% {
            background-position: -1000px 0;
        }
        50% {
            background-position: 1000px 0;
        }
    }

    /* Classes Tailwind avancées */
    .newsletter-message.show {
        display: block;
        animation: slideUpFade 0.3s ease-out;
    }
    .newsletter-success {
        background: rgba(16, 185, 129, 0.15);
        color: #10b981;
        border-left: 4px solid #10b981;
    }
    .newsletter-error {
        background: rgba(239, 68, 68, 0.15);
        color: #ef4444;
        border-left: 4px solid #ef4444;
    }

    .schedule-open { color: #10b981; }
    .schedule-closed { color: #ef4444; }
</style>

<footer id="footer" class="bg-white text-gray-900 pt-16 pb-8 border-t-4" style="border-color: #CE8505;">
    <div class="container mx-auto px-4 md:px-6">
        <!-- Newsletter Section -->
        <div class="rounded-2xl p-8 md:p-12 mb-16 border-2 shadow-lg transition-all duration-300 hover:shadow-xl" style="background-color: #CE8505; border-color: #CE8505;">
            <div class="text-center max-w-2xl mx-auto">
                <h3 class="text-2xl md:text-3xl font-bold text-white mb-3 tracking-tight">📧 Restez connecté !</h3>
                <p class="text-white/90 text-base md:text-lg leading-relaxed mb-6">Recevez nos dernières offres et actualités directement dans votre boîte mail</p>

                <form id="newsletterForm" class="flex flex-col sm:flex-row gap-3 max-w-md mx-auto">
                    <input
                        type="email"
                        class="flex-1 px-5 py-3 rounded-lg bg-white text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-white/50 transition-all duration-300 font-medium"
                        placeholder="votre.email@exemple.com"
                        required
                        id="emailInput"
                    >
                    <button
                        type="submit"
                        class="px-8 py-3 rounded-lg bg-white text-white font-semibold shadow-lg transition-all duration-300 transform hover:scale-105 active:scale-95 whitespace-nowrap hover:shadow-xl"
                        style="background-color: #CE8505; color: #FFFFFF;"
                        id="submitBtn"
                    >
                        <span class="btn-text">S'abonner</span>
                    </button>
                </form>
                <div id="newsletterMessages" class="newsletter-message mt-4 px-5 py-3 rounded-lg text-sm md:text-base text-white"></div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-6 mb-12 pb-12 border-b-2" style="border-color: #CE8505;">
            <!-- Brand Section -->
            <div class="flex flex-col space-y-4">
                <div>
                    <h3 class="text-3xl md:text-4xl font-bold tracking-tight mb-2" style="color: #CE8505;">🍽️ Mulho</h3>
                    <p class="text-gray-600 text-sm leading-relaxed">Expériences culinaires exceptionnelles au cœur de Dakar</p>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-3" style="color: #CE8505;">📍 Adresse</h4>
                    <p class="text-gray-600 text-sm leading-relaxed space-y-1">
                        <span>Dakar, Medina</span><br>
                        <span>Rue 27x24</span><br>
                        <span>Sénégal</span>
                    </p>
                </div>
            </div>

            <!-- Contact Section -->
            <div class="flex flex-col space-y-4">
                <h4 class="text-sm font-bold text-gray-900 uppercase tracking-wider" style="color: #CE8505;">📞 Contact</h4>
                <div class="space-y-5">
                    <div>
                        <p class="text-gray-600 text-xs uppercase tracking-wider font-semibold mb-2">Téléphone</p>
                        <a
                            href="tel:787308706"
                            class="text-base font-medium transition-colors duration-300 inline-flex items-center group"
                            style="color: #CE8505;"
                        >
                            78 730 87 06
                            <span class="ml-1 opacity-0 group-hover:opacity-100 transform group-hover:translate-x-1 transition-all">→</span>
                        </a>
                    </div>
                    <div>
                        <p class="text-gray-600 text-xs uppercase tracking-wider font-semibold mb-2">Email</p>
                        <a
                            href="mailto:mulhomabiala29@gmail.com"
                            class="text-sm break-all font-medium transition-colors duration-300 inline-flex items-center group"
                            style="color: #CE8505;"
                        >
                            mulhomabiala29@gmail.com
                            <span class="ml-1 opacity-0 group-hover:opacity-100 transform group-hover:translate-x-1 transition-all text-xs">↗</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Schedule Section -->
            <div>
                <h4 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4" style="color: #CE8505;">🕐 Horaires</h4>
                <div class="space-y-2">
                    <?php if (!empty($results)): ?>
                        <?php foreach ($results as $row): ?>
                            <div class="flex justify-between items-center py-2.5 px-3 rounded-lg bg-gray-100 hover:bg-gray-200 transition-colors duration-300 border-2" style="border-color: #CE8505;">
                                <span class="text-gray-700 text-sm font-medium"><?= htmlspecialchars($row['jour']) ?></span>
                                <span class="text-xs font-semibold <?= $row['ferme'] == 1 ? 'schedule-closed' : 'schedule-open' ?>">
                                    <?php if ($row['ferme'] == 1): ?>
                                        Fermé
                                    <?php else: ?>
                                        <?= substr($row['heure_ouverture'], 0, 5) ?> - <?= substr($row['heure_fermeture'], 0, 5) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-gray-600 text-sm italic">Horaires non disponibles</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Social Networks Section -->
            <div>
                <h4 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4" style="color: #CE8505;">🌐 Suivez-nous</h4>
                <div class="flex flex-wrap gap-3">
                    <a
                        href="https://www.snapchat.com/add/yourusername"
                        class="w-10 h-10 md:w-11 md:h-11 rounded-lg text-white flex items-center justify-center transition-all duration-300 transform hover:scale-110 hover:shadow-lg shadow-md font-bold"
                        style="background-color: #CE8505;"
                        title="Snapchat"
                    >
                        <i class="fab fa-snapchat-ghost text-lg"></i>
                    </a>
                    <a
                        href="https://www.tiktok.com/@Ombrelumineuse"
                        class="w-10 h-10 md:w-11 md:h-11 rounded-lg bg-black hover:bg-gray-900 text-white flex items-center justify-center transition-all duration-300 transform hover:scale-110 hover:shadow-lg shadow-md font-bold"
                        title="TikTok"
                    >
                        <i class="fab fa-tiktok text-lg"></i>
                    </a>
                    <a
                        href="https://wa.me/+24205530852"
                        class="w-10 h-10 md:w-11 md:h-11 rounded-lg bg-green-500 hover:bg-green-600 text-white flex items-center justify-center transition-all duration-300 transform hover:scale-110 hover:shadow-lg shadow-md font-bold"
                        title="WhatsApp"
                    >
                        <i class="fab fa-whatsapp text-lg"></i>
                    </a>
                    <a
                        href="https://www.facebook.com/votreprofil"
                        class="w-10 h-10 md:w-11 md:h-11 rounded-lg bg-blue-600 hover:bg-blue-700 text-white flex items-center justify-center transition-all duration-300 transform hover:scale-110 hover:shadow-lg shadow-md font-bold"
                        title="Facebook"
                    >
                        <i class="fab fa-facebook-f text-lg"></i>
                    </a>
                    <a
                        href="https://www.instagram.com/votreprofil"
                        class="w-10 h-10 md:w-11 md:h-11 rounded-lg hover:opacity-90 text-white flex items-center justify-center transition-all duration-300 transform hover:scale-110 hover:shadow-lg shadow-md font-bold"
                        style="background-color: #CE8505;"
                        title="Instagram"
                    >
                        <i class="fab fa-instagram text-lg"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Footer Bottom / Copyright -->
        <div class="text-center py-8 border-t-2" style="border-color: #CE8505;">
            <p class="text-gray-700 text-sm leading-relaxed">
                © <span class="font-semibold text-gray-900"><?= date('Y') ?> Mulho</span> - Tous droits réservés
                <span class="text-gray-500 mx-2">|</span>
                Conçu par <a href="#" class="font-semibold transition-colors duration-300 hover:opacity-75" style="color: #CE8505;">Mulho - MABIALA</a>
            </p>
        </div>
    </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('newsletterForm');
    const emailInput = document.getElementById('emailInput');
    const submitBtn = document.getElementById('submitBtn');
    const messages = document.getElementById('newsletterMessages');

    function showMessage(text, type) {
        messages.className = `newsletter-message newsletter-${type} show`;
        messages.textContent = text;
        setTimeout(() => messages.classList.remove('show'), 5000);
    }

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const email = emailInput.value.trim();
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showMessage('Email invalide', 'error');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Envoi...';

        try {
            const response = await fetch('newsletter_subscribe.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email })
            });

            const result = await response.json();

            if (result.success) {
                showMessage(result.message, 'success');
                emailInput.value = '';
            } else {
                showMessage(result.message, 'error');
            }
        } catch (error) {
            showMessage('Erreur de connexion', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.querySelector('.btn-text').textContent = 'S\'abonner';
        }
    });
});
</script>
