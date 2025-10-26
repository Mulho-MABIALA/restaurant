# 🚀 FONCTIONNALITÉS AVANCÉES - RESTAURANT MULHO

Guide complet des fonctionnalités professionnelles à ajouter pour rendre votre système de gestion de restaurant plus compétitif et complet.

**Basé sur l'analyse complète de votre projet actuel**

---

## 📊 ANALYSE DE VOTRE PROJET ACTUEL

### ✅ Fonctionnalités Déjà Implémentées (Excellentes!)

**Interface Client:**
- ✅ Menu dynamique avec catégories
- ✅ Système de commande avec panier
- ✅ Réservations de tables
- ✅ Géolocalisation (geofence 150m)
- ✅ Avis clients et notations
- ✅ Newsletter
- ✅ Galerie d'événements
- ✅ Multi-langues (FR, EN, ES, WO)

**Interface Admin:**
- ✅ Dashboard avec analytics
- ✅ Gestion commandes (6 statuts)
- ✅ Système cuisine temps réel
- ✅ Gestion stock automatisée
- ✅ Finance complète (trésorerie, marges, factures)
- ✅ RH complet (paie, présence, congés, primes)
- ✅ Newsletter avancé (segments, tracking)
- ✅ Sécurité renforcée (2FA, RBAC, audit)
- ✅ QR codes (badges employés, tables)
- ✅ Export PDF/CSV
- ✅ Prévisions financières

**Technologies:**
- PHP 7.1+/8.0+, MySQL 5.7+
- PHPMailer, dompdf, endroid/qr-code
- Tailwind CSS, FontAwesome
- Architecture MVC, POO, PDO

### 🎯 Votre Position Actuelle
Vous avez déjà un système **très complet** qui couvre 70-80% des besoins d'un restaurant moderne. Les ajouts proposés ci-dessous vont vous amener à un niveau **professionnel premium** comparable aux solutions SaaS payantes.

---

## 🔥 NIVEAU 1 - CRITIQUE (Impact Business Immédiat)

### 1. 💳 Intégration Paiement en Ligne

**Utilité:**
- **Problème actuel:** Paiement manuel uniquement (cash, carte physique)
- **Solution:** Commandes payées en ligne = moins d'annulations, paiement garanti
- **ROI:** Augmentation 30-50% des commandes, réduction 80% des no-shows

**Comment l'intégrer:**

#### Architecture Suggérée
```
admin/classes/PaymentGateway.php (classe abstraite)
admin/classes/PaymentProviders/
  ├── OrangeMoneyProvider.php
  ├── WaveProvider.php
  ├── PaydunyaProvider.php (VISA/Mastercard pour Sénégal)
  ├── StripeProvider.php (international)
  └── PayPalProvider.php (international)
```

#### Flux de Paiement
```php
// 1. Lors de la confirmation de commande
public/commander.php:
- Sélection mode paiement (cash/online)
- Si online → redirection vers gateway
- Callback URL: public/payment_callback.php

// 2. Nouvelle table `paiements`
CREATE TABLE paiements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    commande_id INT NOT NULL,
    montant DECIMAL(10,2),
    provider ENUM('orange_money', 'wave', 'paydunya', 'stripe', 'paypal'),
    transaction_id VARCHAR(255), -- ID du provider
    statut ENUM('pending', 'success', 'failed', 'refunded'),
    metadata JSON, -- Données brutes du provider
    callback_data JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (commande_id) REFERENCES commandes(id)
);

// 3. Classe PaymentGateway
abstract class PaymentGateway {
    abstract public function createPayment($order, $amount);
    abstract public function verifyPayment($transactionId);
    abstract public function refund($transactionId, $amount);
    abstract public function getPaymentStatus($transactionId);
}

// 4. Exemple Orange Money (Sénégal)
class OrangeMoneyProvider extends PaymentGateway {
    private $apiKey;
    private $merchantId;
    private $apiUrl = 'https://api.orange.com/orange-money-webpay/';

    public function createPayment($order, $amount) {
        // 1. Créer token de paiement
        $response = $this->makeRequest('/v1/webpayment', [
            'merchant_key' => $this->merchantId,
            'currency' => 'XOF', // FCFA
            'order_id' => $order['id'],
            'amount' => $amount,
            'return_url' => 'https://votre-site.com/payment_callback.php',
            'cancel_url' => 'https://votre-site.com/payment_cancel.php',
            'notif_url' => 'https://votre-site.com/payment_webhook.php',
            'lang' => 'fr',
            'reference' => 'CMD-' . $order['id']
        ]);

        return [
            'payment_url' => $response['payment_url'],
            'payment_token' => $response['pay_token']
        ];
    }
}

// 5. Callback handler
public/payment_callback.php:
<?php
require_once '../config/db.php';
require_once '../admin/classes/PaymentGateway.php';

$provider = $_GET['provider'];
$transactionId = $_GET['transaction_id'];

$gateway = PaymentFactory::create($provider);
$status = $gateway->verifyPayment($transactionId);

if ($status === 'success') {
    // Mettre à jour la commande
    $stmt = $conn->prepare("
        UPDATE commandes
        SET statut_paiement = 'Completed',
            statut = 'Confirmée',
            payment_method = ?
        WHERE id = ?
    ");
    $stmt->execute([$provider, $orderId]);

    // Enregistrer le paiement
    $stmt = $conn->prepare("
        INSERT INTO paiements
        (commande_id, montant, provider, transaction_id, statut, callback_data)
        VALUES (?, ?, ?, ?, 'success', ?)
    ");
    $stmt->execute([
        $orderId,
        $amount,
        $provider,
        $transactionId,
        json_encode($_POST)
    ]);

    // Envoyer email de confirmation
    $emailService->sendOrderConfirmation($order);

    header('Location: confirmation.php?id=' . $orderId);
} else {
    header('Location: payment_failed.php?error=' . $status);
}
```

#### Interface Client Améliorée
```html
<!-- public/commander.php - Section paiement -->
<div class="payment-method-selector">
    <h3>Mode de paiement</h3>

    <!-- Paiement sur place (existant) -->
    <label class="payment-option">
        <input type="radio" name="payment_mode" value="on_delivery">
        <div class="option-card">
            <i class="fas fa-hand-holding-usd"></i>
            <span>Payer sur place</span>
            <small>Espèces ou carte au restaurant</small>
        </div>
    </label>

    <!-- Paiement en ligne (nouveau) -->
    <label class="payment-option">
        <input type="radio" name="payment_mode" value="orange_money">
        <div class="option-card">
            <img src="assets/img/orange-money.png" alt="Orange Money">
            <span>Orange Money</span>
            <small>Paiement sécurisé instantané</small>
        </div>
    </label>

    <label class="payment-option">
        <input type="radio" name="payment_mode" value="wave">
        <div class="option-card">
            <img src="assets/img/wave.png" alt="Wave">
            <span>Wave</span>
            <small>0% de frais</small>
        </div>
    </label>

    <label class="payment-option">
        <input type="radio" name="payment_mode" value="card">
        <div class="option-card">
            <i class="fas fa-credit-card"></i>
            <span>Carte bancaire</span>
            <small>VISA, Mastercard via Paydunya</small>
        </div>
    </label>
</div>

<!-- Avantage paiement en ligne -->
<div class="payment-incentive">
    <i class="fas fa-gift"></i>
    <span>Payez en ligne et recevez -5% de réduction!</span>
</div>
```

