# 🔔 Système de Notifications Push & SMS - Guide d'Installation

## 🎯 Vue d'ensemble

Système complet de notifications multi-canal pour améliorer l'engagement client:
- **📱 Push Notifications** (Firebase Cloud Messaging) - Web, Android, iOS
- **📲 SMS** (Orange SMS API Sénégal)
- **📧 Email** (SMTP existant)

**Impact attendu:**
- ✅ 70% taux d'ouverture notifications push
- ✅ 98% taux de livraison SMS
- ✅ Réduction de 80% des no-shows grâce aux rappels

---

## 📦 Ce qui a été créé

### 1. Base de Données (6 tables)

| Table | Description | Lignes créées |
|-------|-------------|---------------|
| `user_notification_preferences` | Préférences utilisateur par canal et type | - |
| `push_notification_tokens` | Tokens FCM des utilisateurs | - |
| `notifications_log` | Historique complet toutes notifications | - |
| `notification_templates` | Templates multi-canal | **7 templates** |
| `scheduled_notifications` | Notifications programmées (rappels) | - |
| `notification_statistics` | Stats quotidiennes par canal | - |

### 2. Backend PHP (4 classes)

| Fichier | Description | Lignes |
|---------|-------------|--------|
| **NotificationChannel.php** | Classe abstraite de base | 170 |
| **PushNotificationService.php** | Service Firebase Push | 400 |
| **SMSService.php** | Service Orange SMS | 350 |
| **NotificationManager.php** | Gestionnaire centralisé | 450 |

### 3. Frontend JavaScript

| Fichier | Description |
|---------|-------------|
| **firebase-messaging-sw.js** | Service Worker pour push background |
| **push-notifications.js** | Client-side push subscription |

### 4. API Endpoints

| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `/api/register_push_token.php` | POST | Enregistrer token FCM |
| `/api/deactivate_push_token.php` | POST | Désactiver token |
| `/api/notification_clicked.php` | POST | Logger clic notification |

---

## 🚀 Installation

### Étape 1: Exécuter le script SQL

```bash
# Via phpMyAdmin
1. Ouvrir http://localhost/phpmyadmin
2. Sélectionner base 'restaurant'
3. Onglet "SQL"
4. Copier le contenu de admin/sql/create_notifications_system.sql
5. Exécuter
```

**Résultat attendu:**
```
✅ 6 tables créées
✅ 7 templates insérés
✅ 1 trigger créé
✅ 1 vue créée
✅ 1 procédure stockée créée
```

### Étape 2: Configurer Firebase

#### A. Créer un projet Firebase

1. Aller sur https://console.firebase.google.com/
2. Cliquer "Ajouter un projet"
3. Nom: "Restaurant Mulho" (ou votre nom)
4. Activer Google Analytics (optionnel)
5. Créer le projet

#### B. Activer Cloud Messaging

1. Dans le menu, aller dans **"Cloud Messaging"**
2. Configurer une application **Web**
3. Copier la configuration Firebase:

```javascript
const firebaseConfig = {
  apiKey: "AIzaSyXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX",
  authDomain: "restaurant-mulho.firebaseapp.com",
  projectId: "restaurant-mulho",
  storageBucket: "restaurant-mulho.appspot.com",
  messagingSenderId: "123456789012",
  appId: "1:123456789012:web:abcdefghijklmnop"
};
```

#### C. Générer la clé VAPID

1. Dans **Cloud Messaging** → **Web configuration**
2. Cliquer "Générer une paire de clés"
3. Copier la clé publique VAPID

#### D. Obtenir la Server Key

1. **Paramètres du projet** (icône engrenage)
2. Onglet **"Cloud Messaging"**
3. Copier **"Clé du serveur"** (Server Key)

### Étape 3: Configurer le fichier .env

Ajouter ces lignes dans `.env`:

```env
# ============================================
# FIREBASE CLOUD MESSAGING (Push Notifications)
# ============================================
FIREBASE_SERVER_KEY=AAAA-your-server-key-here
FIREBASE_API_KEY=AIzaSy-your-api-key
FIREBASE_AUTH_DOMAIN=restaurant-mulho.firebaseapp.com
FIREBASE_PROJECT_ID=restaurant-mulho
FIREBASE_STORAGE_BUCKET=restaurant-mulho.appspot.com
FIREBASE_MESSAGING_SENDER_ID=123456789012
FIREBASE_APP_ID=1:123456789012:web:abcdef
FIREBASE_VAPID_KEY=BNxxx-your-vapid-public-key

# ============================================
# ORANGE SMS API (Sénégal)
# ============================================
ORANGE_SMS_CLIENT_ID=your-client-id
ORANGE_SMS_CLIENT_SECRET=your-client-secret
ORANGE_SMS_SENDER_NAME=RestauMulho
```

