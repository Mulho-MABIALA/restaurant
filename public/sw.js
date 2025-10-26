/**
 * Service Worker - Restaurant Mulho PWA
 * Gère le cache, mode offline, synchronisation en arrière-plan
 */

const CACHE_VERSION = 'v1.0.0';
const CACHE_NAME = `restaurant-mulho-${CACHE_VERSION}`;
const DATA_CACHE_NAME = `restaurant-mulho-data-${CACHE_VERSION}`;

// Fichiers à mettre en cache immédiatement (App Shell)
const APP_SHELL = [
    '/restaurant/public/',
    '/restaurant/public/index.php',
    '/restaurant/public/menu.php',
    '/restaurant/public/commander.php',
    '/restaurant/public/panier.php',
    '/restaurant/public/assets/css/main.css',
    '/restaurant/public/assets/css/style.css',
    '/restaurant/public/assets/js/main.js',
    '/restaurant/public/assets/js/panier.js',
    '/restaurant/public/assets/img/logo.jpg',
    '/restaurant/public/manifest.json',
    // Polices et icônes
    'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'
];

// URLs à ne JAMAIS mettre en cache
const NEVER_CACHE = [
    '/admin/',
    '/api/register_push_token.php',
    '/api/notification_clicked.php',
    '/public/payment_webhook.php'
];

// Configuration des stratégies de cache par route
const CACHE_STRATEGIES = {
    // Cache First - Pour les assets statiques
    cacheFirst: [
        /\.(?:png|jpg|jpeg|gif|webp|svg|ico)$/,
        /\.(?:woff|woff2|ttf|eot)$/,
        /\.css$/,
        /\.js$/
    ],

    // Network First - Pour les données dynamiques
    networkFirst: [
        /\.php$/,
        /\/api\//
    ],

    // Stale While Revalidate - Pour un bon équilibre
    staleWhileRevalidate: [
        /\/public\/menu\.php/,
        /\/public\/plats\//
    ]
};

/**
 * Installation du Service Worker
 */
self.addEventListener('install', (event) => {
    console.log('[SW] Installation en cours...');

    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[SW] Mise en cache de l\'App Shell');
                return cache.addAll(APP_SHELL);
            })
            .then(() => {
                console.log('[SW] Installation terminée');
                return self.skipWaiting(); // Activer immédiatement
            })
            .catch((err) => {
                console.error('[SW] Erreur installation:', err);
            })
    );
});

/**
 * Activation du Service Worker
 */
self.addEventListener('activate', (event) => {
    console.log('[SW] Activation en cours...');

    event.waitUntil(
        // Supprimer les anciens caches
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME && cacheName !== DATA_CACHE_NAME) {
                        console.log('[SW] Suppression ancien cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
        .then(() => {
            console.log('[SW] Activation terminée');
            return self.clients.claim(); // Prendre contrôle immédiatement
        })
    );
});

/**
 * Interception des requêtes
 */
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Ne pas mettre en cache certaines URLs
    if (NEVER_CACHE.some(pattern => url.pathname.includes(pattern))) {
        return event.respondWith(fetch(request));
    }

    // Déterminer la stratégie de cache
    const strategy = getStrategy(url);

    switch (strategy) {
        case 'cacheFirst':
            event.respondWith(cacheFirstStrategy(request));
            break;

        case 'networkFirst':
            event.respondWith(networkFirstStrategy(request));
            break;

        case 'staleWhileRevalidate':
            event.respondWith(staleWhileRevalidateStrategy(request));
            break;

        default:
            event.respondWith(networkFirstStrategy(request));
    }
});

/**
 * Stratégie Cache First
 * Idéale pour les assets statiques (images, CSS, JS)
 */
async function cacheFirstStrategy(request) {
    const cache = await caches.open(CACHE_NAME);
    const cached = await cache.match(request);

    if (cached) {
        return cached;
    }

    try {
        const response = await fetch(request);

        // Ne mettre en cache que les réponses réussies
        if (response.status === 200) {
            cache.put(request, response.clone());
        }

        return response;
    } catch (error) {
        console.error('[SW] Erreur Cache First:', error);

        // Si hors ligne, retourner une page de fallback
        if (request.destination === 'document') {
            return caches.match('/public/offline.html');
        }

        // Pour les images, retourner un placeholder
        if (request.destination === 'image') {
            return caches.match('/public/assets/img/placeholder.png');
        }

        throw error;
    }
}

/**
 * Stratégie Network First
 * Idéale pour les données dynamiques
 */