#### Admin - Gestion Paiements
```php
// admin/paiements.php (nouvelle page)
<div class="payments-dashboard">
    <!-- Statistiques -->
    <div class="payment-stats-grid">
        <div class="stat-card">
            <h4>Aujourd'hui</h4>
            <p class="amount"><?= formatCurrency($stats['today_total']) ?></p>
            <span class="provider-breakdown">
                OM: <?= formatCurrency($stats['today_om']) ?> |
                Wave: <?= formatCurrency($stats['today_wave']) ?>
            </span>
        </div>

        <div class="stat-card">
            <h4>Taux de réussite</h4>
            <p class="percentage"><?= $stats['success_rate'] ?>%</p>
        </div>

        <div class="stat-card alert">
            <h4>Paiements échoués</h4>
            <p><?= $stats['failed_count'] ?> (<?= formatCurrency($stats['failed_amount']) ?>)</p>
        </div>
    </div>

    <!-- Liste des transactions -->
    <table class="payments-table">
        <thead>
            <tr>
                <th>ID Transaction</th>
                <th>Commande</th>
                <th>Client</th>
                <th>Montant</th>
                <th>Provider</th>
                <th>Statut</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($payments as $payment): ?>
            <tr>
                <td><code><?= $payment['transaction_id'] ?></code></td>
                <td><a href="voir_commande.php?id=<?= $payment['commande_id'] ?>">
                    #<?= $payment['commande_id'] ?>
                </a></td>
                <td><?= $payment['client_nom'] ?></td>
                <td><?= formatCurrency($payment['montant']) ?></td>
                <td>
                    <span class="provider-badge <?= $payment['provider'] ?>">
                        <?= ucfirst($payment['provider']) ?>
                    </span>
                </td>
                <td>
                    <span class="status-badge <?= $payment['statut'] ?>">
                        <?= $payment['statut'] ?>
                    </span>
                </td>
                <td><?= date('d/m/Y H:i', strtotime($payment['created_at'])) ?></td>
                <td>
                    <?php if ($payment['statut'] === 'success'): ?>
                    <button onclick="refundPayment(<?= $payment['id'] ?>)">
                        Rembourser
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
```

**Complexité:** ⭐⭐⭐⭐ (Élevée)
- **Temps estimé:** 2-3 semaines
- **Compétences requises:** API REST, webhooks, sécurité paiement
- **Coûts:** Frais de transaction (1-3% selon provider)
- **Dépendances:** Compte marchand Orange Money/Wave/Paydunya

**Providers Recommandés pour le Sénégal:**
1. **Orange Money** - Le plus utilisé (API disponible)
2. **Wave** - 0% de frais, populaire (API disponible)
3. **Paydunya** - Agrégateur (Orange Money + Wave + cartes bancaires)
4. **CinetPay** - Alternative sénégalaise

---

### 2. 🔔 Système de Notifications Push & SMS

