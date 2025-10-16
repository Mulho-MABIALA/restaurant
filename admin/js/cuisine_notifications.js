/**
 * Script de notification pour la page commandes.php
 * Surveille les commandes prêtes depuis la cuisine en temps réel
 */

class CuisineNotifications {
    constructor() {
        this.checkInterval = 5000; // Vérifier toutes les 5 secondes
        this.notificationSound = null;
        this.previousCount = 0;
        this.timer = null;

        this.init();
    }

    init() {
        console.log('🔔 Système de notifications cuisine activé');

        // Créer le son de notification
        this.createNotificationSound();

        // Créer l'interface de notification
        this.createNotificationUI();

        // Démarrer la surveillance
        this.startMonitoring();

        // Demander la permission pour les notifications navigateur
        this.requestNotificationPermission();
    }

    createNotificationSound() {
        // Son de notification (bip court)
        this.notificationSound = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuFzvLZiTYIG2m98OScTgwOUKjo77RgGwU7k9n0zHkpBSh+zPDekD8IElyx6OyrVBUIRp3e8r1rHwUrhc/y2ogzBx1qwPDlm0wLDlOq6e+yXhoEOpPY88x3KAUpfs/v3o8+BxJbr+frrVMUB0ae3/O9aB0FLoXP8tuIMQcdbMPz5ppKCg5TqunwsVsaBDyU2fPNdSYEK4HQ8d+OOwYSXLLo7K1SFAdGoN/zv2YbBCyEz/PciC4HH23E9OaYSAkNVKzq8bBZGQQ8ldv0znMjBCuB0fLgjDoFEl2z6e2uUBIHSKHh9L9kGQMrhNDz3YgrBiBuxfTnlkYIDVWs6/KvVxgEPJXc9c9xIQMrgtPz4Ys4BRJftOrur04QBkii4/TAYhYDK4XR9N6HJwYgb8X16JRDBgxWre70rlUWAz2W3vbRbx0CK4PU9eGJNAQSYLXr8LFNDQZJo+T1w2AUAy2G0/feh/IAIHDHzuyYSQsLV67w97RRFgM+l+H4025hBSuF1fXii/AFFE+56/OyTAkFSabm9sVhMQMuhNL54Yf5ACFxx+HvmkYLCliy8vq1TxMCPpjk+tNsHQEric3z5I/vBBJPuu/1tEoFBUqn6PjIYiwCLoTS+eCI8wAjcsrj9ZZCKQ0=');
    }