### Étape 4: Mettre à jour firebase-messaging-sw.js

Éditer `public/firebase-messaging-sw.js` ligne 10-17:

```javascript
const firebaseConfig = {
    apiKey: "VOTRE_FIREBASE_API_KEY",
    authDomain: "votre-projet.firebaseapp.com",
    projectId: "votre-projet-id",
    storageBucket: "votre-projet.appspot.com",
    messagingSenderId: "123456789",
    appId: "1:123456789:web:abcdef"
};
```

### Étape 5: Intégrer dans vos pages

#### A. Dans la page de commande (commander.php)

Ajouter avant la fermeture de `</body>`:

```html
<!-- Push Notifications -->
<script src="/assets/js/push-notifications.js"></script>
<script>
// Configuration Firebase (depuis .env)
const firebaseConfig = {
    apiKey: "<?= $_ENV['FIREBASE_API_KEY'] ?>",
    authDomain: "<?= $_ENV['FIREBASE_AUTH_DOMAIN'] ?>",
    projectId: "<?= $_ENV['FIREBASE_PROJECT_ID'] ?>",
    storageBucket: "<?= $_ENV['FIREBASE_STORAGE_BUCKET'] ?>",
    messagingSenderId: "<?= $_ENV['FIREBASE_MESSAGING_SENDER_ID'] ?>",
    appId: "<?= $_ENV['FIREBASE_APP_ID'] ?>",
    vapidKey: "<?= $_ENV['FIREBASE_VAPID_KEY'] ?>"
};

// Initialiser après validation commande
document.addEventListener('DOMContentLoaded', async function() {
    // Initialiser le gestionnaire de push
    window.pushManager = await initPushNotifications(firebaseConfig);
});

// Après une commande réussie, demander la permission
function onOrderSuccess(orderData) {
    if (window.pushManager && !window.pushManager.isEnabled()) {
        setTimeout(() => {
            if (confirm('Voulez-vous recevoir des notifications sur l\'état de votre commande?')) {
                window.pushManager.requestPermission({
                    email: orderData.email,
                    phone: orderData.phone,
                    user_id: orderData.user_id
                });
            }
        }, 1000);
    }
}
</script>
```

#### B. Envoyer une notification après confirmation commande

Dans votre script de traitement de commande:

```php
<?php
require_once __DIR__ . '/admin/classes/Notifications/NotificationManager.php';

// Après insertion commande
$notificationManager = new NotificationManager($conn);

// Notification de confirmation
$notificationManager->notifyOrderConfirmed([
    'id' => $commande_id,
    'numero_commande' => $numero_commande,
    'client_nom' => $nom,
    'client_email' => $email,
    'client_telephone' => $telephone,
    'total' => $total
]);
```

### Étape 6: Configurer Orange SMS (Sénégal)

#### A. Créer un compte développeur

1. Aller sur https://developer.orange.com/
2. S'inscrire ou se connecter
3. Créer une application **"SMS API"**
4. Région: **Sénégal**

#### B. Obtenir les credentials

1. Dans votre application → **Credentials**
2. Copier:
   - `Client ID`
   - `Client Secret`

3. Ajouter dans `.env`:
```env
ORANGE_SMS_CLIENT_ID=votre-client-id
ORANGE_SMS_CLIENT_SECRET=votre-client-secret
ORANGE_SMS_SENDER_NAME=RestauMulho
```

#### C. Tester l'envoi SMS

```php
<?php
require_once __DIR__ . '/admin/classes/Notifications/SMSService.php';

$smsService = new SMSService($conn);

$result = $smsService->send(
    ['phone' => '+221771234567', 'name' => 'Test'],
    'autre',
    ['message' => 'Test SMS depuis Restaurant Mulho']
);

var_dump($result);
```

---

## 📱 Utilisation

### Envoyer une notification simple

```php
<?php
$notificationManager = new NotificationManager($conn);

$recipient = [
    'email' => 'client@example.com',
    'phone' => '+221771234567',
    'name' => 'Mamadou Diallo'
];

$variables = [
    'nom' => 'Mamadou',
    'numero_commande' => '12345',
    'montant' => '15 000'
];

// Envoyer sur tous les canaux
$result = $notificationManager->send(
    $recipient,
    'commande_confirmee',
    $variables
);
```

### Programmer un rappel de réservation

```php
<?php
// Créer rappel 2h avant la réservation
$notificationManager->scheduleReservationReminder([
    'id' => $reservation_id,
    'nom' => 'Fatou Sall',
    'email' => 'fatou@example.com',
    'telephone' => '+221771234567',
    'date_reservation' => '2025-10-25',
    'heure_reservation' => '19:00:00',
    'nombre_personnes' => 4
], 2); // 2 heures avant
```