**Utilité:**
- **Problème actuel:** Notifications email seulement (taux d'ouverture 20-30%)
- **Solution:** Push (70% taux d'ouverture) + SMS (98% taux de lecture)
- **Use cases:** "Votre commande est prête", "Table disponible dans 5min", "Votre réservation dans 1h"

**Comment l'intégrer:**

#### Architecture
```
admin/classes/NotificationService.php
admin/classes/NotificationProviders/
  ├── PushNotificationProvider.php (Firebase Cloud Messaging)
  ├── SMSProvider.php (abstrait)
  ├── TwilioSMSProvider.php
  └── OrangeSMSProvider.php (API Orange SMS Sénégal)
```

#### Base de Données
```sql
-- Table pour gérer les tokens push des clients
CREATE TABLE notification_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    token VARCHAR(255) UNIQUE,
    platform ENUM('web', 'android', 'ios'),
    device_info JSON,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Table pour l'historique des notifications
CREATE TABLE notifications_sent (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    type ENUM('push', 'sms', 'email'),
    channel VARCHAR(50), -- 'order_status', 'reservation', 'promotion'
    title VARCHAR(255),
    message TEXT,
    data JSON, -- Payload supplémentaire
    statut ENUM('pending', 'sent', 'delivered', 'failed', 'read'),
    provider VARCHAR(50),
    provider_response JSON,
    sent_at TIMESTAMP,
    delivered_at TIMESTAMP,
    read_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Préférences de notifications par utilisateur
CREATE TABLE notification_preferences (
    user_id INT PRIMARY KEY,
    order_updates_push BOOLEAN DEFAULT 1,
    order_updates_sms BOOLEAN DEFAULT 1,
    order_updates_email BOOLEAN DEFAULT 1,
    reservation_reminders BOOLEAN DEFAULT 1,
    promotions BOOLEAN DEFAULT 1,
    newsletter BOOLEAN DEFAULT 1,
    updated_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

#### Classe NotificationService
```php
// admin/classes/NotificationService.php
class NotificationService {
    private $db;
    private $pushProvider;
    private $smsProvider;
    private $emailService;

    public function __construct($conn) {
        $this->db = $conn;
        $this->pushProvider = new PushNotificationProvider();
        $this->smsProvider = new OrangeSMSProvider();
        $this->emailService = new EmailService();
    }

    /**
     * Envoyer notification multi-canal basée sur préférences utilisateur
     */
    public function notifyOrderStatusChange($orderId, $newStatus) {
        $order = $this->getOrder($orderId);
        $user = $this->getUser($order['user_id']);
        $prefs = $this->getUserPreferences($order['user_id']);

        $title = $this->getOrderStatusTitle($newStatus);
        $message = $this->getOrderStatusMessage($order, $newStatus);

        $results = [];

        // Push notification
        if ($prefs['order_updates_push']) {
            $results['push'] = $this->pushProvider->send(
                $user['id'],
                $title,
                $message,
                [
                    'type' => 'order_status',
                    'order_id' => $orderId,
                    'status' => $newStatus,
                    'action' => 'view_order'
                ]
            );
        }

        // SMS pour statuts importants
        if ($prefs['order_updates_sms'] && in_array($newStatus, ['Prêt', 'En livraison'])) {
            $results['sms'] = $this->smsProvider->send(
                $user['telephone'],
                $message
            );
        }

        // Email toujours envoyé comme backup
        if ($prefs['order_updates_email']) {
            $results['email'] = $this->emailService->sendOrderStatusUpdate(
                $user['email'],
                $order,
                $newStatus
            );
        }

        // Logger toutes les notifications envoyées
        $this->logNotification($user['id'], 'order_status', $title, $message, $results);

        return $results;
    }

    /**
     * Notification de rappel de réservation (1h avant)
     */
    public function sendReservationReminder($reservationId) {
        $reservation = $this->getReservation($reservationId);

        $title = "Rappel de réservation";
        $message = "Bonjour {$reservation['nom']}, votre table pour {$reservation['nombre_personnes']} personnes est réservée aujourd'hui à {$reservation['heure']}. À bientôt!";

        return $this->sendMultiChannel(
            $reservation['user_id'],
            $title,
            $message,
            'reservation',
            ['reservation_id' => $reservationId]
        );
    }

    /**
     * Notification promotionnelle (avec opt-in)
     */
    public function sendPromotion($title, $message, $segmentIds = []) {
        $users = $this->getUsersInSegments($segmentIds);
        $sent = 0;

        foreach ($users as $user) {
            $prefs = $this->getUserPreferences($user['id']);

            if (!$prefs['promotions']) {
                continue; // Respecter le opt-out
            }

            $this->pushProvider->send(
                $user['id'],
                $title,
                $message,
                [
                    'type' => 'promotion',
                    'action' => 'view_menu'
                ]
            );

            $sent++;
        }

        return ['sent' => $sent, 'total' => count($users)];
    }

    private function getOrderStatusTitle($status) {
        $titles = [
            'Confirmée' => '✅ Commande confirmée',
            'En préparation' => '👨‍🍳 Commande en préparation',
            'Prêt' => '🔔 Votre commande est prête!',
            'En livraison' => '🚴 Commande en route',
            'Livré' => '✨ Commande livrée',
            'Annulé' => '❌ Commande annulée'
        ];
        return $titles[$status] ?? 'Mise à jour commande';
    }

    private function getOrderStatusMessage($order, $status) {
        $messages = [
            'Confirmée' => "Votre commande #{$order['id']} a été confirmée. Temps estimé: 30 minutes.",
            'En préparation' => "Nos chefs préparent votre commande #{$order['id']} avec soin.",
            'Prêt' => "Votre commande #{$order['id']} est prête! Vous pouvez venir la récupérer.",
            'En livraison' => "Votre commande #{$order['id']} est en route. Livraison dans 10-15 minutes.",
            'Livré' => "Bon appétit! N'oubliez pas de nous laisser un avis.",
            'Annulé' => "Votre commande #{$order['id']} a été annulée. Contactez-nous pour plus d'infos."
        ];
        return $messages[$status] ?? "Votre commande a été mise à jour.";
    }
}
```

#### Provider Firebase Cloud Messaging (Push)
```php
// admin/classes/NotificationProviders/PushNotificationProvider.php
class PushNotificationProvider {
    private $fcmServerKey;
    private $fcmApiUrl = 'https://fcm.googleapis.com/fcm/send';
    private $db;

    public function __construct() {
        $this->fcmServerKey = $_ENV['FCM_SERVER_KEY'];
    }

    public function send($userId, $title, $body, $data = []) {
        // Récupérer tous les tokens actifs de l'utilisateur
        $tokens = $this->getActiveTokens($userId);

        if (empty($tokens)) {
            return ['success' => false, 'error' => 'No active tokens'];
        }

        $notification = [
            'title' => $title,
            'body' => $body,
            'icon' => 'https://votre-site.com/assets/img/logo.png',
            'badge' => 'https://votre-site.com/assets/img/badge.png',
            'sound' => 'default',
            'click_action' => 'https://votre-site.com/mes-commandes.php'
        ];

        $payload = [
            'registration_ids' => $tokens,
            'notification' => $notification,
            'data' => $data,
            'priority' => 'high',
            'time_to_live' => 3600 // 1 heure
        ];

        $headers = [
            'Authorization: key=' . $this->fcmServerKey,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->fcmApiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        // Nettoyer les tokens invalides
        if (isset($result['results'])) {
            foreach ($result['results'] as $index => $res) {
                if (isset($res['error']) && $res['error'] === 'NotRegistered') {
                    $this->deactivateToken($tokens[$index]);
                }
            }
        }

        return [
            'success' => $result['success'] > 0,
            'sent' => $result['success'] ?? 0,
            'failed' => $result['failure'] ?? 0,
            'response' => $result
        ];
    }

    public function registerToken($userId, $token, $platform, $deviceInfo = []) {
        $stmt = $this->db->prepare("
            INSERT INTO notification_tokens
            (user_id, token, platform, device_info, last_used_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                platform = VALUES(platform),
                device_info = VALUES(device_info),
                is_active = 1,
                last_used_at = NOW()
        ");

        return $stmt->execute([
            $userId,
            $token,
            $platform,
            json_encode($deviceInfo)
        ]);
    }

    private function getActiveTokens($userId) {
        $stmt = $this->db->prepare("
            SELECT token
            FROM notification_tokens
            WHERE user_id = ? AND is_active = 1
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function deactivateToken($token) {
        $stmt = $this->db->prepare("
            UPDATE notification_tokens
            SET is_active = 0
            WHERE token = ?
        ");
        $stmt->execute([$token]);
    }
}
```

#### Provider SMS Orange Sénégal
```php
// admin/classes/NotificationProviders/OrangeSMSProvider.php
class OrangeSMSProvider {
    private $clientId;
    private $clientSecret;
    private $senderName = 'MULHO'; // Nom affiché (max 11 chars)
    private $apiUrl = 'https://api.orange.com/smsmessaging/v1';
    private $accessToken;

    public function __construct() {
        $this->clientId = $_ENV['ORANGE_SMS_CLIENT_ID'];
        $this->clientSecret = $_ENV['ORANGE_SMS_CLIENT_SECRET'];
        $this->authenticate();
    }

    private function authenticate() {
        // Obtenir token OAuth2
        $ch = curl_init('https://api.orange.com/oauth/v3/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->clientId . ':' . $this->clientSecret);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        $this->accessToken = $response['access_token'];
    }

    public function send($phoneNumber, $message) {
        // Formater numéro (ex: 771234567 → +221771234567)
        $formattedNumber = $this->formatPhoneNumber($phoneNumber);

        $payload = [
            'outboundSMSMessageRequest' => [
                'address' => 'tel:' . $formattedNumber,
                'senderAddress' => 'tel:+221' . $this->senderName,
                'outboundSMSTextMessage' => [
                    'message' => $message
                ]
            ]
        ];

        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ];

        $ch = curl_init($this->apiUrl . '/outbound/tel:+221' . $this->senderName . '/requests');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'success' => $httpCode === 201,
            'response' => json_decode($response, true),
            'cost' => $this->calculateCost($message) // ~25 FCFA par SMS
        ];
    }

    private function formatPhoneNumber($phone) {
        // Nettoyer et formater
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Ajouter +221 si nécessaire
        if (substr($phone, 0, 3) !== '221') {
            $phone = '221' . $phone;
        }

        return '+' . $phone;
    }

    private function calculateCost($message) {
        $segments = ceil(strlen($message) / 160);
        return $segments * 25; // 25 FCFA par segment
    }
}
```

#### Frontend - Service Worker pour Push (Web)
```javascript
// public/assets/js/push-notifications.js

// Demander permission et enregistrer token
async function enablePushNotifications() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        console.log('Push notifications not supported');
        return;
    }

    try {
        // Enregistrer service worker
        const registration = await navigator.serviceWorker.register('/sw.js');

        // Demander permission
        const permission = await Notification.requestPermission();

        if (permission === 'granted') {
            // S'abonner aux push
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(FCM_PUBLIC_KEY)
            });

            // Envoyer token au serveur
            await fetch('/api/register-push-token.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    subscription: subscription.toJSON(),
                    platform: 'web',
                    device_info: {
                        browser: navigator.userAgent,
                        screen: `${screen.width}x${screen.height}`
                    }
                })
            });

            showNotification('Notifications activées!',
                'Vous recevrez des alertes pour vos commandes');
        }
    } catch (error) {
        console.error('Push notification error:', error);
    }
}

