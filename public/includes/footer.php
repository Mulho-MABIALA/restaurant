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
    .footer {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        color: white;
        padding: 60px 0 0;
        border-top: 1px solid rgba(236, 72, 153, 0.3);
    }

    .newsletter-section {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        padding: 30px;
        margin-bottom: 40px;
        text-align: center;
    }

    .newsletter-section h3 {
        font-size: 1.3rem;
        margin-bottom: 10px;
    }

    .newsletter-form {
        display: flex;
        gap: 10px;
        max-width: 500px;
        margin: 20px auto 0;
    }

    .newsletter-input {
        flex: 1;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 8px;
        padding: 12px 16px;
        color: white;
    }

    .newsletter-input:focus {
        outline: none;
        border-color: #ec4899;
    }

    .newsletter-btn {
        background: linear-gradient(135deg, #ec4899, #f97316);
        color: white;
        border: none;
        border-radius: 8px;
        padding: 12px 24px;
        font-weight: 600;
        cursor: pointer;
        transition: transform 0.3s;
    }

    .newsletter-btn:hover:not(:disabled) {
        transform: translateY(-2px);
    }

    .newsletter-message {
        margin-top: 15px;
        padding: 12px;
        border-radius: 8px;
        font-size: 0.9rem;
        display: none;
    }

    .newsletter-message.show { display: block; }
    .newsletter-success { background: rgba(16, 185, 129, 0.2); color: #10b981; }
    .newsletter-error { background: rgba(239, 68, 68, 0.2); color: #ef4444; }

    .footer-content {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 40px;
        margin-bottom: 30px;
    }

    .footer-section h4 {
        font-size: 1.1rem;
        margin-bottom: 15px;
        color: white;
    }

    .footer-section p, .footer-section li {
        color: #94a3b8;
        line-height: 1.8;
        margin-bottom: 8px;
        font-size: 0.9rem;
    }

    .footer-section a {
        color: #ec4899;
        text-decoration: none;
    }

    .footer-section a:hover {
        color: #f97316;
    }

    .brand-section h3 {
        font-size: 1.8rem;
        font-weight: 700;
        background: linear-gradient(135deg, #ec4899, #f97316);
        background-clip: text;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 10px;
    }

    .schedule-item {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .schedule-open { color: #10b981; }
    .schedule-closed { color: #ef4444; }

    .social-links {
        display: flex;
        gap: 10px;
        margin: 15px 0;
    }

    .social-links a {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.3s;
    }

    .social-links a:hover {
        transform: translateY(-3px);
    }

    .social-snapchat { background: #FFFC00; color: #333; }
    .social-tiktok { background: #000; }
    .social-whatsapp { background: #25D366; }
    .social-facebook { background: #4267B2; }
    .social-instagram { background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%); }

    .footer-bottom {
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        padding: 20px 0;
        text-align: center;
        color: #64748b;
        font-size: 0.85rem;
    }

    @media (max-width: 768px) {
        .footer-content {
            grid-template-columns: 1fr;
            gap: 30px;
        }
        .newsletter-form {
            flex-direction: column;
        }
    }
</style>

<footer id="footer" class="footer">
    <div class="container">
        <!-- Newsletter -->
        <div class="newsletter-section">
            <h3>📧 Restez connecté !</h3>
            <p style="color: #94a3b8;">Recevez nos dernières offres et actualités</p>
            <form id="newsletterForm" class="newsletter-form">
                <input type="email" class="newsletter-input" placeholder="Votre email..." required id="emailInput">
                <button type="submit" class="newsletter-btn" id="submitBtn">
                    <span class="btn-text">S'abonner</span>
                </button>
            </form>
            <div id="newsletterMessages" class="newsletter-message"></div>
        </div>

        <!-- Contenu principal -->
        <div class="footer-content">
            <!-- Brand -->
            <div class="footer-section">
                <div class="brand-section">
                    <h3>🍽️ Mulho</h3>
                    <p>Expériences culinaires exceptionnelles</p>
                </div>
                <h4>📍 Adresse</h4>
                <p>Dakar, Medina<br>Rue 27x24<br>Sénégal</p>
            </div>

            <!-- Contact -->
            <div class="footer-section">
                <h4>📞 Contact</h4>
                <p><strong>Téléphone:</strong><br>
                <a href="tel:787308706">78 730 87 06</a></p>
                <p style="margin-top: 15px;"><strong>Email:</strong><br>
                <a href="mailto:mulhomabiala29@gmail.com">mulhomabiala29@gmail.com</a></p>
            </div>

            <!-- Horaires -->
            <div class="footer-section">
                <h4>🕐 Horaires</h4>
                <?php if (!empty($results)): ?>
                    <?php foreach ($results as $row): ?>
                        <div class="schedule-item">
                            <span><?= htmlspecialchars($row['jour']) ?></span>
                            <span class="<?= $row['ferme'] == 1 ? 'schedule-closed' : 'schedule-open' ?>">
                                <?php if ($row['ferme'] == 1): ?>
                                    Fermé
                                <?php else: ?>
                                    <?= substr($row['heure_ouverture'], 0, 5) ?>-<?= substr($row['heure_fermeture'], 0, 5) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Horaires non disponibles</p>
                <?php endif; ?>
            </div>

            <!-- Réseaux sociaux -->
            <div class="footer-section">
                <h4>🌐 Suivez-nous</h4>
                <div class="social-links">
                    <a href="https://www.snapchat.com/add/yourusername" class="social-snapchat" title="Snapchat">
                        <i class="fab fa-snapchat-ghost"></i>
                    </a>
                    <a href="https://www.tiktok.com/@Ombrelumineuse" class="social-tiktok" title="TikTok">
                        <i class="fab fa-tiktok"></i>
                    </a>
                    <a href="https://wa.me/+24205530852" class="social-whatsapp" title="WhatsApp">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                    <a href="https://www.facebook.com/votreprofil" class="social-facebook" title="Facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="https://www.instagram.com/votreprofil" class="social-instagram" title="Instagram">
                        <i class="fab fa-instagram"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Copyright -->
        <div class="footer-bottom">
            <p>© <?= date('Y') ?> <strong style="color: #ec4899;">Mulho</strong> - Tous droits réservés | Conçu par <a href="#">Mulho - MABIALA</a></p>
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
