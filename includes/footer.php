<?php

require_once 'config.php';

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Requête pour récupérer les horaires d'ouverture/fermeture par jour
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
    $results = []; // Valeur de repli
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
      <link href="assets/img/favicon.png" rel="icon">
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Inter:wght@100;200;300;400;500;600;700;800;900&family=Amatic+SC:wght@400;700&display=swap" rel="stylesheet">

    <!-- CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/aos/aos.css" rel="stylesheet">
    <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
    <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<style>
    /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2rem;
            }
            
            .col-lg-5, .col-lg-7, .col-lg-4, .col-lg-8 {
                flex: 0 0 100%;
                margin-bottom: 2rem;
            }
            
            main.main {
                margin-top: 70px;
            }

            .footer-content {
                grid-template-columns: 1fr !important;
                gap: 30px !important;
            }

            .newsletter-section {
                flex-direction: column !important;
                text-align: center;
            }

            .newsletter-form {
                margin-top: 20px;
            }
        }

        /* Footer moderne style */
        .footer {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: white;
            padding: 60px 0 0;
            position: relative;
        }

        .footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, #ec4899, transparent);
        }

        /* Newsletter section moderne */
        .newsletter-section {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border-radius: 16px;
            padding: 40px;
            margin-bottom: 50px;
            border: 1px solid rgba(236, 72, 153, 0.2);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .newsletter-content h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: white;
        }

        .newsletter-content p {
            color: #94a3b8;
            margin: 0;
            font-size: 0.95rem;
        }

        .newsletter-form {
            display: flex;
            gap: 10px;
            min-width: 350px;
        }

        .newsletter-input {
            flex: 1;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            padding: 12px 16px;
            color: white;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .newsletter-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .newsletter-input:focus {
            outline: none;
            border-color: #ec4899;
            box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.1);
            background: rgba(255, 255, 255, 0.12);
        }

        .newsletter-btn {
            background: linear-gradient(135deg, #ec4899, #f97316);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .newsletter-btn:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 8px 25px rgba(236, 72, 153, 0.3);
        }

        .newsletter-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Messages d'état */
        .newsletter-message {
            margin-top: 15px;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.9rem;
            text-align: center;
            transition: all 0.3s ease;
            opacity: 0;
            transform: translateY(-10px);
        }

        .newsletter-message.show {
            opacity: 1;
            transform: translateY(0);
        }

        .newsletter-success {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .newsletter-error {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .newsletter-info {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        /* Loader */
        .newsletter-loader {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Contenu principal du footer */
        .footer-content {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 50px;
            margin-bottom: 40px;
        }

        .footer-section h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .footer-section p, .footer-section li {
            color: #94a3b8;
            line-height: 1.6;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .footer-section a {
            color: #ec4899;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-section a:hover {
            color: #f97316;
        }

        /* Brand section */
        .brand-section {
            margin-bottom: 30px;
        }

        .brand-section h3 {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #ec4899, #f97316);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
        }

        .brand-section p {
            color: #94a3b8;
            font-size: 0.95rem;
            line-height: 1.6;
            max-width: 300px;
        }

        /* Horaires avec style moderne */
        .schedule-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .schedule-item:last-child {
            border-bottom: none;
        }

        .schedule-day {
            font-weight: 500;
            color: #e2e8f0;
        }

        .schedule-time {
            font-size: 0.85rem;
        }

        .schedule-open {
            color: #10b981;
        }

        .schedule-closed {
            color: #ef4444;
        }

        /* Réseaux sociaux modernes */
        .social-links {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }

        .social-links a {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            color: white;
        }

        .social-links a:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        /* Couleurs spécifiques */
        .social-snapchat { background: #FFFC00; color: #333; }
        .social-tiktok { background: #000; border: 2px solid #ff0050; }
        .social-whatsapp { background: #25D366; }
        .social-facebook { background: #4267B2; }
        .social-instagram { background: radial-gradient(circle at 30% 107%, #fdf497 0%, #fdf497 5%, #fd5949 45%, #d6249f 60%, #285AEB 90%); }

        /* Pied de page */
        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 30px 0;
            text-align: center;
        }

        .footer-bottom p {
            color: #64748b;
            margin: 0;
            font-size: 0.9rem;
        }

        .footer-bottom .sitename {
            color: #ec4899;
            font-weight: 600;
        }

        .footer-bottom a {
            color: #ec4899;
            text-decoration: none;
        }

        .footer-bottom a:hover {
            color: #f97316;
        }

        #preloader {
            display: none !important;
        }
</style>
<body>
    <!-- Footer -->
    <footer id="footer" class="footer">
        <div class="container">
            <!-- Newsletter Section -->
            <div class="newsletter-section">
                <div class="newsletter-content">
                    <h3>Restez connecté !</h3>
                    <p>Abonnez-vous à notre newsletter pour recevoir nos dernières actualités et offres spéciales.</p>
                </div>
                <form id="newsletterForm" class="newsletter-form">
                    <input type="email" 
                           class="newsletter-input" 
                           placeholder="Votre adresse email..." 
                           required 
                           id="emailInput">
                    <button type="submit" class="newsletter-btn" id="submitBtn">
                        <span class="btn-text">S'abonner</span>
                        <span class="btn-loader" style="display: none;">
                            <span class="newsletter-loader"></span>
                        </span>
                    </button>
                </form>
            </div>
            
            <!-- Messages de statut -->
            <div id="newsletterMessages"></div>

            <!-- Contenu principal -->
            <div class="footer-content">
                <!-- Brand + Description -->
                <div class="footer-section">
                    <div class="brand-section">
                        <h3>Mulho</h3>
                        <p>Nous créons des expériences exceptionnelles pour nos clients avec passion et professionnalisme.</p>
                    </div>
                    
                    <h4>Notre Adresse</h4>
                    <p><i class="fas fa-map-marker-alt" style="color: #ec4899; margin-right: 8px;"></i>Dakar, Medina</p>
                    <p><i class="fas fa-road" style="color: #ec4899; margin-right: 8px;"></i>Rue 27x24</p>
                    <p><i class="fas fa-flag" style="color: #ec4899; margin-right: 8px;"></i>Sénégal</p>
                </div>

                <!-- Contact -->
                <div class="footer-section">
                    <h4>Contact</h4>
                    <p><strong>Mobile :</strong></p>
                    <p><i class="fas fa-phone" style="color: #ec4899; margin-right: 8px;"></i><a href="tel:787308706">78 730 87 06</a></p>
                    
                    <p style="margin-top: 15px;"><strong>Email :</strong></p>
                    <p><i class="fas fa-envelope" style="color: #ec4899; margin-right: 8px;"></i><a href="mailto:mulhomabiala29@gmail.com?subject=Contact depuis le site">mulhomabiala29@gmail.com</a></p>
                </div>

                <!-- Horaires -->
                <div class="footer-section">
                    <h4>Heures d'ouverture</h4>
                    <div class="schedule">
                        <?php if (!empty($results)): ?>
                            <?php foreach ($results as $row): ?>
                                <div class="schedule-item">
                                    <span class="schedule-day"><?= htmlspecialchars($row['jour']) ?></span>
                                    <span class="schedule-time <?= $row['ferme'] == 1 ? 'schedule-closed' : 'schedule-open' ?>">
                                        <?php if ($row['ferme'] == 1): ?>
                                            Fermé
                                        <?php else: ?>
                                            <?= htmlspecialchars(substr($row['heure_ouverture'], 0, 5)) ?>-<?= htmlspecialchars(substr($row['heure_fermeture'], 0, 5)) ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>Aucun horaire trouvé.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Réseaux sociaux -->
                <div class="footer-section">
                    <h4>Suivez-nous</h4>
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
                        <a href="https://www.facebook.com/votreprofil" class="social-facebook" target="_blank" title="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://www.instagram.com/votreprofil" class="social-instagram" target="_blank" title="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                    </div>
                    
                    <div style="background: rgba(236, 72, 153, 0.1); padding: 15px; border-radius: 8px; border-left: 3px solid #ec4899;">
                        <p style="margin: 0; font-size: 0.85rem; color: #e2e8f0;">
                            <i class="fas fa-users" style="color: #ec4899; margin-right: 6px;"></i>
                            <strong>Rejoignez notre communauté</strong><br>
                            <span style="color: #94a3b8;">Des milliers de clients satisfaits</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Copyright -->
            <div class="footer-bottom">
                <p>© Copyright <span class="sitename">Mulho</span> - Tous droits réservés | Conçu par <a href="#">Mulho - MABIALA</a></p>
            </div>
        </div>
    </footer>

    <script>
    // Configuration
    const NEWSLETTER_CONFIG = {
        apiUrl: 'newsletter_subscribe.php',
        maxRetries: 3,
        retryDelay: 1000
    };

    // Classe pour gérer la newsletter
    class NewsletterManager {
        constructor() {
            this.form = document.getElementById('newsletterForm');
            this.emailInput = document.getElementById('emailInput');
            this.submitBtn = document.getElementById('submitBtn');
            this.btnText = this.submitBtn.querySelector('.btn-text');
            this.btnLoader = this.submitBtn.querySelector('.btn-loader');
            this.messagesContainer = document.getElementById('newsletterMessages');
            
            this.init();
        }

        init() {
            this.form.addEventListener('submit', (e) => this.handleSubmit(e));
            
            // Validation en temps réel
            this.emailInput.addEventListener('input', () => this.validateInput());
            this.emailInput.addEventListener('blur', () => this.validateInput());
        }

        validateInput() {
            const email = this.emailInput.value.trim();
            const isValid = this.isValidEmail(email);
            
            if (email && !isValid) {
                this.emailInput.style.borderColor = '#ef4444';
            } else {
                this.emailInput.style.borderColor = '';
            }
            
            return isValid;
        }

        isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        setLoading(loading) {
            this.submitBtn.disabled = loading;
            
            if (loading) {
                this.btnText.style.display = 'none';
                this.btnLoader.style.display = 'block';
            } else {
                this.btnText.style.display = 'block';
                this.btnLoader.style.display = 'none';
            }
        }

        showMessage(message, type = 'info') {
            // Supprimer les anciens messages
            this.messagesContainer.innerHTML = '';
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `newsletter-message newsletter-${type}`;
            
            const icon = {
                success: 'fas fa-check-circle',
                error: 'fas fa-exclamation-triangle',
                info: 'fas fa-info-circle'
            }[type] || 'fas fa-info-circle';
            
            messageDiv.innerHTML = `
                <i class="${icon}" style="margin-right: 8px;"></i>
                ${message}
            `;
            
            this.messagesContainer.appendChild(messageDiv);
            
            // Animation d'apparition
            setTimeout(() => {
                messageDiv.classList.add('show');
            }, 100);
            
            // Masquer après délai (sauf erreurs)
            if (type !== 'error') {
                setTimeout(() => {
                    this.hideMessage(messageDiv);
                }, 5000);
            }
        }

        hideMessage(messageDiv) {
            messageDiv.classList.remove('show');
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.parentNode.removeChild(messageDiv);
                }
            }, 300);
        }

        async handleSubmit(e) {
            e.preventDefault();
            
            const email = this.emailInput.value.trim();
            
            // Validation
            if (!email) {
                this.showMessage('Veuillez saisir votre adresse email', 'error');
                this.emailInput.focus();
                return;
            }
            
            if (!this.isValidEmail(email)) {
                this.showMessage('Veuillez saisir une adresse email valide', 'error');
                this.emailInput.focus();
                return;
            }
            
            // Envoyer la requête
            await this.subscribe(email);
        }

        async subscribe(email, retryCount = 0) {
            this.setLoading(true);
            
            try {
                const response = await fetch(NEWSLETTER_CONFIG.apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ email: email })
                });

                if (!response.ok) {
                    throw new Error(`Erreur HTTP: ${response.status}`);
                }

                const result = await response.json();
                
                if (result.success) {
                    this.showMessage(result.message, 'success');
                    this.emailInput.value = '';
                    
                    // Analytics/tracking (optionnel)
                    if (typeof gtag !== 'undefined') {
                        gtag('event', 'newsletter_subscription', {
                            'event_category': 'engagement',
                            'event_label': 'footer_newsletter'
                        });
                    }
                } else {
                    this.showMessage(result.message, 'error');
                }
                
            } catch (error) {
                console.error('Erreur newsletter:', error);
                
                // Retry logic
                if (retryCount < NEWSLETTER_CONFIG.maxRetries) {
                    setTimeout(() => {
                        this.subscribe(email, retryCount + 1);
                    }, NEWSLETTER_CONFIG.retryDelay * (retryCount + 1));
                    
                    this.showMessage(
                        `Tentative ${retryCount + 1}/${NEWSLETTER_CONFIG.maxRetries + 1}...`, 
                        'info'
                    );
                } else {
                    this.showMessage(
                        'Erreur de connexion. Veuillez réessayer plus tard.', 
                        'error'
                    );
                }
            } finally {
                this.setLoading(false);
            }
        }
    }

    // Initialisation quand le DOM est prêt
    document.addEventListener('DOMContentLoaded', function() {
        new NewsletterManager();
    });

    // Fallback pour les anciens navigateurs
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            new NewsletterManager();
        });
    } else {
        new NewsletterManager();
    }
    </script>
</body>
</html>