// Service Worker (public/sw.js)
self.addEventListener('push', function(event) {
    const data = event.data.json();

    const options = {
        body: data.notification.body,
        icon: data.notification.icon,
        badge: data.notification.badge,
        data: data.data,
        actions: [
            {action: 'view', title: 'Voir'},
            {action: 'dismiss', title: 'Fermer'}
        ],
        vibrate: [200, 100, 200],
        tag: data.data.type + '-' + data.data.order_id
    };

    event.waitUntil(
        self.registration.showNotification(data.notification.title, options)
    );
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();

    if (event.action === 'view') {
        const urlToOpen = event.notification.data.action === 'view_order'
            ? '/mes-commandes.php?id=' + event.notification.data.order_id
            : '/menu.php';

        event.waitUntil(
            clients.openWindow(urlToOpen)
        );
    }
});
```

#### Intégration dans le flux de commande
```php
// admin/update_commande.php - Modifier le statut
$stmt = $conn->prepare("
    UPDATE commandes
    SET statut = ?
    WHERE id = ?
");
$stmt->execute([$newStatus, $orderId]);

// Envoyer notifications
$notificationService = new NotificationService($conn);
$notificationService->notifyOrderStatusChange($orderId, $newStatus);

// Réponse JSON
echo json_encode([
    'success' => true,
    'message' => 'Statut mis à jour et notifications envoyées'
]);
```

#### Cron Job pour rappels de réservation
```php
// admin/cron/reservation_reminders.php
<?php
require_once '../../config/db.php';
require_once '../classes/NotificationService.php';

// Trouver réservations dans 1h
$stmt = $conn->prepare("
    SELECT id
    FROM reservations
    WHERE DATE(date_reservation) = CURDATE()
    AND TIME(heure_reservation) BETWEEN
        TIME(DATE_ADD(NOW(), INTERVAL 55 MINUTE))
        AND TIME(DATE_ADD(NOW(), INTERVAL 65 MINUTE))
    AND statut = 'Confirmée'
    AND reminder_sent = 0
");
$stmt->execute();
$reservations = $stmt->fetchAll(PDO::FETCH_COLUMN);

$notificationService = new NotificationService($conn);

foreach ($reservations as $reservationId) {
    $notificationService->sendReservationReminder($reservationId);

    // Marquer comme envoyé
    $stmt = $conn->prepare("
        UPDATE reservations
        SET reminder_sent = 1
        WHERE id = ?
    ");
    $stmt->execute([$reservationId]);
}

echo "Sent " . count($reservations) . " reminders\n";
```

**Ajouter à crontab:**
```bash
# Rappels réservation toutes les 10 minutes
*/10 * * * * php /path/to/admin/cron/reservation_reminders.php
```

#### Interface Admin - Gestion Notifications
```php
// admin/notifications.php
<div class="notifications-dashboard">
    <!-- Statistiques -->
    <div class="stats-grid">
        <div class="stat-card">
            <h4>Aujourd'hui</h4>
            <p><?= $stats['today_sent'] ?> envoyées</p>
            <small>
                Push: <?= $stats['today_push'] ?> |
                SMS: <?= $stats['today_sms'] ?> |
                Email: <?= $stats['today_email'] ?>
            </small>
        </div>

        <div class="stat-card">
            <h4>Taux de lecture</h4>
            <p><?= $stats['read_rate'] ?>%</p>
        </div>

        <div class="stat-card">
            <h4>Coût SMS</h4>
            <p><?= formatCurrency($stats['sms_cost']) ?></p>
            <small><?= $stats['sms_count'] ?> SMS ce mois</small>
        </div>
    </div>

    <!-- Envoyer notification manuelle -->
    <div class="send-notification-form">
        <h3>Envoyer une notification</h3>
        <form id="sendNotificationForm">
            <select name="segment">
                <option value="all">Tous les clients</option>
                <option value="frequent">Clients fidèles</option>
                <option value="inactive">Inactifs 30j</option>
            </select>

            <input type="text" name="title" placeholder="Titre">
            <textarea name="message" placeholder="Message"></textarea>

            <div class="channels">
                <label>
                    <input type="checkbox" name="channels[]" value="push" checked>
                    Push notification
                </label>
                <label>
                    <input type="checkbox" name="channels[]" value="sms">
                    SMS (coût: ~25 FCFA/client)
                </label>
                <label>
                    <input type="checkbox" name="channels[]" value="email" checked>
                    Email
                </label>
            </div>

            <button type="submit">Envoyer</button>
        </form>
    </div>

    <!-- Historique -->
    <table class="notifications-history">
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Canal</th>
                <th>Destinataire</th>
                <th>Message</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($history as $notif): ?>
            <tr>
                <td><?= date('d/m/Y H:i', strtotime($notif['sent_at'])) ?></td>
                <td><?= $notif['channel'] ?></td>
                <td>
                    <span class="type-badge <?= $notif['type'] ?>">
                        <?= $notif['type'] ?>
                    </span>
                </td>
                <td><?= $notif['user_name'] ?></td>
                <td><?= substr($notif['message'], 0, 50) ?>...</td>
                <td>
                    <span class="status-badge <?= $notif['statut'] ?>">
                        <?= $notif['statut'] ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
```

**Complexité:** ⭐⭐⭐⭐ (Élevée)
- **Temps estimé:** 2-3 semaines
- **Compétences requises:** Firebase, service workers, API SMS, webhooks
- **Coûts:**
  - Push: Gratuit (Firebase)
  - SMS: ~25 FCFA par SMS (Orange Sénégal)
  - SMS alternatif: Twilio (~50 FCFA/SMS)
- **Dépendances:**
  - Compte Firebase (gratuit)
  - Compte Orange Developer ou Twilio

**ROI:**
- Réduction 50% des annulations grâce aux rappels
- Augmentation 40% de l'engagement client
- Amélioration satisfaction client (NPS +15 points)

---

### 3. 📱 Application Mobile (PWA Progressive Web App)

**Utilité:**
- **Problème actuel:** Site web responsive mais pas d'app native
- **Solution:** PWA = expérience app native sans développer Android/iOS
- **Avantages:** Installation sur écran d'accueil, mode hors-ligne, push notifications

**Comment l'intégrer:**

Votre site est déjà responsive, il suffit d'ajouter:

#### 1. Manifest (public/manifest.json)
```json
{
  "name": "Restaurant Mulho",
  "short_name": "Mulho",
  "description": "Commandez vos plats préférés au Restaurant Mulho",
  "start_url": "/",
  "display": "standalone",
  "background_color": "#1F2937",
  "theme_color": "#D4AF37",
  "orientation": "portrait",
  "icons": [
    {
      "src": "/assets/img/icons/icon-72x72.png",
      "sizes": "72x72",
      "type": "image/png"
    },
    {
      "src": "/assets/img/icons/icon-96x96.png",
      "sizes": "96x96",
      "type": "image/png"
    },
    {
      "src": "/assets/img/icons/icon-128x128.png",
      "sizes": "128x128",
      "type": "image/png"
    },
    {
      "src": "/assets/img/icons/icon-144x144.png",
      "sizes": "144x144",
      "type": "image/png"
    },
    {
      "src": "/assets/img/icons/icon-152x152.png",
      "sizes": "152x152",
      "type": "image/png"
    },
    {
      "src": "/assets/img/icons/icon-192x192.png",
      "sizes": "192x192",
      "type": "image/png"
    },
    {
      "src": "/assets/img/icons/icon-384x384.png",
      "sizes": "384x384",
      "type": "image/png"
    },
    {
      "src": "/assets/img/icons/icon-512x512.png",
      "sizes": "512x512",
      "type": "image/png",
      "purpose": "any maskable"
    }
  ],
  "screenshots": [
    {
      "src": "/assets/img/screenshots/home.png",
      "sizes": "540x720",
      "type": "image/png"
    },
    {
      "src": "/assets/img/screenshots/menu.png",
      "sizes": "540x720",
      "type": "image/png"
    }
  ],
  "shortcuts": [
    {
      "name": "Voir le menu",
      "short_name": "Menu",
      "url": "/menu.php",
      "icons": [{"src": "/assets/img/icons/menu-icon.png", "sizes": "96x96"}]
    },
    {
      "name": "Mes commandes",
      "short_name": "Commandes",
      "url": "/mes-commandes.php",
      "icons": [{"src": "/assets/img/icons/orders-icon.png", "sizes": "96x96"}]
    },
    {
      "name": "Réserver une table",
      "short_name": "Réserver",
      "url": "/cartes.php",
      "icons": [{"src": "/assets/img/icons/reservation-icon.png", "sizes": "96x96"}]
    }
  ],
  "categories": ["food", "restaurant"],
  "lang": "fr-SN"
}
```

#### 2. Service Worker Amélioré (public/sw.js)
```javascript
const CACHE_NAME = 'mulho-v1.0.0';
const STATIC_CACHE = 'mulho-static-v1';
const DYNAMIC_CACHE = 'mulho-dynamic-v1';
const IMAGE_CACHE = 'mulho-images-v1';

// Fichiers à mettre en cache immédiatement
const STATIC_FILES = [
    '/',
    '/menu.php',
    '/commander.php',
    '/cartes.php',
    '/assets/css/style.css',
    '/assets/js/main.js',
    '/assets/img/logo.png',
    '/offline.html'
];

// Installation
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => cache.addAll(STATIC_FILES))
            .then(() => self.skipWaiting())
    );
});

