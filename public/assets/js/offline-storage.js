/**
 * Gestionnaire de stockage hors ligne avec IndexedDB
 * Gère le panier, favoris, et commandes en attente
 */

class OfflineStorage {
    constructor() {
        this.dbName = 'RestaurantMulhoDB';
        this.version = 1;
        this.db = null;
    }

    /**
     * Initialiser la base de données
     */
    async init() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.version);

            request.onerror = () => {
                console.error('Erreur ouverture IndexedDB:', request.error);
                reject(request.error);
            };

            request.onsuccess = () => {
                this.db = request.result;
                console.log('IndexedDB initialisée');
                resolve(this.db);
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                // Store pour le panier
                if (!db.objectStoreNames.contains('cart')) {
                    const cartStore = db.createObjectStore('cart', { keyPath: 'plat_id' });
                    cartStore.createIndex('nom', 'nom', { unique: false });
                    cartStore.createIndex('added_at', 'added_at', { unique: false });
                }

                // Store pour les favoris
                if (!db.objectStoreNames.contains('favorites')) {
                    const favStore = db.createObjectStore('favorites', { keyPath: 'plat_id' });
                    favStore.createIndex('nom', 'nom', { unique: false });
                    favStore.createIndex('added_at', 'added_at', { unique: false });
                }

                // Store pour les commandes en attente de synchronisation
                if (!db.objectStoreNames.contains('pendingOrders')) {
                    const orderStore = db.createObjectStore('pendingOrders', {
                        keyPath: 'id',
                        autoIncrement: true
                    });
                    orderStore.createIndex('created_at', 'created_at', { unique: false });
                    orderStore.createIndex('synced', 'synced', { unique: false });
                }

                // Store pour le cache du menu
                if (!db.objectStoreNames.contains('menuCache')) {
                    const menuStore = db.createObjectStore('menuCache', { keyPath: 'plat_id' });
                    menuStore.createIndex('categorie', 'categorie', { unique: false });
                    menuStore.createIndex('cached_at', 'cached_at', { unique: false });
                }

                // Store pour les préférences utilisateur
                if (!db.objectStoreNames.contains('userPreferences')) {
                    db.createObjectStore('userPreferences', { keyPath: 'key' });
                }

                console.log('IndexedDB mise à jour');
            };
        });
    }

    /**
     * Panier - Ajouter un plat
     */
    async addToCart(plat) {
        const transaction = this.db.transaction(['cart'], 'readwrite');
        const store = transaction.objectStore('cart');

        const cartItem = {
            plat_id: plat.id,
            nom: plat.nom,
            prix: plat.prix,
            image: plat.image,
            quantite: plat.quantite || 1,
            added_at: new Date().toISOString()
        };

        return new Promise((resolve, reject) => {
            const request = store.put(cartItem);
            request.onsuccess = () => {
                console.log('Plat ajouté au panier offline:', plat.nom);
                this.syncCartWithLocalStorage();
                resolve(request.result);
            };
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Panier - Obtenir tous les items
     */
    async getCart() {
        const transaction = this.db.transaction(['cart'], 'readonly');
        const store = transaction.objectStore('cart');

        return new Promise((resolve, reject) => {
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Panier - Mettre à jour la quantité
     */
    async updateCartQuantity(platId, quantite) {
        const transaction = this.db.transaction(['cart'], 'readwrite');
        const store = transaction.objectStore('cart');

        return new Promise(async (resolve, reject) => {
            const getRequest = store.get(platId);

            getRequest.onsuccess = () => {
                const item = getRequest.result;

                if (item) {
                    item.quantite = quantite;

                    if (quantite <= 0) {
                        // Supprimer si quantité = 0
                        const deleteRequest = store.delete(platId);
                        deleteRequest.onsuccess = () => {
                            this.syncCartWithLocalStorage();
                            resolve();
                        };
                        deleteRequest.onerror = () => reject(deleteRequest.error);
                    } else {
                        const updateRequest = store.put(item);
                        updateRequest.onsuccess = () => {
                            this.syncCartWithLocalStorage();
                            resolve();
                        };
                        updateRequest.onerror = () => reject(updateRequest.error);
                    }
                } else {
                    reject(new Error('Item non trouvé'));
                }
            };

            getRequest.onerror = () => reject(getRequest.error);
        });
    }

    /**
     * Panier - Vider
     */
    async clearCart() {
        const transaction = this.db.transaction(['cart'], 'readwrite');
        const store = transaction.objectStore('cart');

        return new Promise((resolve, reject) => {
            const request = store.clear();
            request.onsuccess = () => {
                this.syncCartWithLocalStorage();
                resolve();
            };
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Synchroniser le panier avec localStorage (pour compatibilité)
     */
    async syncCartWithLocalStorage() {
        try {
            const cart = await this.getCart();
            const total = cart.reduce((sum, item) => sum + (item.prix * item.quantite), 0);

            localStorage.setItem('panier', JSON.stringify(cart));
            localStorage.setItem('panier_total', total);

            // Déclencher un événement pour mettre à jour l'UI
            window.dispatchEvent(new CustomEvent('cartUpdated', {
                detail: { cart, total }
            }));
        } catch (error) {
            console.error('Erreur sync panier:', error);
        }
    }

    /**
     * Favoris - Ajouter
     */
    async addToFavorites(plat) {
        const transaction = this.db.transaction(['favorites'], 'readwrite');
        const store = transaction.objectStore('favorites');

        const favoriteItem = {
            plat_id: plat.id,
            nom: plat.nom,
            prix: plat.prix,
            image: plat.image,
            categorie: plat.categorie,
            added_at: new Date().toISOString()
        };

        return new Promise((resolve, reject) => {
            const request = store.put(favoriteItem);
            request.onsuccess = () => {
                console.log('Plat ajouté aux favoris:', plat.nom);
                resolve(request.result);
            };
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Favoris - Retirer
     */
    async removeFromFavorites(platId) {
        const transaction = this.db.transaction(['favorites'], 'readwrite');
        const store = transaction.objectStore('favorites');

        return new Promise((resolve, reject) => {
            const request = store.delete(platId);
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Favoris - Obtenir tous
     */
    async getFavorites() {
        const transaction = this.db.transaction(['favorites'], 'readonly');
        const store = transaction.objectStore('favorites');

        return new Promise((resolve, reject) => {
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Commandes - Enregistrer commande en attente
     */
    async savePendingOrder(orderData) {
        const transaction = this.db.transaction(['pendingOrders'], 'readwrite');
        const store = transaction.objectStore('pendingOrders');

        const order = {
            ...orderData,
            created_at: new Date().toISOString(),
            synced: false
        };

        return new Promise((resolve, reject) => {
            const request = store.add(order);
            request.onsuccess = () => {
                console.log('Commande enregistrée pour sync:', order);

                // Déclencher le background sync si disponible
                if ('serviceWorker' in navigator && 'sync' in ServiceWorkerRegistration.prototype) {
                    navigator.serviceWorker.ready.then((registration) => {
                        return registration.sync.register('sync-orders');
                    });
                }

                resolve(request.result);
            };
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Commandes - Obtenir commandes en attente
     */
    async getPendingOrders() {
        const transaction = this.db.transaction(['pendingOrders'], 'readonly');
        const store = transaction.objectStore('pendingOrders');

        return new Promise((resolve, reject) => {
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Menu - Mettre en cache
     */
    async cacheMenu(plats) {
        const transaction = this.db.transaction(['menuCache'], 'readwrite');
        const store = transaction.objectStore('menuCache');

        const cached_at = new Date().toISOString();

        return new Promise((resolve, reject) => {
            // Vider le cache existant
            store.clear();

            // Ajouter les nouveaux plats
            plats.forEach((plat) => {
                store.add({
                    plat_id: plat.id,
                    ...plat,
                    cached_at
                });
            });

            transaction.oncomplete = () => {
                console.log(`${plats.length} plats mis en cache`);
                resolve();
            };

            transaction.onerror = () => reject(transaction.error);
        });
    }

    /**
     * Menu - Obtenir depuis le cache
     */
    async getCachedMenu() {
        const transaction = this.db.transaction(['menuCache'], 'readonly');
        const store = transaction.objectStore('menuCache');

        return new Promise((resolve, reject) => {
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Préférences - Sauvegarder
     */
    async setPreference(key, value) {
        const transaction = this.db.transaction(['userPreferences'], 'readwrite');
        const store = transaction.objectStore('userPreferences');

        return new Promise((resolve, reject) => {
            const request = store.put({ key, value });
            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Préférences - Obtenir
     */
    async getPreference(key) {
        const transaction = this.db.transaction(['userPreferences'], 'readonly');
        const store = transaction.objectStore('userPreferences');

        return new Promise((resolve, reject) => {
            const request = store.get(key);
            request.onsuccess = () => resolve(request.result ? request.result.value : null);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Nettoyer les anciennes données (> 30 jours)
     */
    async cleanup() {
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);

        // Nettoyer le cache du menu si > 1 jour
        const oneDayAgo = new Date();
        oneDayAgo.setDate(oneDayAgo.getDate() - 1);

        const menuTransaction = this.db.transaction(['menuCache'], 'readwrite');
        const menuStore = menuTransaction.objectStore('menuCache');
        const menuIndex = menuStore.index('cached_at');
        const menuRange = IDBKeyRange.upperBound(oneDayAgo.toISOString());

        menuIndex.openCursor(menuRange).onsuccess = (event) => {
            const cursor = event.target.result;
            if (cursor) {
                cursor.delete();
                cursor.continue();
            }
        };

        console.log('Nettoyage IndexedDB effectué');
    }
}

// Instance globale
let offlineStorage = null;

// Initialiser au chargement
async function initOfflineStorage() {
    if (!offlineStorage) {
        offlineStorage = new OfflineStorage();
        await offlineStorage.init();

        // Nettoyer périodiquement
        offlineStorage.cleanup();
    }
    return offlineStorage;
}

// Auto-initialisation
if ('indexedDB' in window) {
    initOfflineStorage().catch(console.error);
}

// Export pour utilisation globale
window.OfflineStorage = OfflineStorage;
window.offlineStorage = offlineStorage;
window.initOfflineStorage = initOfflineStorage;
