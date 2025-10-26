/**
 * Gestion des notifications Push côté client
 * Enregistrement du token FCM et abonnement
 */

class PushNotificationManager {
    constructor(firebaseConfig) {
        this.firebaseConfig = firebaseConfig;
        this.messaging = null;
        this.currentToken = null;
    }

    /**
     * Initialiser Firebase Messaging
     */
    async init() {
        try {
            // Vérifier le support
            if (!('serviceWorker' in navigator)) {
                console.warn('Service Worker non supporté par ce navigateur');
                return false;
            }

            if (!('Notification' in window)) {
                console.warn('Notifications non supportées par ce navigateur');
                return false;
            }

            // Importer Firebase
            const { initializeApp } = await import('https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js');
            const { getMessaging, getToken, onMessage } = await import('https://www.gstatic.com/firebasejs/10.7.1/firebase-messaging.js');

            // Initialiser Firebase
            const app = initializeApp(this.firebaseConfig);
            this.messaging = getMessaging(app);

            // Enregistrer le Service Worker
            await this.registerServiceWorker();

            // Écouter les messages au premier plan
            this.listenForMessages();

            return true;

        } catch (error) {
            console.error('Erreur initialisation Firebase:', error);
            return false;
        }
    }

    /**
     * Enregistrer le Service Worker
     */
    async registerServiceWorker() {
        try {
            const registration = await navigator.serviceWorker.register('/firebase-messaging-sw.js');
            console.log('Service Worker enregistré:', registration);
            return registration;
        } catch (error) {
            console.error('Erreur enregistrement Service Worker:', error);
            throw error;
        }
    }

    /**
     * Demander la permission et obtenir le token
     */
    async requestPermission(userData = {}) {
        try {
            // Demander la permission
            const permission = await Notification.requestPermission();

            if (permission !== 'granted') {
                console.log('Permission de notification refusée');
                return { success: false, error: 'Permission refusée' };
            }

            // Obtenir le token
            const { getToken } = await import('https://www.gstatic.com/firebasejs/10.7.1/firebase-messaging.js');

            const token = await getToken(this.messaging, {
                vapidKey: this.firebaseConfig.vapidKey
            });

            if (!token) {
                throw new Error('Impossible d\'obtenir le token');
            }

            this.currentToken = token;
            console.log('Token FCM obtenu:', token);

            // Enregistrer le token dans la base de données
            const saved = await this.saveTokenToServer(token, userData);

            if (saved) {
                // Sauvegarder localement
                localStorage.setItem('fcm_token', token);
                localStorage.setItem('notifications_enabled', 'true');
            }

            return {
                success: true,
                token: token
            };

        } catch (error) {
            console.error('Erreur demande permission:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Enregistrer le token sur le serveur
     */
    async saveTokenToServer(token, userData) {
        try {
            const deviceInfo = this.getDeviceInfo();

            const response = await fetch('/api/register_push_token.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    fcm_token: token,
                    user_id: userData.user_id || null,
                    email: userData.email || null,
                    phone: userData.phone || null,
                    device_type: deviceInfo.device_type,
                    device_name: deviceInfo.device_name,
                    browser: deviceInfo.browser,
                    os: deviceInfo.os
                })
            });

            const result = await response.json();
            return result.success;

        } catch (error) {
            console.error('Erreur enregistrement token:', error);
            return false;
        }
    }

    /**
     * Obtenir les informations du device
     */
    getDeviceInfo() {
        const ua = navigator.userAgent;
        let device_type = 'web';
        let browser = 'Unknown';
        let os = 'Unknown';

        // Détecter le type d'appareil
        if (/Android/i.test(ua)) {
            device_type = 'android';
            os = 'Android';
        } else if (/iPhone|iPad|iPod/i.test(ua)) {
            device_type = 'ios';
            os = 'iOS';
        } else if (/Windows/i.test(ua)) {
            os = 'Windows';
        } else if (/Mac/i.test(ua)) {
            os = 'macOS';
        } else if (/Linux/i.test(ua)) {
            os = 'Linux';
        }

        // Détecter le navigateur
        if (/Chrome/i.test(ua) && !/Edge/i.test(ua)) {
            browser = 'Chrome';
        } else if (/Firefox/i.test(ua)) {
            browser = 'Firefox';
        } else if (/Safari/i.test(ua) && !/Chrome/i.test(ua)) {
            browser = 'Safari';
        } else if (/Edge/i.test(ua)) {
            browser = 'Edge';
        } else if (/Opera|OPR/i.test(ua)) {
            browser = 'Opera';
        }

        return {
            device_type: device_type,
            device_name: `${os} - ${browser}`,
            browser: browser,
            os: os
        };
    }

    /**
     * Écouter les messages au premier plan
     */
    async listenForMessages() {
        try {
            const { onMessage } = await import('https://www.gstatic.com/firebasejs/10.7.1/firebase-messaging.js');

            onMessage(this.messaging, (payload) => {
                console.log('Message reçu au premier plan:', payload);

                // Afficher la notification
                this.showNotification(payload);

                // Déclencher un événement personnalisé
                const event = new CustomEvent('pushNotificationReceived', {
                    detail: payload
                });
                window.dispatchEvent(event);
            });

        } catch (error) {
            console.error('Erreur écoute messages:', error);
        }
    }

    /**
     * Afficher une notification
     */
    showNotification(payload) {
        const title = payload.notification?.title || 'Restaurant Mulho';
        const options = {
            body: payload.notification?.body || '',
            icon: payload.notification?.icon || '/assets/img/logo.png',
            image: payload.notification?.image,
            badge: '/assets/img/badge.png',
            vibrate: [200, 100, 200],
            tag: payload.data?.type || 'notification',
            data: payload.data || {}
        };

        if (Notification.permission === 'granted') {
            new Notification(title, options);
        }
    }

    /**
     * Vérifier si les notifications sont activées
     */
    isEnabled() {
        return localStorage.getItem('notifications_enabled') === 'true' &&
               Notification.permission === 'granted';
    }

    /**
     * Désactiver les notifications
     */
    async disable() {
        if (this.currentToken) {
            try {
                await fetch('/api/deactivate_push_token.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        fcm_token: this.currentToken
                    })
                });
            } catch (error) {
                console.error('Erreur désactivation token:', error);
            }
        }

        localStorage.setItem('notifications_enabled', 'false');
        this.currentToken = null;
    }

    /**
     * Obtenir le token actuel
     */
    getCurrentToken() {
        return this.currentToken || localStorage.getItem('fcm_token');
    }
}

// Fonction helper pour initialiser facilement
async function initPushNotifications(firebaseConfig, userData = {}) {
    const manager = new PushNotificationManager(firebaseConfig);
    const initialized = await manager.init();

    if (initialized) {
        // Auto-demander la permission si déjà accordée
        if (Notification.permission === 'granted') {
            await manager.requestPermission(userData);
        }
    }

    return manager;
}

// Exporter pour utilisation globale
window.PushNotificationManager = PushNotificationManager;
window.initPushNotifications = initPushNotifications;