// Activation
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => {
            return Promise.all(
                keys.filter(key => key !== STATIC_CACHE &&
                                   key !== DYNAMIC_CACHE &&
                                   key !== IMAGE_CACHE)
                    .map(key => caches.delete(key))
            );
        }).then(() => self.clients.claim())
    );
});

// Stratégies de cache
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    // API calls: Network first, fallback to cache
    if (url.pathname.includes('/api/') || url.pathname.includes('ajax')) {
        event.respondWith(networkFirst(request));
    }
    // Images: Cache first, fallback to network
    else if (request.destination === 'image') {
        event.respondWith(cacheFirst(request, IMAGE_CACHE));
    }
    // Static files: Cache first
    else if (STATIC_FILES.includes(url.pathname)) {
        event.respondWith(cacheFirst(request, STATIC_CACHE));
    }
    // Dynamic pages: Network first
    else {
        event.respondWith(networkFirst(request));
    }
});

// Network first strategy
async function networkFirst(request) {
    try {
        const response = await fetch(request);
        const cache = await caches.open(DYNAMIC_CACHE);
        cache.put(request, response.clone());
        return response;
    } catch (error) {
        const cached = await caches.match(request);
        return cached || caches.match('/offline.html');
    }
}

// Cache first strategy
async function cacheFirst(request, cacheName) {
    const cached = await caches.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);
        const cache = await caches.open(cacheName);
        cache.put(request, response.clone());
        return response;
    } catch (error) {
        return caches.match('/offline.html');
    }
}

// Background sync pour commandes hors ligne
self.addEventListener('sync', event => {
    if (event.tag === 'sync-orders') {
        event.waitUntil(syncPendingOrders());
    }
});

async function syncPendingOrders() {
    const db = await openDatabase();
    const orders = await db.getAll('pending-orders');

    for (const order of orders) {
        try {
            const response = await fetch('/api/create-order.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(order)
            });

            if (response.ok) {
                await db.delete('pending-orders', order.id);

                // Notifier l'utilisateur
                self.registration.showNotification('Commande envoyée!', {
                    body: 'Votre commande a été envoyée avec succès',
                    icon: '/assets/img/icons/icon-192x192.png'
                });
            }
        } catch (error) {
            console.error('Sync failed:', error);
        }
    }
}

// IndexedDB pour stockage hors ligne
function openDatabase() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('mulho-db', 1);

        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);

        request.onupgradeneeded = event => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains('pending-orders')) {
                db.createObjectStore('pending-orders', {keyPath: 'id', autoIncrement: true});
            }
            if (!db.objectStoreNames.contains('menu-cache')) {
                db.createObjectStore('menu-cache');
            }
        };
    });
}
```

#### 3. Page Offline (public/offline.html)
```html
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hors ligne - Restaurant Mulho</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #1F2937, #374151);
            color: white;
            text-align: center;
            padding: 2rem;
        }
        .offline-container {
            max-width: 400px;
        }
        .offline-icon {
            font-size: 80px;
            margin-bottom: 2rem;
        }
        h1 {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #D4AF37;
        }
        p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        .retry-btn {
            background: #D4AF37;
            color: #1F2937;
            border: none;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        .retry-btn:hover {
            background: #F4C430;
        }
    </style>
</head>
<body>
    <div class="offline-container">
        <div class="offline-icon">📡</div>
        <h1>Vous êtes hors ligne</h1>
        <p>Veuillez vérifier votre connexion Internet pour continuer à utiliser l'application.</p>
        <button class="retry-btn" onclick="window.location.reload()">
            Réessayer
        </button>
    </div>
</body>
</html>
```

#### 4. Prompt d'installation (public/assets/js/pwa-install.js)
```javascript
let deferredPrompt;
const installButton = document.getElementById('pwa-install-btn');
const installBanner = document.getElementById('pwa-install-banner');

// Écouter l'événement beforeinstallprompt
window.addEventListener('beforeinstallprompt', (e) => {
    // Empêcher le prompt automatique
    e.preventDefault();
    deferredPrompt = e;

    // Afficher notre propre bannière d'installation
    if (installBanner) {
        installBanner.style.display = 'flex';
    }
});

// Gérer le clic sur le bouton d'installation
if (installButton) {
    installButton.addEventListener('click', async () => {
        if (!deferredPrompt) return;

        // Afficher le prompt d'installation
        deferredPrompt.prompt();

        // Attendre la réponse de l'utilisateur
        const { outcome } = await deferredPrompt.userChoice;

        console.log(`User choice: ${outcome}`);

        // Logger dans analytics
        if (typeof gtag !== 'undefined') {
            gtag('event', 'pwa_install_prompt', {
                event_category: 'PWA',
                event_label: outcome
            });
        }

        // Réinitialiser
        deferredPrompt = null;

        // Cacher la bannière
        if (installBanner) {
            installBanner.style.display = 'none';
        }
    });
}

// Détecter si l'app est installée
window.addEventListener('appinstalled', () => {
    console.log('PWA installed successfully');

    // Cacher la bannière
    if (installBanner) {
        installBanner.style.display = 'none';
    }

    // Logger
    if (typeof gtag !== 'undefined') {
        gtag('event', 'pwa_installed', {
            event_category: 'PWA'
        });
    }

    // Afficher message de succès
    showNotification('Application installée!',
        'Vous pouvez maintenant accéder à Mulho depuis votre écran d\'accueil');
});

// Vérifier si déjà en mode standalone
function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches ||
           window.navigator.standalone === true;
}

// Cacher la bannière si déjà installé
if (isStandalone() && installBanner) {
    installBanner.style.display = 'none';
}
```

#### 5. Bannière d'installation (à ajouter dans les pages publiques)
```html
<!-- Bannière sticky en bas -->
<div id="pwa-install-banner" class="pwa-install-banner" style="display: none;">
    <div class="banner-content">
        <div class="banner-icon">
            <img src="/assets/img/icons/icon-96x96.png" alt="Mulho">
        </div>
        <div class="banner-text">
            <strong>Installer l'application</strong>
            <span>Accès rapide et mode hors-ligne</span>
        </div>
    </div>
    <div class="banner-actions">
        <button id="pwa-install-btn" class="install-btn">Installer</button>
        <button class="dismiss-btn" onclick="this.parentElement.parentElement.style.display='none'">
            ✕
        </button>
    </div>
</div>

<style>
.pwa-install-banner {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: white;
    border-top: 1px solid #e5e7eb;
    padding: 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.1);
    z-index: 1000;
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from {
        transform: translateY(100%);
    }
    to {
        transform: translateY(0);
    }
}

.banner-content {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
}

.banner-icon img {
    width: 48px;
    height: 48px;
    border-radius: 12px;
}

.banner-text {
    display: flex;
    flex-direction: column;
}

.banner-text strong {
    font-size: 1rem;
    color: #1f2937;
}

.banner-text span {
    font-size: 0.875rem;
    color: #6b7280;
}

.banner-actions {
    display: flex;
    gap: 0.5rem;
}

.install-btn {
    background: #D4AF37;
    color: #1f2937;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
}

.install-btn:hover {
    background: #F4C430;
}

.dismiss-btn {
    background: transparent;
    border: none;
    font-size: 1.5rem;
    color: #6b7280;
    cursor: pointer;
    padding: 0.5rem;
}

