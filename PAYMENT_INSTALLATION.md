# 💳 Guide d'Installation - Paiement en Ligne

Guide complet pour installer et configurer le système de paiement en ligne (Orange Money, Wave, Paydunya).

---

## 📋 Table des matières

1. [Prérequis](#prérequis)
2. [Installation de la base de données](#installation-de-la-base-de-données)
3. [Configuration Orange Money](#configuration-orange-money)
4. [Configuration Wave](#configuration-wave)
5. [Configuration Paydunya](#configuration-paydunya)
6. [Configuration du fichier .env](#configuration-du-fichier-env)
7. [Tests](#tests)
8. [Mise en production](#mise-en-production)
9. [Dépannage](#dépannage)

---

## ✅ Prérequis

- PHP 7.1+ avec extensions:
  - PDO
  - cURL
  - JSON
  - OpenSSL
- MySQL 5.7+
- Accès aux comptes marchands des providers de paiement
- HTTPS activé (requis en production)

---

## 📦 Installation de la base de données

### Étape 1: Exécuter le script SQL

```bash
# Se connecter à MySQL
mysql -u root -p restaurant

# Exécuter le script de création des tables
mysql -u root -p restaurant < admin/sql/create_payment_tables.sql
```

Ou via phpMyAdmin:
1. Ouvrir phpMyAdmin
2. Sélectionner la base de données `restaurant`
3. Aller dans l'onglet "SQL"
4. Copier-coller le contenu de `admin/sql/create_payment_tables.sql`
5. Cliquer sur "Exécuter"

### Étape 2: Vérifier que les tables sont créées

```sql
SHOW TABLES LIKE 'paiements%';
SHOW TABLES LIKE 'payment%';
```

Vous devriez voir:
- `paiements`
- `payment_webhooks_log`
- `payment_methods`
- `payment_statistics`

---

## 🍊 Configuration Orange Money

Orange Money est le service de mobile money le plus utilisé au Sénégal.

### 1. Créer un compte développeur Orange

1. Aller sur https://developer.orange.com/
2. Créer un compte développeur
3. Activer l'API "Orange Money Web Pay"
4. Créer une application

### 2. Récupérer les credentials

Dans votre dashboard Orange Developer:
- **Client ID**: Votre identifiant client OAuth2
- **Client Secret**: Votre secret client OAuth2
- **Merchant Key**: Votre clé marchand

### 3. Configuration Sandbox (Test)

Orange fournit un environnement de test:
- Endpoint: `https://api.orange.com/orange-money-webpay/dev/v1`
- Numéros de test fournis dans la documentation

### 4. Webhooks

URL à configurer dans votre dashboard Orange:
```
https://votre-domaine.com/public/payment_webhook.php?provider=orange_money
```

### 5. Ajouter dans .env

```env
ORANGE_MONEY_CLIENT_ID=votre_client_id
ORANGE_MONEY_CLIENT_SECRET=votre_client_secret
ORANGE_MONEY_MERCHANT_KEY=votre_merchant_key
PAYMENT_TEST_MODE=true
```

### 6. Frais Orange Money

- **Frais client**: ~1-2% (variable selon montant)
- **Frais marchand**: ~2.5%
- Consultez votre contrat pour les tarifs exacts

---

## 🌊 Configuration Wave

Wave est populaire au Sénégal car il propose 0% de frais pour les utilisateurs.

### 1. Créer un compte marchand Wave

1. Télécharger l'application Wave
2. Créer un compte marchand
3. Contacter le support Wave pour activer l'API
4. Demander les credentials API

### 2. Récupérer les credentials

Wave vous fournira:
- **API Key**: Clé d'authentification
- **API Secret**: Secret pour vérifier les webhooks

### 3. Configuration Sandbox

Wave fournit un environnement de test:
- Endpoint test: `https://api.wave.com/v1/test`
- Endpoint production: `https://api.wave.com/v1`

### 4. Webhooks

URL à configurer:
```
https://votre-domaine.com/public/payment_webhook.php?provider=wave
```

Wave utilise une signature HMAC pour sécuriser les webhooks.

### 5. Ajouter dans .env

```env
WAVE_API_KEY=votre_api_key
WAVE_API_SECRET=votre_api_secret
PAYMENT_TEST_MODE=true
```

### 6. Avantages Wave

- **0% de frais** pour les clients
- Frais marchands compétitifs (~1.5%)
- Interface simple et rapide
- Support des remboursements via API

---

## 💰 Configuration Paydunya

Paydunya est un agrégateur qui supporte Orange Money, Wave ET les cartes bancaires.

### 1. Créer un compte Paydunya

1. Aller sur https://paydunya.com/
2. S'inscrire comme marchand
3. Compléter la vérification KYC
4. Activer les méthodes de paiement souhaitées

### 2. Récupérer les credentials

Dans votre dashboard Paydunya:
- **Master Key**: Clé maître
- **Private Key**: Clé privée
- **Public Key**: Clé publique
- **Token**: Token d'authentification

### 3. Configuration Sandbox

Paydunya fournit un sandbox:
- URL: `https://app.paydunya.com/sandbox-api/v1`
- Credentials de test dans votre dashboard

### 4. Webhooks

URL de callback:
```
https://votre-domaine.com/public/payment_webhook.php?provider=paydunya
```

### 5. Ajouter dans .env

```env
PAYDUNYA_MASTER_KEY=votre_master_key
PAYDUNYA_PRIVATE_KEY=votre_private_key
PAYDUNYA_PUBLIC_KEY=votre_public_key
PAYDUNYA_TOKEN=votre_token
PAYMENT_TEST_MODE=true
```

### 6. Méthodes supportées par Paydunya

- Orange Money
- Wave
- VISA
- Mastercard
- Mobile banking

### 7. Frais Paydunya

- **Frais variables** selon la méthode de paiement
- Orange Money: ~2.9%
- Wave: ~1.9%
- Cartes bancaires: ~3.5%
- Consultez votre contrat

---

## ⚙️ Configuration du fichier .env

### 1. Copier .env.example

```bash
cp .env.example .env
```

### 2. Éditer le fichier .env

```env
# ============================================
# PAIEMENT EN LIGNE
# ============================================

# Orange Money
ORANGE_MONEY_CLIENT_ID=abc123
ORANGE_MONEY_CLIENT_SECRET=xyz789
ORANGE_MONEY_MERCHANT_KEY=merchant123

# Wave
WAVE_API_KEY=wave_key_123
WAVE_API_SECRET=wave_secret_456

# Paydunya
PAYDUNYA_MASTER_KEY=master_key_123
PAYDUNYA_PRIVATE_KEY=private_key_456
PAYDUNYA_PUBLIC_KEY=public_key_789
PAYDUNYA_TOKEN=token_abc

# Mode test
PAYMENT_TEST_MODE=true
```

### 3. Sécuriser le fichier .env

```bash
# Linux/Mac
chmod 600 .env

# Vérifier que .env est dans .gitignore
echo ".env" >> .gitignore
```

**⚠️ IMPORTANT**: NE JAMAIS committer le fichier .env dans Git!

---

## 🧪 Tests

### 1. Test de la base de données

```php
<?php
// test_db.php
require_once 'config/db.php';

$stmt = $conn->query("SELECT COUNT(*) FROM payment_methods WHERE is_active = 1");
echo "Méthodes de paiement actives: " . $stmt->fetchColumn();
?>
```

### 2. Test Orange Money (Sandbox)

```php
<?php
// test_orange_money.php
require_once 'config/db.php';
require_once 'admin/classes/PaymentFactory.php';

$testOrder = [
    'id' => 9999,
    'client_nom' => 'Test User',
    'client_telephone' => '+221771234567'
];

$provider = PaymentFactory::create('orange_money', $conn, ['test_mode' => true]);
$result = $provider->createPayment($testOrder, 1000);

echo "<pre>";
print_r($result);
echo "</pre>";

if ($result['success']) {
    echo "✅ Test réussi!<br>";
    echo "URL de paiement: " . $result['payment_url'];
} else {
    echo "❌ Test échoué: " . $result['error'];
}
?>
```

### 3. Test Wave (Sandbox)

Similaire au test Orange Money, remplacer `'orange_money'` par `'wave'`.

### 4. Test Paydunya (Sandbox)

Similaire aux tests précédents, remplacer par `'paydunya'`.

### 5. Test complet du flux

1. Aller sur `public/menu.php`
2. Ajouter un produit au panier
3. Aller sur `public/commander.php`
4. Sélectionner un mode de paiement
5. Compléter le paiement avec les credentials de test
6. Vérifier que vous êtes redirigé vers `payment_callback.php`
7. Vérifier que la commande est marquée comme payée

### 6. Test des webhooks

Utiliser un outil comme **ngrok** pour exposer votre localhost:

```bash
# Installer ngrok
# https://ngrok.com/download

# Exposer le port 80
ngrok http 80

# Copier l'URL HTTPS générée (ex: https://abc123.ngrok.io)
# L'utiliser comme URL de callback dans vos dashboards providers
```

---

## 🚀 Mise en production

### 1. Checklist avant production

- [ ] HTTPS activé sur le domaine
- [ ] Certificat SSL valide
- [ ] Credentials de production configurés dans .env
- [ ] `PAYMENT_TEST_MODE=false` dans .env
- [ ] Webhooks configurés avec URL de production
- [ ] Tests effectués en sandbox
- [ ] Logs activés
- [ ] Monitoring configuré

### 2. Activer le mode production

```env
# Dans .env
PAYMENT_TEST_MODE=false
```

### 3. Configurer les webhooks en production

Dans chaque dashboard provider, remplacer les URLs de test par:

**Orange Money:**
```
https://votre-domaine.com/public/payment_webhook.php?provider=orange_money
```

**Wave:**
```
https://votre-domaine.com/public/payment_webhook.php?provider=wave
```

**Paydunya:**
```
https://votre-domaine.com/public/payment_webhook.php?provider=paydunya
```

### 4. Vérifier les permissions

```bash
# Le dossier logs doit être accessible en écriture
chmod 755 logs/
touch logs/payments.log
chmod 644 logs/payments.log
```

### 5. Activer/Désactiver les providers

Dans la base de données:

```sql
-- Désactiver un provider
UPDATE payment_methods SET is_active = 0 WHERE provider = 'orange_money';

-- Activer un provider
UPDATE payment_methods SET is_active = 1 WHERE provider = 'wave';

-- Voir tous les providers
SELECT provider, name, is_active FROM payment_methods;
```

### 6. Monitoring

Surveiller les logs:

```bash
tail -f logs/payments.log
```

Vérifier les webhooks non traités:

```sql
SELECT * FROM payment_webhooks_log
WHERE processed = 0
ORDER BY created_at DESC
LIMIT 10;
```

---

## 🔧 Dépannage

### Problème: "Provider non supporté"

**Solution**: Vérifier que le provider est actif dans la BDD:

```sql
SELECT * FROM payment_methods WHERE provider = 'orange_money';
```

Si `is_active = 0`, l'activer:

```sql
UPDATE payment_methods SET is_active = 1 WHERE provider = 'orange_money';
```

### Problème: "Orange Money access token not available"

**Causes possibles**:
1. Credentials incorrects dans .env
2. Application Orange pas activée
3. Problème réseau

**Solution**:
```php
// Vérifier les credentials
echo $_ENV['ORANGE_MONEY_CLIENT_ID']; // Ne doit pas être vide
```

### Problème: Webhook non reçu

**Vérifier**:
1. URL du webhook correcte dans le dashboard du provider
2. HTTPS activé (requis par la plupart des providers)
3. Pare-feu n'empêche pas les requêtes entrantes

**Debug**:
```sql
-- Voir les webhooks reçus
SELECT * FROM payment_webhooks_log ORDER BY created_at DESC LIMIT 5;

-- Voir les erreurs
SELECT * FROM payment_webhooks_log WHERE processed = 0;
```

### Problème: Paiement bloqué en "pending"

**Solution**: Vérifier manuellement le statut:

```php
<?php
require_once 'config/db.php';
require_once 'admin/classes/PaymentFactory.php';

$transactionId = 'OM-123-1234567890'; // ID de transaction
$provider = PaymentFactory::create('orange_money', $conn);
$status = $provider->verifyPayment($transactionId);

print_r($status);
?>
```

### Problème: Erreur cURL

**Message**: "Could not resolve host" ou "SSL certificate problem"

**Solution**:
```php
// Dans PaymentGateway.php, temporairement (DEV SEULEMENT):
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // NE PAS FAIRE EN PRODUCTION
```

Mieux: Installer les certificats CA corrects sur le serveur.

### Logs

Consulter les logs pour debug:

```bash
# Logs de paiement
tail -f logs/payments.log

# Logs PHP
tail -f /var/log/apache2/error.log  # Linux
tail -f C:\wamp64\logs\php_error.log  # Windows
```

---

## 📞 Support

### Orange Money
- Support: https://developer.orange.com/
- Email: api.support@orange.com

### Wave
- Support: https://www.wave.com/help/
- Email: support@wave.com

### Paydunya
- Support: https://paydunya.com/contact/
- Email: developers@paydunya.com

---

## 📚 Ressources supplémentaires

### Documentation officielle

- **Orange Money API**: https://developer.orange.com/apis/orange-money-webpay/
- **Wave API**: https://developer.wave.com/docs
- **Paydunya API**: https://paydunya.com/developers/

### Exemples de code

Voir les fichiers dans `admin/classes/PaymentProviders/` pour des exemples d'implémentation.

### Tableaux de bord

- **Admin paiements**: `admin/paiements.php` (à créer)
- **Statistiques**: Vue `v_payment_dashboard`

---

**Créé le**: 2025-10-24
**Version**: 1.0
**Auteur**: Claude AI

**✅ Installation terminée? Passez aux tests en sandbox avant de passer en production!**
