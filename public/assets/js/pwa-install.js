/**
 * Bannière d'installation PWA
 * Gère l'affichage du prompt d'installation et le tracking
 */

class PWAInstallManager {
    constructor() {
        this.deferredPrompt = null;
        this.isInstalled = false;
        this.installButton = null;
        this.banner = null;
    }

    /**
     * Initialiser le gestionnaire d'installation
     */
    init() {
        // Vérifier si déjà installé
        this.checkIfInstalled();

        // Écouter l'événement beforeinstallprompt
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            this.deferredPrompt = e;
            console.log('PWA: Prompt d\'installation disponible');

            // Afficher la bannière si pas encore installé
            if (!this.isInstalled) {
                this.showInstallBanner();
            }
        });

        // Écouter l'installation
        window.addEventListener('appinstalled', () => {
            console.log('PWA: Application installée');
            this.onInstalled();
        });

        // Créer la bannière
        this.createBanner();
    }

    /**
     * Vérifier si l'app est déjà installée
     */
    checkIfInstalled() {
        // Mode standalone = installé
        if (window.matchMedia('(display-mode: standalone)').matches) {
            this.isInstalled = true;
            console.log('PWA: Application déjà installée');
            return true;
        }

        // iOS Safari
        if (window.navigator.standalone === true) {
            this.isInstalled = true;
            console.log('PWA: Installé sur iOS');
            return true;
        }

        // Vérifier localStorage (si utilisateur a déjà installé)
        if (localStorage.getItem('pwa_installed') === 'true') {
            this.isInstalled = true;
            return true;
        }

        return false;
    }

    /**
     * Créer la bannière d'installation
     */
    createBanner() {
        // Ne pas créer si déjà installé
        if (this.isInstalled) return;

        const banner = document.createElement('div');
        banner.id = 'pwa-install-banner';
        banner.className = 'pwa-install-banner hidden';
        banner.innerHTML = `
            <div class="pwa-banner-content">
                <div class="pwa-banner-icon">
                    <img src="/public/assets/img/logo.png" alt="Restaurant Mulho" width="48" height="48">
                </div>
                <div class="pwa-banner-text">
                    <div class="pwa-banner-title">Installer Restaurant Mulho</div>
                    <div class="pwa-banner-description">
                        Accès rapide, notifications et commande hors ligne
                    </div>
                </div>
                <div class="pwa-banner-actions">
                    <button class="pwa-btn-install" id="pwa-install-button">
                        Installer
                    </button>
                    <button class="pwa-btn-close" id="pwa-banner-close">
                        ✕
                    </button>
                </div>
            </div>
        `;

        // Styles inline pour garantir l'affichage
        const style = document.createElement('style');
        style.textContent = `
            .pwa-install-banner {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 16px;
                box-shadow: 0 -4px 12px rgba(0,0,0,0.15);
                z-index: 9999;
                transform: translateY(100%);
                transition: transform 0.3s ease-out;
            }

            .pwa-install-banner.show {
                transform: translateY(0);
            }

            .pwa-install-banner.hidden {
                display: none;
            }

            .pwa-banner-content {
                display: flex;
                align-items: center;
                gap: 12px;
                max-width: 800px;
                margin: 0 auto;
            }

            .pwa-banner-icon img {
                width: 48px;
                height: 48px;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            }

            .pwa-banner-text {
                flex: 1;
            }

            .pwa-banner-title {
                font-weight: 600;
                font-size: 16px;
                margin-bottom: 4px;
            }

            .pwa-banner-description {
                font-size: 13px;
                opacity: 0.9;
            }

            .pwa-banner-actions {
                display: flex;
                gap: 8px;
            }

            .pwa-btn-install {
                background: white;
                color: #667eea;
                border: none;
                padding: 10px 20px;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                transition: transform 0.2s;
            }

            .pwa-btn-install:hover {
                transform: scale(1.05);
            }

            .pwa-btn-close {
                background: rgba(255,255,255,0.2);
                color: white;
                border: none;
                width: 32px;
                height: 32px;
                border-radius: 50%;
                cursor: pointer;
                font-size: 18px;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: background 0.2s;
            }

            .pwa-btn-close:hover {
                background: rgba(255,255,255,0.3);
            }

            @media (max-width: 640px) {
                .pwa-banner-content {
                    flex-wrap: wrap;
                }

                .pwa-banner-description {
                    display: none;
                }

                .pwa-btn-install {
                    padding: 8px 16px;
                    font-size: 14px;
                }
            }
        `;

        document.head.appendChild(style);
        document.body.appendChild(banner);

        this.banner = banner;

        // Event listeners
        document.getElementById('pwa-install-button').addEventListener('click', () => {
            this.promptInstall();
        });

        document.getElementById('pwa-banner-close').addEventListener('click', () => {
            this.dismissBanner();
        });
    }

    /**
     * Afficher la bannière
     */
    showInstallBanner() {
        // Ne pas afficher si déjà fermée dans cette session
        if (sessionStorage.getItem('pwa_banner_dismissed') === 'true') {
            return;
        }

        // Attendre 3 secondes avant d'afficher
        setTimeout(() => {
            if (this.banner) {
                this.banner.classList.remove('hidden');
                setTimeout(() => {
                    this.banner.classList.add('show');
                }, 10);
            }
        }, 3000);
    }

    /**
     * Fermer la bannière
     */
    dismissBanner() {
        if (this.banner) {
            this.banner.classList.remove('show');
            setTimeout(() => {
                this.banner.classList.add('hidden');
            }, 300);

            // Mémoriser pour cette session
            sessionStorage.setItem('pwa_banner_dismissed', 'true');
        }
    }

    /**
     * Afficher le prompt d'installation
     */
    async promptInstall() {
        if (!this.deferredPrompt) {
            console.log('PWA: Prompt non disponible');
            this.showIOSInstructions();
            return;
        }

        // Afficher le prompt natif
        this.deferredPrompt.prompt();

        // Attendre le choix de l'utilisateur
        const { outcome } = await this.deferredPrompt.userChoice;

        console.log(`PWA: Choix utilisateur: ${outcome}`);

        if (outcome === 'accepted') {
            // Utilisateur a accepté
            this.trackInstallEvent('accepted');
        } else {
            // Utilisateur a refusé
            this.trackInstallEvent('dismissed');
        }

        // Réinitialiser
        this.deferredPrompt = null;
        this.dismissBanner();
    }

    /**
     * Instructions pour iOS
     */
    showIOSInstructions() {
        // Détecter iOS
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);

        if (isIOS && !window.navigator.standalone) {
            const modal = document.createElement('div');
            modal.className = 'pwa-ios-modal';
            modal.innerHTML = `
                <div class="pwa-ios-overlay" onclick="this.parentElement.remove()"></div>
                <div class="pwa-ios-content">
                    <h3>Installer sur iOS</h3>
                    <ol>
                        <li>Appuyez sur le bouton Partager <span style="font-size: 20px;">⎋</span></li>
                        <li>Faites défiler et sélectionnez "Sur l'écran d'accueil"</li>
                        <li>Appuyez sur "Ajouter"</li>
                    </ol>
                    <button onclick="this.closest('.pwa-ios-modal').remove()" class="pwa-ios-close">
                        Compris
                    </button>
                </div>
            `;

            const style = document.createElement('style');
            style.textContent = `
                .pwa-ios-modal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    z-index: 10000;
                }

                .pwa-ios-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0,0,0,0.5);
                }

                .pwa-ios-content {
                    position: absolute;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    background: white;
                    border-radius: 20px 20px 0 0;
                    padding: 24px;
                    max-width: 500px;
                    margin: 0 auto;
                }

                .pwa-ios-content h3 {
                    margin: 0 0 16px 0;
                    font-size: 20px;
                    font-weight: 600;
                }

                .pwa-ios-content ol {
                    margin: 0 0 20px 0;
                    padding-left: 24px;
                }

                .pwa-ios-content li {
                    margin-bottom: 12px;
                    line-height: 1.5;
                }

                .pwa-ios-close {
                    width: 100%;
                    padding: 12px;
                    background: #667eea;
                    color: white;
                    border: none;
                    border-radius: 8px;
                    font-weight: 600;
                    cursor: pointer;
                }
            `;

            document.head.appendChild(style);
            document.body.appendChild(modal);
        }
    }

    /**
     * Quand l'app est installée
     */
    onInstalled() {
        this.isInstalled = true;
        localStorage.setItem('pwa_installed', 'true');
        this.dismissBanner();
        this.trackInstallEvent('installed');

        // Afficher un message de confirmation
        this.showInstalledMessage();
    }

    /**
     * Message de confirmation après installation
     */
    showInstalledMessage() {
        const toast = document.createElement('div');
        toast.className = 'pwa-toast';
        toast.innerHTML = `
            <div class="pwa-toast-content">
                ✅ Application installée avec succès!
            </div>
        `;

        const style = document.createElement('style');
        style.textContent = `
            .pwa-toast {
                position: fixed;
                top: 20px;
                right: 20px;
                background: #10b981;
                color: white;
                padding: 16px 24px;
                border-radius: 12px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 10000;
                animation: slideIn 0.3s ease-out;
            }

            @keyframes slideIn {
                from {
                    transform: translateX(400px);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }

            .pwa-toast-content {
                display: flex;
                align-items: center;
                gap: 8px;
                font-weight: 600;
            }
        `;

        document.head.appendChild(style);
        document.body.appendChild(toast);

        // Retirer après 5 secondes
        setTimeout(() => {
            toast.remove();
        }, 5000);
    }

    /**
     * Tracker les événements d'installation
     */
    trackInstallEvent(action) {
        // Google Analytics
        if (typeof gtag !== 'undefined') {
            gtag('event', 'pwa_install', {
                event_category: 'PWA',
                event_label: action
            });
        }

        // Log local
        console.log(`PWA Install Event: ${action}`);

        // Envoyer au serveur pour analytics
        fetch('/api/track_pwa_event.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                event: 'pwa_install',
                action: action,
                timestamp: new Date().toISOString(),
                user_agent: navigator.userAgent
            })
        }).catch(console.error);
    }
}

// Initialiser au chargement
let pwaInstallManager = null;

function initPWAInstall() {
    if (!pwaInstallManager) {
        pwaInstallManager = new PWAInstallManager();
        pwaInstallManager.init();
    }
    return pwaInstallManager;
}

// Auto-initialisation
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        initPWAInstall();
    });
}

// Export pour utilisation globale
window.PWAInstallManager = PWAInstallManager;
window.pwaInstallManager = pwaInstallManager;
window.initPWAInstall = initPWAInstall;