@media (max-width: 640px) {
    .pwa-install-banner {
        flex-direction: column;
        align-items: stretch;
    }

    .banner-actions {
        width: 100%;
    }

    .install-btn {
        flex: 1;
    }
}
</style>
```

#### 6. Modifications dans les headers HTML
```html
<!-- Dans toutes les pages publiques -->
<head>
    <!-- Meta tags PWA -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Mulho">

    <!-- Manifest -->
    <link rel="manifest" href="/manifest.json">

    <!-- Icônes iOS -->
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/img/icons/icon-180x180.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/assets/img/icons/icon-152x152.png">
    <link rel="apple-touch-icon" sizes="120x120" href="/assets/img/icons/icon-120x120.png">

    <!-- Theme color -->
    <meta name="theme-color" content="#D4AF37" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#1F2937" media="(prefers-color-scheme: dark)">

    <!-- Existing head content -->
</head>

<body>
    <!-- Existing content -->

    <!-- Scripts PWA -->
    <script>
        // Enregistrer service worker
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js')
                .then(reg => console.log('SW registered:', reg))
                .catch(err => console.error('SW registration failed:', err));
        }
    </script>
    <script src="/assets/js/pwa-install.js"></script>
</body>
```

#### 7. Mode Offline pour le panier
```javascript
// public/assets/js/offline-cart.js

class OfflineCart {
    constructor() {
        this.dbName = 'mulho-db';
        this.storeName = 'pending-orders';
        this.init();
    }

    async init() {
        this.db = await this.openDB();
        this.syncPendingOrders();
    }

    openDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, 1);
            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result);
        });
    }

    async saveOrderOffline(orderData) {
        const transaction = this.db.transaction([this.storeName], 'readwrite');
        const store = transaction.objectStore(this.storeName);

        const order = {
            ...orderData,
            createdAt: new Date().toISOString(),
            status: 'pending-sync'
        };

        store.add(order);

        // Afficher notification
        this.showOfflineNotification();

        // Tenter sync en arrière-plan si possible
        if ('sync' in registration) {
            const registration = await navigator.serviceWorker.ready;
            await registration.sync.register('sync-orders');
        }
    }

    async syncPendingOrders() {
        if (!navigator.onLine) return;

        const transaction = this.db.transaction([this.storeName], 'readonly');
        const store = transaction.objectStore(this.storeName);
        const orders = await store.getAll();

        for (const order of orders) {
            try {
                const response = await fetch('/api/create-order.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(order)
                });

                if (response.ok) {
                    // Supprimer de IndexedDB
                    const delTransaction = this.db.transaction([this.storeName], 'readwrite');
                    const delStore = delTransaction.objectStore(this.storeName);
                    delStore.delete(order.id);

                    this.showSyncSuccessNotification();
                }
            } catch (error) {
                console.error('Sync failed:', error);
            }
        }
    }

    showOfflineNotification() {
        const banner = document.createElement('div');
        banner.className = 'offline-order-banner';
        banner.innerHTML = `
            <div class="banner-icon">📡</div>
            <div class="banner-text">
                <strong>Commande enregistrée hors ligne</strong>
                <span>Elle sera envoyée dès que vous serez connecté</span>
            </div>
        `;
        document.body.appendChild(banner);

        setTimeout(() => banner.remove(), 5000);
    }

    showSyncSuccessNotification() {
        const banner = document.createElement('div');
        banner.className = 'sync-success-banner';
        banner.innerHTML = `
            <div class="banner-icon">✅</div>
            <div class="banner-text">
                <strong>Commande envoyée!</strong>
                <span>Votre commande a été synchronisée avec succès</span>
            </div>
        `;
        document.body.appendChild(banner);

        setTimeout(() => banner.remove(), 5000);
    }
}

// Modifier le formulaire de commande
document.getElementById('orderForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = new FormData(e.target);
    const orderData = Object.fromEntries(formData);

    if (!navigator.onLine) {
        // Sauvegarder hors ligne
        const offlineCart = new OfflineCart();
        await offlineCart.saveOrderOffline(orderData);
    } else {
        // Envoyer normalement
        submitOrder(orderData);
    }
});

// Écouter le retour en ligne
window.addEventListener('online', () => {
    const offlineCart = new OfflineCart();
    offlineCart.syncPendingOrders();
});
```

**Complexité:** ⭐⭐⭐ (Moyenne)
- **Temps estimé:** 1 semaine
- **Compétences requises:** Service workers, PWA, IndexedDB
- **Coûts:** Gratuit
- **Bénéfices:**
  - Installation sur écran d'accueil (↑30% rétention)
  - Mode offline (commandes même sans réseau)
  - Notifications push natives
  - Chargement plus rapide (cache)
  - Expérience app-like

---

## 🟡 NIVEAU 2 - HAUTE PRIORITÉ (Avantage Compétitif)

### 4. 🤖 Programme de Fidélité & Gamification

**Utilité:**
- Augmenter la rétention client de 30-50%
- Customer Lifetime Value x2
- Fréquence de commande +40%
- Encourager partage sur réseaux sociaux

**Comment l'intégrer:**

#### Base de Données
```sql
-- Table des points de fidélité
CREATE TABLE loyalty_points (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    points INT DEFAULT 0,
    points_lifetime INT DEFAULT 0, -- Total cumulé
    tier ENUM('bronze', 'silver', 'gold', 'platinum') DEFAULT 'bronze',
    tier_progress INT DEFAULT 0, -- Points pour prochain tier
    next_tier VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Historique des points
CREATE TABLE loyalty_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    points INT NOT NULL, -- Négatif si dépense
    type ENUM('earn', 'redeem', 'expire', 'bonus', 'referral'),
    source VARCHAR(50), -- 'order', 'review', 'referral', 'birthday', etc.
    reference_id INT, -- ID de la commande, avis, etc.
    description VARCHAR(255),
    balance_after INT,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Récompenses disponibles
CREATE TABLE loyalty_rewards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    description TEXT,
    points_cost INT,
    reward_type ENUM('discount_percent', 'discount_fixed', 'free_item', 'upgrade'),
    reward_value VARCHAR(100), -- '10' pour 10%, 'plat_id_15' pour plat gratuit
    min_order_amount INT DEFAULT 0,
    max_redemptions_per_user INT DEFAULT 1,
    total_available INT DEFAULT NULL, -- NULL = illimité
    is_active BOOLEAN DEFAULT 1,
    valid_from DATE,
    valid_until DATE,
    image VARCHAR(255),
    tier_required ENUM('bronze', 'silver', 'gold', 'platinum') DEFAULT 'bronze'
);

-- Rédemptions
CREATE TABLE loyalty_redemptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    reward_id INT NOT NULL,
    points_spent INT,
    commande_id INT NULL,
    redeemed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (reward_id) REFERENCES loyalty_rewards(id),
    FOREIGN KEY (commande_id) REFERENCES commandes(id)
);

-- Missions/Défis
CREATE TABLE loyalty_missions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    description TEXT,
    type ENUM('order_count', 'order_amount', 'referral', 'review', 'checkin', 'streak'),
    target_value INT, -- Ex: 5 commandes, 10000 FCFA
    reward_points INT,
    duration_days INT DEFAULT 30,
    is_recurring BOOLEAN DEFAULT 0, -- Mensuel ?
    is_active BOOLEAN DEFAULT 1,
    icon VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Progression des missions par utilisateur
CREATE TABLE user_mission_progress (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    mission_id INT NOT NULL,
    current_value INT DEFAULT 0,
    target_value INT,
    is_completed BOOLEAN DEFAULT 0,
    completed_at TIMESTAMP NULL,
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (mission_id) REFERENCES loyalty_missions(id)
);

-- Badges
CREATE TABLE badges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    description TEXT,
    icon VARCHAR(255),
    unlock_condition VARCHAR(255), -- 'orders_10', 'referrals_5'
    rarity ENUM('common', 'rare', 'epic', 'legendary'),
    points_bonus INT DEFAULT 0
);

