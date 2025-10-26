/**
 * Service Worker pour Firebase Cloud Messaging
 * Gère les notifications push en arrière-plan
 */

// Importer les scripts Firebase
importScripts('https://www.gstatic.com/firebasejs/10.7.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.7.1/firebase-messaging-compat.js');

// Configuration Firebase (à personnaliser avec vos credentials)
const firebaseConfig = {
    apiKey: "VOTRE_API_KEY",
    authDomain: "votre-projet.firebaseapp.com",
    projectId: "votre-projet-id",
    storageBucket: "votre-projet.appspot.com",
    messagingSenderId: "123456789",
    appId: "1:123456789:web:abcdef"
};

// Initialiser Firebase
firebase.initializeApp(firebaseConfig);

// Récupérer l'instance de messaging
const messaging = firebase.messaging();

// Gérer les messages en arrière-plan
messaging.onBackgroundMessage((payload) => {
    console.log('[firebase-messaging-sw.js] Message reçu en arrière-plan:', payload);

    const notificationTitle = payload.notification?.title || 'Restaurant Mulho';
    const notificationOptions = {
        body: payload.notification?.body || '',
        icon: payload.notification?.icon || '/assets/img/logo.png',
        image: payload.notification?.image,
        badge: '/assets/img/badge.png',
        vibrate: [200, 100, 200],
        tag: payload.data?.type || 'notification',
        requireInteraction: false,
        data: payload.data || {},
        actions: [
            {
                action: 'view',
                title: 'Voir',
                icon: '/assets/img/icons/view.png'
            },
            {
                action: 'close',
                title: 'Fermer',
                icon: '/assets/img/icons/close.png'
            }
        ]
    };

    // Afficher la notification
    return self.registration.showNotification(notificationTitle, notificationOptions);
});

// Gérer le clic sur la notification
self.addEventListener('notificationclick', (event) => {
    console.log('[firebase-messaging-sw.js] Notification cliquée:', event);

    event.notification.close();

    // Déterminer l'URL à ouvrir selon le type
    let urlToOpen = '/';

    if (event.notification.data) {
        const data = event.notification.data;

        switch (data.type) {
            case 'commande_confirmee':
            case 'commande_prete':
            case 'commande_en_livraison':
                urlToOpen = `/public/suivi_commande.php?id=${data.order_id || ''}`;
                break;
            case 'reservation_confirmee':
            case 'rappel_reservation':
                urlToOpen = `/public/mes_reservations.php`;
                break;
            case 'paiement_reussi':
                urlToOpen = `/public/confirmation.php?id=${data.order_id || ''}`;
                break;
            case 'promotion':
                urlToOpen = `/public/menu.php`;
                break;
            default:
                urlToOpen = data.url || '/';
        }
    }

    if (event.action === 'view' || !event.action) {
        // Ouvrir l'URL
        event.waitUntil(
            clients.matchAll({ type: 'window', includeUncontrolled: true })
                .then((clientList) => {
                    // Vérifier si une fenêtre est déjà ouverte
                    for (let i = 0; i < clientList.length; i++) {
                        const client = clientList[i];
                        if (client.url.includes(self.location.origin) && 'focus' in client) {
                            return client.focus().then(() => {
                                return client.navigate(urlToOpen);
                            });
                        }
                    }
                    // Sinon, ouvrir une nouvelle fenêtre
                    if (clients.openWindow) {
                        return clients.openWindow(urlToOpen);
                    }
                })
        );
    }

    // Enregistrer le clic dans la base de données via l'API
    fetch('/api/notification_clicked.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            notification_id: event.notification.data?.notification_id,
            action: event.action || 'view'
        })
    }).catch(err => console.error('Erreur enregistrement clic:', err));
});

// Gérer la fermeture de la notification
self.addEventListener('notificationclose', (event) => {
    console.log('[firebase-messaging-sw.js] Notification fermée:', event);
});

// Version du Service Worker
const SW_VERSION = '1.0.0';
console.log(`[firebase-messaging-sw.js] Service Worker v${SW_VERSION} chargé`);