async function networkFirstStrategy(request) {
    const cache = await caches.open(DATA_CACHE_NAME);

    try {
        const response = await fetch(request);

        // Mettre en cache si succès
        if (response.status === 200) {
            cache.put(request, response.clone());
        }

        return response;
    } catch (error) {
        console.log('[SW] Réseau indisponible, utilisation du cache');

        const cached = await cache.match(request);

        if (cached) {
            return cached;
        }

        // Si pas de cache et hors ligne
        if (request.destination === 'document') {
            return caches.match('/public/offline.html');
        }

        throw error;
    }
}

/**
 * Stratégie Stale While Revalidate
 * Retourne le cache immédiatement et met à jour en arrière-plan
 */
async function staleWhileRevalidateStrategy(request) {
    const cache = await caches.open(DATA_CACHE_NAME);
    const cached = await cache.match(request);

    // Lancer la requête réseau en parallèle
    const fetchPromise = fetch(request).then((response) => {
        if (response.status === 200) {
            cache.put(request, response.clone());
        }
        return response;
    });

    // Retourner le cache immédiatement s'il existe
    return cached || fetchPromise;
}

/**
 * Déterminer la stratégie pour une URL
 */
function getStrategy(url) {
    const pathname = url.pathname;

    // Cache First
    for (let pattern of CACHE_STRATEGIES.cacheFirst) {
        if (pattern.test(pathname)) {
            return 'cacheFirst';
        }
    }

    // Network First
    for (let pattern of CACHE_STRATEGIES.networkFirst) {
        if (pattern.test(pathname)) {
            return 'networkFirst';
        }
    }

    // Stale While Revalidate
    for (let pattern of CACHE_STRATEGIES.staleWhileRevalidate) {
        if (pattern.test(pathname)) {
            return 'staleWhileRevalidate';
        }
    }

    return 'networkFirst'; // Par défaut
}

/**
 * Background Sync - Synchroniser les commandes hors ligne
 */
self.addEventListener('sync', (event) => {
    console.log('[SW] Background Sync:', event.tag);

    if (event.tag === 'sync-orders') {
        event.waitUntil(syncPendingOrders());
    }

    if (event.tag === 'sync-favorites') {
        event.waitUntil(syncFavorites());
    }
});

/**
 * Synchroniser les commandes en attente
 */
async function syncPendingOrders() {
    try {
        const db = await openIndexedDB();
        const pendingOrders = await getPendingOrders(db);

        for (let order of pendingOrders) {
            try {
                const response = await fetch('/api/submit_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(order)
                });

                if (response.ok) {
                    await removePendingOrder(db, order.id);
                    console.log('[SW] Commande synchronisée:', order.id);
                }
            } catch (error) {
                console.error('[SW] Erreur sync commande:', error);
            }
        }
    } catch (error) {
        console.error('[SW] Erreur Background Sync:', error);
    }
}

/**
 * Synchroniser les favoris
 */
async function syncFavorites() {
    // À implémenter selon vos besoins
    console.log('[SW] Synchronisation favoris...');
}

/**
 * Push Notification - Gestion intégrée
 */
self.addEventListener('push', (event) => {
    console.log('[SW] Push reçu:', event);

    const data = event.data ? event.data.json() : {};
    const title = data.title || 'Restaurant Mulho';
    const options = {
        body: data.body || '',
        icon: data.icon || '/public/assets/img/logo.png',
        badge: '/public/assets/img/badge.png',
        vibrate: [200, 100, 200],
        tag: data.tag || 'notification',
        data: data.data || {}
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

/**
 * Gestion du clic sur notification
 */
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = event.notification.data.url || '/public/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                for (let client of clientList) {
                    if (client.url === url && 'focus' in client) {
                        return client.focus();
                    }
                }
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            })
    );
});

/**
 * Helpers IndexedDB
 */
function openIndexedDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('RestaurantMulhoDB', 1);

        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);

        request.onupgradeneeded = (event) => {
            const db = event.target.result;

            if (!db.objectStoreNames.contains('pendingOrders')) {
                db.createObjectStore('pendingOrders', { keyPath: 'id', autoIncrement: true });
            }

            if (!db.objectStoreNames.contains('favorites')) {
                db.createObjectStore('favorites', { keyPath: 'id' });
            }

            if (!db.objectStoreNames.contains('cart')) {
                db.createObjectStore('cart', { keyPath: 'plat_id' });
            }
        };
    });
}

function getPendingOrders(db) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['pendingOrders'], 'readonly');
        const store = transaction.objectStore('pendingOrders');
        const request = store.getAll();

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function removePendingOrder(db, orderId) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['pendingOrders'], 'readwrite');
        const store = transaction.objectStore('pendingOrders');
        const request = store.delete(orderId);

        request.onsuccess = () => resolve();
        request.onerror = () => reject(request.error);
    });
}

// Log de la version
console.log(`[SW] Service Worker ${CACHE_VERSION} actif`);