CREATE TABLE user_badges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    badge_id INT NOT NULL,
    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (badge_id) REFERENCES badges(id),
    UNIQUE KEY unique_user_badge (user_id, badge_id)
);

-- Parrainage
CREATE TABLE referrals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    referrer_user_id INT NOT NULL,
    referred_user_id INT NOT NULL,
    referral_code VARCHAR(20) UNIQUE,
    status ENUM('pending', 'completed', 'expired'),
    referrer_reward_points INT,
    referred_reward_points INT,
    first_order_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (referrer_user_id) REFERENCES users(id),
    FOREIGN KEY (referred_user_id) REFERENCES users(id)
);
```

#### Classe LoyaltyManager
```php
// admin/classes/LoyaltyManager.php
class LoyaltyManager {
    private $db;
    private $pointsPerFCFA = 1; // 1 point par 100 FCFA
    private $tiers = [
        'bronze' => ['min' => 0, 'multiplier' => 1],
        'silver' => ['min' => 500, 'multiplier' => 1.25],
        'gold' => ['min' => 2000, 'multiplier' => 1.5],
        'platinum' => ['min' => 5000, 'multiplier' => 2]
    ];

    public function __construct($conn) {
        $this->db = $conn;
    }

    /**
     * Calculer et attribuer points pour une commande
     */
    public function awardPointsForOrder($userId, $orderId, $orderAmount) {
        // Obtenir tier actuel
        $userLoyalty = $this->getUserLoyalty($userId);
        $tier = $userLoyalty['tier'] ?? 'bronze';
        $multiplier = $this->tiers[$tier]['multiplier'];

        // Calculer points de base (1 point / 100 FCFA)
        $basePoints = floor($orderAmount / 100);

        // Appliquer multiplicateur de tier
        $points = floor($basePoints * $multiplier);

        // Bonus si première commande de la journée
        if ($this->isFirstOrderToday($userId)) {
            $points += 10;
            $description = "Commande #{$orderId} + Bonus première commande du jour";
        } else {
            $description = "Commande #{$orderId}";
        }

        // Ajouter points
        $this->addPoints($userId, $points, 'earn', 'order', $orderId, $description);

        // Vérifier streak (série de jours consécutifs)
        $streak = $this->updateStreak($userId);
        if ($streak > 0 && $streak % 7 === 0) {
            // Bonus toutes les 7 jours consécutifs
            $bonusPoints = 50 * ($streak / 7);
            $this->addPoints($userId, $bonusPoints, 'bonus', 'streak', null,
                "Série de {$streak} jours consécutifs!");
        }

        // Mettre à jour progression des missions
        $this->updateMissionProgress($userId, 'order_count', 1);
        $this->updateMissionProgress($userId, 'order_amount', $orderAmount);

        // Vérifier unlock de badges
        $this->checkBadgeUnlocks($userId);

        return [
            'points_earned' => $points,
            'new_balance' => $this->getPointsBalance($userId),
            'tier' => $tier,
            'streak' => $streak
        ];
    }

    /**
     * Ajouter des points
     */
    private function addPoints($userId, $points, $type, $source, $referenceId, $description) {
        // Points expirent après 1 an
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 year'));

        // Obtenir balance actuel
        $currentBalance = $this->getPointsBalance($userId);
        $newBalance = $currentBalance + $points;

        // Insérer transaction
        $stmt = $this->db->prepare("
            INSERT INTO loyalty_transactions
            (user_id, points, type, source, reference_id, description, balance_after, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId, $points, $type, $source, $referenceId,
            $description, $newBalance, $expiresAt
        ]);

        // Mettre à jour table loyalty_points
        $stmt = $this->db->prepare("
            INSERT INTO loyalty_points (user_id, points, points_lifetime)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                points = points + VALUES(points),
                points_lifetime = points_lifetime + VALUES(points),
                updated_at = NOW()
        ");
        $stmt->execute([$userId, $points, $points]);

        // Vérifier si tier up
        $this->checkTierUpgrade($userId);

        return $newBalance;
    }

    /**
     * Racheter une récompense
     */
    public function redeemReward($userId, $rewardId, $orderId = null) {
        // Vérifier récompense existe et est active
        $reward = $this->getReward($rewardId);
        if (!$reward || !$reward['is_active']) {
            return ['success' => false, 'error' => 'Récompense non disponible'];
        }

        // Vérifier tier requis
        $userLoyalty = $this->getUserLoyalty($userId);
        if (!$this->hasTierAccess($userLoyalty['tier'], $reward['tier_required'])) {
            return ['success' => false, 'error' => 'Tier insuffisant'];
        }

        // Vérifier points suffisants
        $balance = $this->getPointsBalance($userId);
        if ($balance < $reward['points_cost']) {
            return [
                'success' => false,
                'error' => 'Points insuffisants',
                'required' => $reward['points_cost'],
                'current' => $balance
            ];
        }

        // Vérifier limite de rédemptions
        $redemptionCount = $this->getUserRedemptionCount($userId, $rewardId);
        if ($redemptionCount >= $reward['max_redemptions_per_user']) {
            return ['success' => false, 'error' => 'Limite atteinte'];
        }

        // Déduire points
        $this->addPoints(
            $userId,
            -$reward['points_cost'],
            'redeem',
            'reward',
            $rewardId,
            "Échange: {$reward['name']}"
        );

        // Enregistrer rédemption
        $stmt = $this->db->prepare("
            INSERT INTO loyalty_redemptions
            (user_id, reward_id, points_spent, commande_id)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $rewardId, $reward['points_cost'], $orderId]);

        // Appliquer la récompense (retourner code à appliquer)
        $code = $this->generateRewardCode($reward);

        return [
            'success' => true,
            'reward' => $reward,
            'code' => $code,
            'new_balance' => $this->getPointsBalance($userId)
        ];
    }