### Traiter les notifications programmées (CRON)

Créer un fichier `admin/cron/process_notifications.php`:

```php
<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../classes/Notifications/NotificationManager.php';

$notificationManager = new NotificationManager($conn);
$result = $notificationManager->processPendingNotifications();

echo "Traitement terminé: {$result['processed']} notifications envoyées\n";
```

Ajouter au crontab (Linux) ou Planificateur de tâches (Windows):

```bash
# Toutes les 5 minutes
*/5 * * * * php /path/to/restaurant/admin/cron/process_notifications.php
```

---

## 🎨 Templates Disponibles

| Template | Type | Canaux | Variables |
|----------|------|--------|-----------|
| `commande_confirmee` | Commande | Push, SMS, Email | nom, numero_commande, montant, temps_preparation |
| `commande_prete` | Commande | Push, SMS, Email | nom, numero_commande |
| `commande_en_livraison` | Commande | Push, SMS | nom, numero_commande, heure_arrivee |
| `reservation_confirmee` | Réservation | Push, SMS, Email | nom, nombre_personnes, date, heure, num_table |
| `rappel_reservation_2h` | Rappel | Push, SMS | nom, heure, nombre_personnes |
| `paiement_reussi` | Paiement | Push, SMS | nom, montant, provider, numero_commande |
| `promotion` | Marketing | Push, Email | titre_promotion, message_promotion |

---

## 📊 Statistiques

### Obtenir les stats du jour

```php
<?php
$stats = $notificationManager->getTodayStats();

echo "Push envoyés: " . $stats['push_sent'] . "\n";
echo "SMS envoyés: " . $stats['sms_sent'] . "\n";
echo "Coût SMS: " . $stats['sms_cost'] . " FCFA\n";
echo "Email ouverts: " . $stats['email_opened'] . "\n";
```

### Vue SQL pour dashboard

```sql
SELECT * FROM v_notification_stats_today;
```

---

## 🔧 Dépannage

### Problème: Notifications push ne s'affichent pas

**Vérifier:**
1. Permission accordée dans le navigateur
2. Service Worker enregistré: `navigator.serviceWorker.getRegistrations()`
3. Token FCM obtenu et enregistré
4. Clé serveur Firebase correcte dans `.env`

**Console du navigateur:**
```javascript
// Vérifier le token
console.log(localStorage.getItem('fcm_token'));

// Vérifier la permission
console.log(Notification.permission);
```

### Problème: SMS non envoyés

**Vérifier:**
1. Credentials Orange SMS dans `.env`
2. Format du numéro: `+221XXXXXXXXX`
3. Crédit SMS disponible sur compte Orange
4. Logs: `SELECT * FROM notifications_log WHERE channel = 'sms' ORDER BY created_at DESC LIMIT 10;`

### Problème: Rappels non envoyés

**Vérifier:**
1. CRON configuré et actif
2. Notifications programmées: `SELECT * FROM scheduled_notifications WHERE status = 'pending';`
3. Logs du CRON: `/var/log/cron` (Linux)

---

## 💰 Coûts Estimés

### Orange SMS (Sénégal)
- **25 FCFA** par SMS
- Pack 1000 SMS: ~20 000 FCFA
- Pack 5000 SMS: ~90 000 FCFA

### Firebase Cloud Messaging
- **GRATUIT** jusqu'à 1 million de messages/mois
- Au-delà: très bas coût (~$0.001/message)

### Exemple budget mensuel
- 200 clients/jour
- 3 SMS par client (confirmation, prêt, rappel)
- 600 SMS/jour × 30 jours = 18 000 SMS/mois
- **Coût: 450 000 FCFA/mois**

**Économies:**
- Push gratuit réduit SMS de ~40%
- **Coût réel: ~270 000 FCFA/mois**

---

## 📈 Métriques de Succès

À mesurer après 1 mois:

- ✅ **Taux d'opt-in push**: Objectif 50%+
- ✅ **Taux d'ouverture push**: Objectif 70%+
- ✅ **Taux de livraison SMS**: Objectif 98%+
- ✅ **Réduction no-shows réservations**: Objectif -80%
- ✅ **Satisfaction client**: Enquête post-livraison

---

## 🎯 Roadmap Future

- [ ] Support iOS native app
- [ ] Support Android native app
- [ ] Rich notifications (images, boutons)
- [ ] Notifications géolocalisées
- [ ] A/B testing des messages
- [ ] Segmentation avancée
- [ ] Analytics détaillées

---

**Date de création**: 2025-10-24
**Version**: 1.0
**Compatibilité**: PHP 7.4+, MySQL 5.7+, Navigateurs modernes