    createNotificationUI() {
        // Créer le conteneur de notifications si il n'existe pas
        if (!document.getElementById('cuisineNotificationContainer')) {
            const container = document.createElement('div');
            container.id = 'cuisineNotificationContainer';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                max-width: 400px;
            `;
            document.body.appendChild(container);
        }

        // Créer le badge de notifications
        if (!document.getElementById('cuisineNotificationBadge')) {
            const badge = document.createElement('div');
            badge.id = 'cuisineNotificationBadge';
            badge.style.cssText = `
                position: fixed;
                top: 80px;
                right: 20px;
                background: #28a745;
                color: white;
                padding: 10px 20px;
                border-radius: 25px;
                box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
                display: none;
                align-items: center;
                gap: 10px;
                font-weight: bold;
                z-index: 9998;
                cursor: pointer;
                transition: all 0.3s ease;
            `;
            badge.innerHTML = `
                <i class="fas fa-bell"></i>
                <span id="cuisineNotificationCount">0</span>
                <span>commande(s) prête(s)</span>
            `;
            badge.onclick = () => this.showCommandesPretesModal();
            document.body.appendChild(badge);
        }
    }

    async requestNotificationPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            try {
                await Notification.requestPermission();
            } catch (e) {
                console.log('Notifications désactivées');
            }
        }
    }

    startMonitoring() {
        // Vérifier immédiatement
        this.checkCommandesPretes();

        // Puis vérifier périodiquement
        this.timer = setInterval(() => {
            this.checkCommandesPretes();
        }, this.checkInterval);

        console.log('✅ Surveillance des commandes démarrée');
    }

    stopMonitoring() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
            console.log('⏸️ Surveillance arrêtée');
        }
    }

    async checkCommandesPretes() {
        try {
            const response = await fetch('api_cuisine_notifications.php?action=count_commandes_pretes');
            const data = await response.json();

            if (data.success) {
                const count = data.count;

                // Si le nombre a augmenté, nouvelle commande prête !
                if (count > this.previousCount) {
                    this.onNouvelleCommandePrete(count);
                }

                // Mettre à jour l'affichage
                this.updateBadge(count);

                this.previousCount = count;
            }
        } catch (error) {
            console.error('Erreur de vérification:', error);
        }
    }

    onNouvelleCommandePrete(count) {
        console.log('🍽️ Nouvelle commande prête !');

        // Jouer le son
        this.playNotificationSound();

        // Notification toast
        this.showToastNotification('🍽️ Commande prête !', 'Une commande est prête pour le service');

        // Notification navigateur
        this.showBrowserNotification('Commande prête !', `${count} commande(s) prête(s) pour le service`);

        // Animation du badge
        this.animateBadge();
    }

    playNotificationSound() {
        try {
            this.notificationSound.play();
        } catch (e) {
            console.log('Impossible de jouer le son');
        }
    }

    showToastNotification(title, message) {
        const container = document.getElementById('cuisineNotificationContainer');

        const toast = document.createElement('div');
        toast.style.cssText = `
            background: white;
            border-left: 4px solid #28a745;
            padding: 15px 20px;
            margin-bottom: 10px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            animation: slideInRight 0.3s ease;
        `;
        toast.innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-bell" style="color: #28a745; font-size: 1.5rem;"></i>
                <div>
                    <div style="font-weight: bold; color: #333;">${title}</div>
                    <div style="color: #666; font-size: 0.9rem;">${message}</div>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" style="
                    border: none;
                    background: none;
                    color: #999;
                    cursor: pointer;
                    font-size: 1.2rem;
                    margin-left: auto;
                ">&times;</button>
            </div>
        `;

        container.appendChild(toast);

        // Retirer automatiquement après 5 secondes
        setTimeout(() => {
            if (toast.parentElement) {
                toast.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }
        }, 5000);
    }

    showBrowserNotification(title, body) {
        if ('Notification' in window && Notification.permission === 'granted') {
            try {
                const notification = new Notification(title, {
                    body: body,
                    icon: '/admin/assets/logo.png', // Adapter le chemin
                    badge: '/admin/assets/logo.png',
                    tag: 'cuisine-commande-prete',
                    requireInteraction: true
                });

                notification.onclick = () => {
                    window.focus();
                    this.showCommandesPretesModal();
                    notification.close();
                };
            } catch (e) {
                console.log('Erreur notification:', e);
            }
        }
    }

    updateBadge(count) {
        const badge = document.getElementById('cuisineNotificationBadge');
        const countSpan = document.getElementById('cuisineNotificationCount');

        if (count > 0) {
            badge.style.display = 'flex';
            countSpan.textContent = count;
        } else {
            badge.style.display = 'none';
        }
    }

    animateBadge() {
        const badge = document.getElementById('cuisineNotificationBadge');
        badge.style.animation = 'none';
        setTimeout(() => {
            badge.style.animation = 'pulse 0.5s ease 3';
        }, 10);
    }

    async showCommandesPretesModal() {
        try {
            const response = await fetch('api_cuisine_notifications.php?action=get_commandes_pretes');
            const data = await response.json();

            if (data.success && data.commandes.length > 0) {
                // Créer et afficher le modal avec les commandes
                this.displayCommandesPretesModal(data.commandes);
            }
        } catch (error) {
            console.error('Erreur:', error);
        }
    }

    displayCommandesPretesModal(commandes) {
        // Créer le modal
        const modal = document.createElement('div');
        modal.id = 'commandesPretesModal';
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        `;

        modal.innerHTML = `
            <div style="
                background: white;
                border-radius: 15px;
                max-width: 800px;
                max-height: 80vh;
                overflow-y: auto;
                padding: 30px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            ">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="margin: 0; color: #28a745;">
                        <i class="fas fa-check-circle"></i>
                        Commandes Prêtes (${commandes.length})
                    </h2>
                    <button onclick="document.getElementById('commandesPretesModal').remove()" style="
                        border: none;
                        background: #dc3545;
                        color: white;
                        width: 35px;
                        height: 35px;
                        border-radius: 50%;
                        cursor: pointer;
                        font-size: 1.2rem;
                    ">&times;</button>
                </div>

                <div style="display: grid; gap: 15px;">
                    ${commandes.map(cmd => `
                        <div style="
                            border: 2px solid #28a745;
                            border-radius: 10px;
                            padding: 15px;
                            background: #f8fff9;
                        ">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                <strong style="font-size: 1.2rem; color: #333;">
                                    Commande #${String(cmd.id).padStart(4, '0')}
                                </strong>
                                <span style="
                                    background: #28a745;
                                    color: white;
                                    padding: 5px 15px;
                                    border-radius: 20px;
                                    font-size: 0.9rem;
                                ">
                                    <i class="fas fa-clock"></i>
                                    Prête depuis ${cmd.temps_depuis_pret || 0} min
                                </span>
                            </div>

                            <div style="margin-bottom: 10px;">
                                <strong>Client:</strong> ${cmd.nom_client}
                                ${cmd.num_table ? `| <strong>Table:</strong> ${cmd.num_table}` : ''}
                            </div>

                            <div style="
                                background: white;
                                padding: 10px;
                                border-radius: 5px;
                                margin: 10px 0;
                            ">
                                <strong>Articles:</strong>
                                <ul style="margin: 5px 0; padding-left: 20px;">
                                    ${cmd.details.map(d => `
                                        <li>${d.nom_plat} <span style="color: #666;">(x${d.quantite})</span></li>
                                    `).join('')}
                                </ul>
                            </div>

                            <button onclick="cuisineNotifications.marquerCommandeVue(${cmd.id})" style="
                                background: #007bff;
                                color: white;
                                border: none;
                                padding: 10px 20px;
                                border-radius: 5px;
                                cursor: pointer;
                                font-weight: bold;
                                width: 100%;
                            ">
                                <i class="fas fa-check"></i>
                                Marquer comme servie
                            </button>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;

        document.body.appendChild(modal);
    }

    async marquerCommandeVue(id) {
        try {
            const formData = new FormData();
            formData.append('action', 'marquer_vu');
            formData.append('id', id);

            const response = await fetch('api_cuisine_notifications.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                // Fermer le modal et recharger
                const modal = document.getElementById('commandesPretesModal');
                if (modal) modal.remove();

                // Recharger la page des commandes si on est dessus
                if (typeof loadOrders === 'function') {
                    loadOrders();
                }

                // Vérifier à nouveau
                this.checkCommandesPretes();

                this.showToastNotification('✅ Succès', 'Commande marquée comme servie');
            }
        } catch (error) {
            console.error('Erreur:', error);
            alert('Erreur lors de la mise à jour');
        }
    }
}

// Ajouter les animations CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }

    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.1);
        }
    }
`;
document.head.appendChild(style);

// Initialiser automatiquement
let cuisineNotifications;
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        cuisineNotifications = new CuisineNotifications();
    });
} else {
    cuisineNotifications = new CuisineNotifications();
}