    /**
     * Système de parrainage
     */
    public function generateReferralCode($userId) {
        $code = strtoupper(substr(md5($userId . time()), 0, 8));

        $stmt = $this->db->prepare("
            INSERT INTO referrals (referrer_user_id, referral_code, status)
            VALUES (?, ?, 'pending')
            ON DUPLICATE KEY UPDATE referral_code = VALUES(referral_code)
        ");
        $stmt->execute([$userId, $code]);

        return $code;
    }

    public function processReferral($referralCode, $newUserId) {
        // Trouver le parrain
        $stmt = $this->db->prepare("
            SELECT referrer_user_id
            FROM referrals
            WHERE referral_code = ? AND status = 'pending'
            LIMIT 1
        ");
        $stmt->execute([$referralCode]);
        $referral = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$referral) {
            return ['success' => false, 'error' => 'Code invalide'];
        }

        $referrerId = $referral['referrer_user_id'];

        // Vérifier que le nouveau utilisateur n'est pas le parrain lui-même
        if ($referrerId === $newUserId) {
            return ['success' => false, 'error' => 'Auto-parrainage interdit'];
        }

        // Créer lien de parrainage
        $stmt = $this->db->prepare("
            UPDATE referrals
            SET referred_user_id = ?,
                status = 'pending'
            WHERE referral_code = ?
        ");
        $stmt->execute([$newUserId, $referralCode]);

        // Bonus immédiat pour le filleul (100 points)
        $this->addPoints(
            $newUserId,
            100,
            'referral',
            'referral',
            null,
            "Bienvenue! Bonus de parrainage"
        );

        return [
            'success' => true,
            'message' => 'Parrainage enregistré! 100 points bonus'
        ];
    }

    public function completeReferral($referralCode, $firstOrderId) {
        $stmt = $this->db->prepare("
            SELECT referrer_user_id, referred_user_id
            FROM referrals
            WHERE referral_code = ? AND status = 'pending'
        ");
        $stmt->execute([$referralCode]);
        $referral = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$referral) return;

        // Bonus pour le parrain (200 points)
        $this->addPoints(
            $referral['referrer_user_id'],
            200,
            'referral',
            'referral',
            $firstOrderId,
            "Parrainage complété!"
        );

        // Bonus supplémentaire pour le filleul (50 points)
        $this->addPoints(
            $referral['referred_user_id'],
            50,
            'bonus',
            'referral',
            $firstOrderId,
            "Première commande complétée!"
        );

        // Marquer comme complété
        $stmt = $this->db->prepare("
            UPDATE referrals
            SET status = 'completed',
                first_order_id = ?,
                completed_at = NOW()
            WHERE referral_code = ?
        ");
        $stmt->execute([$firstOrderId, $referralCode]);
    }

    /**
     * Système de missions
     */
    public function updateMissionProgress($userId, $missionType, $value) {
        // Trouver missions actives de ce type
        $stmt = $this->db->prepare("
            SELECT m.*, COALESCE(ump.current_value, 0) as current_progress
            FROM loyalty_missions m
            LEFT JOIN user_mission_progress ump
                ON m.id = ump.mission_id AND ump.user_id = ?
            WHERE m.type = ?
                AND m.is_active = 1
                AND (ump.is_completed = 0 OR ump.is_completed IS NULL)
                AND (ump.expires_at IS NULL OR ump.expires_at > NOW())
        ");
        $stmt->execute([$userId, $missionType]);
        $missions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($missions as $mission) {
            $newProgress = $mission['current_progress'] + $value;

            // Insérer ou mettre à jour progression
            $stmt = $this->db->prepare("
                INSERT INTO user_mission_progress
                (user_id, mission_id, current_value, target_value, expires_at)
                VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? DAY))
                ON DUPLICATE KEY UPDATE
                    current_value = current_value + VALUES(current_value)
            ");
            $stmt->execute([
                $userId,
                $mission['id'],
                $value,
                $mission['target_value'],
                $mission['duration_days']
            ]);

            // Vérifier si mission complétée
            if ($newProgress >= $mission['target_value']) {
                $this->completeMission($userId, $mission['id'], $mission['reward_points']);
            }
        }
    }

    private function completeMission($userId, $missionId, $rewardPoints) {
        // Marquer comme complétée
        $stmt = $this->db->prepare("
            UPDATE user_mission_progress
            SET is_completed = 1,
                completed_at = NOW()
            WHERE user_id = ? AND mission_id = ?
        ");
        $stmt->execute([$userId, $missionId]);

        // Attribuer points
        $this->addPoints(
            $userId,
            $rewardPoints,
            'bonus',
            'mission',
            $missionId,
            "Mission accomplie!"
        );
    }

    /**
     * Badges
     */
    private function checkBadgeUnlocks($userId) {
        $stats = $this->getUserStats($userId);

        $badgeConditions = [
            'first_order' => $stats['order_count'] >= 1,
            'orders_10' => $stats['order_count'] >= 10,
            'orders_50' => $stats['order_count'] >= 50,
            'orders_100' => $stats['order_count'] >= 100,
            'big_spender' => $stats['total_spent'] >= 100000,
            'reviewer' => $stats['review_count'] >= 5,
            'referrer' => $stats['referral_count'] >= 3,
            'streak_7' => $stats['current_streak'] >= 7,
            'streak_30' => $stats['current_streak'] >= 30
        ];

        foreach ($badgeConditions as $condition => $met) {
            if ($met) {
                $this->unlockBadge($userId, $condition);
            }
        }
    }

    private function unlockBadge($userId, $conditionKey) {
        $stmt = $this->db->prepare("
            SELECT id, points_bonus
            FROM badges
            WHERE unlock_condition = ?
        ");
        $stmt->execute([$conditionKey]);
        $badge = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$badge) return;

        // Vérifier si déjà unlocked
        $stmt = $this->db->prepare("
            SELECT id FROM user_badges
            WHERE user_id = ? AND badge_id = ?
        ");
        $stmt->execute([$userId, $badge['id']]);

        if ($stmt->fetch()) return; // Déjà unlocked

        // Unlock
        $stmt = $this->db->prepare("
            INSERT INTO user_badges (user_id, badge_id)
            VALUES (?, ?)
        ");
        $stmt->execute([$userId, $badge['id']]);

        // Bonus points
        if ($badge['points_bonus'] > 0) {
            $this->addPoints(
                $userId,
                $badge['points_bonus'],
                'bonus',
                'badge',
                $badge['id'],
                "Badge débloqué!"
            );
        }
    }

    /**
     * Helpers
     */
    private function getPointsBalance($userId) {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(points), 0) as balance
            FROM loyalty_transactions
            WHERE user_id = ?
                AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }

    private function getUserLoyalty($userId) {
        $stmt = $this->db->prepare("
            SELECT * FROM loyalty_points WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function checkTierUpgrade($userId) {
        $lifetime = $this->getLifetimePoints($userId);
        $currentTier = 'bronze';

        foreach (array_reverse($this->tiers, true) as $tier => $config) {
            if ($lifetime >= $config['min']) {
                $currentTier = $tier;
                break;
            }
        }

        $stmt = $this->db->prepare("
            UPDATE loyalty_points
            SET tier = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$currentTier, $userId]);
    }
}
```

**Complexité:** ⭐⭐⭐⭐ (Élevée)
- **Temps estimé:** 2-3 semaines
- **Impact:** Très fort sur rétention

---

### 5. 📊 Analytics & Business Intelligence Avancé

**Utilité:**
- Prédiction des ventes
- Optimisation du stock
- Insights actionnables
- Détection des tendances

**Comment l'intégrer:**

```php
// admin/classes/AnalyticsEngine.php
class AnalyticsEngine {
    public function predictNextWeekSales() {
        // Machine learning basique (régression linéaire)
    }

    public function getTopSellingDishes($period = '30days') {
        // Analyse des tendances
    }

    public function getPeakHours() {
        // Heures de pointe
    }

    public function getCustomerSegments() {
        // RFM Analysis (Recency, Frequency, Monetary)
    }

    public function getChurnRisk() {
        // Clients à risque de partir
    }
}
```

**Complexité:** ⭐⭐⭐⭐⭐ (Très élevée)
- **Temps estimé:** 3-4 semaines

---

### 6. 🍽️ Menu Dynamique avec IA

**Utilité:**
- Recommandations personnalisées
- Menus adaptatifs selon météo/saison
- Suggestions basées sur historique

**Complexité:** ⭐⭐⭐⭐⭐ (Très élevée)
- **Temps estimé:** 3-4 semaines

---

### 7. 📲 Commande Vocale & Chatbot

**Utilité:**
- Prise de commande par voix
- Support client 24/7
- Réponses instantanées aux FAQ

**Complexité:** ⭐⭐⭐⭐⭐ (Très élevée)
- **Temps estimé:** 4-5 semaines

---

### 8. 🚗 Livraison avec Tracking en Temps Réel

**Utilité:**
- Suivi GPS du livreur
- ETA précis
- Communication client-livreur

**Complexité:** ⭐⭐⭐⭐ (Élevée)
- **Temps estimé:** 2-3 semaines

---

## 📋 RÉSUMÉ & ROADMAP RECOMMANDÉE

### Phase 1 (Mois 1-2) - Monétisation
1. ✅ Paiement en ligne (Orange Money, Wave, Paydunya)
2. ✅ Notifications Push & SMS

### Phase 2 (Mois 3) - Engagement
3. ✅ PWA (Application mobile)
4. ✅ Programme de fidélité

### Phase 3 (Mois 4-5) - Optimisation
5. ✅ Analytics avancé
6. ✅ Livraison avec tracking

### Phase 4 (Mois 6+) - Innovation
7. ✅ IA & Recommandations
8. ✅ Chatbot & Commande vocale

---

**Créé le:** 2025-10-24
**Version:** 1.0

