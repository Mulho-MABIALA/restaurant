/**
 * Initialisation de la PWA
 * Enregistre le Service Worker et initialise les fonctionnalités offline
 */

(function() {
    'use strict';

    /**
     * Configuration de la PWA
     */
    const PWA_CONFIG = {
        serviceWorkerPath: '/restaurant/public/sw.js',
        scope: '/restaurant/public/',
        enableNotifications: true,
        enableOfflineMode: true,
        cacheVersion: 'v1.0.0'
    };

    /**
     * État de la PWA
     */
    let pwaState = {
        serviceWorkerRegistered: false,
        isOnline: navigator.onLine,
        isStandalone: false,
        registration: null
    };

    /**
     * Initialiser la PWA
     */
    async function initPWA() {
        console.log('[PWA] Initialisation...');

        // Vérifier le support
        if (!checkSupport()) {
            console.warn('[PWA] Navigateur non supporté');
            return false;
        }

        // Détecter le mode standalone
        detectStandaloneMode();

        // Enregistrer le Service Worker
        await registerServiceWorker();

        // Initialiser le stockage offline
        if (PWA_CONFIG.enableOfflineMode) {
            await initializeOfflineStorage();
        }

        // Écouter les changements de connexion
        setupNetworkListeners();

        // Afficher le statut
        updateOnlineStatus();

        // Charger le manifest dynamiquement si nécessaire
        ensureManifestLoaded();

        console.log('[PWA] Initialisé avec succès');
        return true;
    }

    /**
     * Vérifier le support du navigateur
     */
    function checkSupport() {
        const hasServiceWorker = 'serviceWorker' in navigator;
        const hasPromise = typeof Promise !== 'undefined';
        const hasFetch = typeof fetch !== 'undefined';

        return hasServiceWorker && hasPromise && hasFetch;
    }

    /**
     * Détecter le mode standalone (app installée)
     */
    function detectStandaloneMode() {
        // Chrome/Edge
        if (window.matchMedia('(display-mode: standalone)').matches) {
            pwaState.isStandalone = true;
            document.documentElement.classList.add('standalone-mode');
            console.log('[PWA] Mode standalone détecté');
        }

        // iOS Safari
        if (window.navigator.standalone === true) {
            pwaState.isStandalone = true;
            document.documentElement.classList.add('standalone-mode', 'ios-standalone');
            console.log('[PWA] Mode standalone iOS détecté');
        }

        // Déclencher un événement
        if (pwaState.isStandalone) {
            window.dispatchEvent(new CustomEvent('pwaStandalone'));
        }
    }

    /**
     * Enregistrer le Service Worker
     */
    async function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            return false;
        }

        try {
            const registration = await navigator.serviceWorker.register(
                PWA_CONFIG.serviceWorkerPath,
                { scope: PWA_CONFIG.scope }
            );

            pwaState.serviceWorkerRegistered = true;
            pwaState.registration = registration;

            console.log('[PWA] Service Worker enregistré:', registration.scope);

            // Vérifier les mises à jour
            registration.addEventListener('updatefound', () => {
                console.log('[PWA] Mise à jour du Service Worker détectée');
                handleServiceWorkerUpdate(registration);
            });

            // Vérifier immédiatement s'il y a une mise à jour
            registration.update();

            return registration;

        } catch (error) {
            console.error('[PWA] Erreur enregistrement Service Worker:', error);
            return false;
        }
    }

    /**
     * Gérer la mise à jour du Service Worker
     */
    function handleServiceWorkerUpdate(registration) {
        const newWorker = registration.installing;

        newWorker.addEventListener('statechange', () => {
            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                // Nouvelle version disponible
                showUpdateNotification();
            }
        });
    }

    /**
     * Afficher une notification de mise à jour
     */
    function showUpdateNotification() {
        const notification = document.createElement('div');
        notification.className = 'pwa-update-notification';
        notification.innerHTML = `
            <div class="pwa-update-content">
                <span>🔄 Nouvelle version disponible</span>
                <button onclick="window.location.reload()">Mettre à jour</button>
            </div>
        `;

        const style = document.createElement('style');
        style.textContent = `
            .pwa-update-notification {
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                background: #4f46e5;
                color: white;
                padding: 12px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 10000;
                animation: slideDown 0.3s ease-out;
            }

            @keyframes slideDown {
                from {
                    transform: translateX(-50%) translateY(-100px);
                    opacity: 0;
                }
                to {
                    transform: translateX(-50%) translateY(0);
                    opacity: 1;
                }
            }

            .pwa-update-content {
                display: flex;
                align-items: center;
                gap: 16px;
            }

            .pwa-update-content button {
                background: white;
                color: #4f46e5;
                border: none;
                padding: 6px 16px;
                border-radius: 4px;
                font-weight: 600;
                cursor: pointer;
            }
        `;

        document.head.appendChild(style);
        document.body.appendChild(notification);
    }

    /**
     * Initialiser le stockage offline
     */
    async function initializeOfflineStorage() {
        if (typeof initOfflineStorage === 'function') {
            try {
                await initOfflineStorage();
                console.log('[PWA] Stockage offline initialisé');
            } catch (error) {
                console.error('[PWA] Erreur init stockage:', error);
            }
        }
    }

    /**
     * Configurer les listeners réseau
     */
    function setupNetworkListeners() {
        window.addEventListener('online', () => {
            pwaState.isOnline = true;
            updateOnlineStatus();
            handleOnline();
        });

        window.addEventListener('offline', () => {
            pwaState.isOnline = false;
            updateOnlineStatus();
            handleOffline();
        });
    }

    /**
     * Mettre à jour l'indicateur de statut en ligne
     */
    function updateOnlineStatus() {
        document.documentElement.classList.toggle('is-online', pwaState.isOnline);
        document.documentElement.classList.toggle('is-offline', !pwaState.isOnline);

        // Déclencher un événement
        window.dispatchEvent(new CustomEvent('networkStatusChanged', {
            detail: { online: pwaState.isOnline }
        }));
    }

    /**
     * Gérer le retour en ligne
     */
    function handleOnline() {
        console.log('[PWA] Connexion rétablie');

        // Afficher une notification
        showNetworkToast('✅ Connexion rétablie', 'success');

        // Synchroniser les données en attente
        if (pwaState.registration && pwaState.registration.sync) {
            pwaState.registration.sync.register('sync-orders').catch(console.error);
        }

        // Recharger les données critiques
        reloadCriticalData();
    }

    /**
     * Gérer le passage hors ligne
     */
    function handleOffline() {
        console.log('[PWA] Connexion perdue');

        // Afficher une notification
        showNetworkToast('⚠️ Mode hors ligne', 'warning');
    }

    /**
     * Afficher un toast de statut réseau
     */
    function showNetworkToast(message, type) {
        const toast = document.createElement('div');
        toast.className = `pwa-network-toast pwa-toast-${type}`;
        toast.textContent = message;

        const style = document.createElement('style');
        style.textContent = `
            .pwa-network-toast {
                position: fixed;
                bottom: 20px;
                left: 50%;
                transform: translateX(-50%);
                padding: 12px 24px;
                border-radius: 8px;
                font-weight: 600;
                z-index: 9999;
                animation: fadeInUp 0.3s ease-out;
            }

            .pwa-toast-success {
                background: #10b981;
                color: white;
            }

            .pwa-toast-warning {
                background: #f59e0b;
                color: white;
            }

            @keyframes fadeInUp {
                from {
                    transform: translateX(-50%) translateY(20px);
                    opacity: 0;
                }
                to {
                    transform: translateX(-50%) translateY(0);
                    opacity: 1;
                }
            }
        `;

        document.head.appendChild(style);
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.remove();
        }, 3000);
    }

    /**
     * Recharger les données critiques
     */
    function reloadCriticalData() {
        // Déclencher un événement que d'autres scripts peuvent écouter
        window.dispatchEvent(new CustomEvent('pwaOnlineDataReload'));

        // Si sur la page menu, recharger le menu
        if (window.location.pathname.includes('menu.php')) {
            console.log('[PWA] Rechargement du menu...');
            // La page peut implémenter sa propre logique
        }
    }

    /**
     * S'assurer que le manifest est chargé
     */
    function ensureManifestLoaded() {
        const existingLink = document.querySelector('link[rel="manifest"]');

        if (!existingLink) {
            const link = document.createElement('link');
            link.rel = 'manifest';
            link.href = '/public/manifest.json';
            document.head.appendChild(link);
            console.log('[PWA] Manifest chargé');
        }
    }

    /**
     * Obtenir l'état de la PWA
     */
    function getPWAState() {
        return { ...pwaState };
    }

    /**
     * Vérifier si l'app est installée
     */
    function isInstalled() {
        return pwaState.isStandalone ||
               localStorage.getItem('pwa_installed') === 'true';
    }

    // Export des fonctions publiques
    window.PWA = {
        init: initPWA,
        getState: getPWAState,
        isInstalled: isInstalled,
        isOnline: () => pwaState.isOnline,
        isStandalone: () => pwaState.isStandalone,
        registration: () => pwaState.registration
    };

    // Auto-initialisation
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPWA);
    } else {
        initPWA();
    }

})();
