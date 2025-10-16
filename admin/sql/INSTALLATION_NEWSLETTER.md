# 📧 Installation du Système Newsletter

Guide complet pour installer et configurer le système de newsletter du restaurant.

---

## 📋 Prérequis

- ✅ WAMP/XAMPP installé et fonctionnel
- ✅ Base de données `restaurant` existante
- ✅ PHP 7.4+ avec extension PDO
- ✅ Accès phpMyAdmin ou MySQL en ligne de commande

---

## 🚀 Installation Rapide

### Étape 1 : Importer le schéma SQL

#### Option A : Via phpMyAdmin (Recommandé)

1. Ouvrir phpMyAdmin : `http://localhost/phpmyadmin`
2. Sélectionner la base `restaurant` dans la colonne de gauche
3. Cliquer sur l'onglet **"SQL"** en haut
4. Copier tout le contenu du fichier `newsletter_tables.sql`
5. Coller dans la zone de texte
6. Cliquer sur **"Exécuter"**
7. ✅ Vérifier le message de succès

#### Option B : Via ligne de commande

```bash
# Depuis le répertoire wamp64/www/restaurant/admin/sql/
mysql -u root -p restaurant < newsletter_tables.sql
```

### Étape 2 : Vérifier l'installation

Exécuter cette requête dans phpMyAdmin :

```sql
SHOW TABLES LIKE 'newsletter%';
```

Vous devez voir **10 tables** :
- ✅ `newsletter`
- ✅ `newsletter_campaigns`
- ✅ `newsletter_link_clicks`
- ✅ `newsletter_links`
- ✅ `newsletter_logs`
- ✅ `newsletter_queue`
- ✅ `newsletter_segments`
- ✅ `newsletter_subscriber_segments`
- ✅ `newsletter_templates`
- ✅ `newsletter_tracking`

### Étape 3 : Vérifier les données de démarrage

```sql
SELECT * FROM newsletter_segments;
SELECT * FROM newsletter_templates;
```

Vous devriez voir :
- 4 segments pré-créés (VIP, Nouveaux abonnés, Inactifs, Engagés)
- 1 template de base

---

## 🔧 Configuration

### Permissions Admin

Assurez-vous que votre compte admin a accès au module newsletter :

```sql
-- Vérifier la table des permissions
SELECT * FROM permissions WHERE page = 'admin_newsletter';

-- Si la permission n'existe pas, l'ajouter
INSERT INTO permissions (page, description)
VALUES ('admin_newsletter', 'Accès à la gestion de la newsletter');

-- Donner la permission à un admin (remplacer 1 par l'ID de l'admin)
INSERT INTO admin_permissions (admin_id, permission_id)
SELECT 1, id FROM permissions WHERE page = 'admin_newsletter';
```

### Configuration Email (Optionnel)

Pour l'envoi d'emails, configurez vos paramètres SMTP dans `config.php` :

```php
// Configuration email
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'votre-email@gmail.com');
define('SMTP_PASS', 'votre-mot-de-passe');
define('SMTP_FROM', 'noreply@votre-restaurant.com');
```

---

## 📊 Structure des Tables

### Table Principale : `newsletter`

Contient tous les abonnés avec leurs informations et statistiques d'engagement.

**Colonnes clés :**
- `email` : Email unique de l'abonné
- `statut` : actif / inactif
- `total_opens` : Nombre d'ouvertures d'emails
- `total_clicks` : Nombre de clics
- `last_activity` : Dernière interaction

### Table : `newsletter_campaigns`

Gère les campagnes d'emailing.

**Statuts possibles :**
- `draft` : Brouillon
- `scheduled` : Programmée
- `sending` : En cours d'envoi
- `sent` : Envoyée
- `failed` : Échec

### Table : `newsletter_segments`

Groupes d'abonnés pour ciblage.

**Segments par défaut :**
- 🌟 VIP
- 🆕 Nouveaux abonnés
- 😴 Inactifs
- 🔥 Engagés

---

## 🎯 Utilisation

### Accéder au Module

1. Connectez-vous à l'admin : `http://localhost/restaurant/admin/`
2. Dans le sidebar, cliquez sur **"Newsletter"**
3. Vous verrez 4 sous-menus :
   - 📋 **Liste des abonnés** (`admin_newsletter.php`)
   - ✉️ **Composer** (`admin_newsletter_compose.php`)
   - 📊 **Campagnes** (`admin_newsletter_campaigns.php`)
   - 📈 **Analytics** (`admin_newsletter_analytics.php`)

### Ajouter des Abonnés

#### Manuellement via l'interface

1. Aller dans "Liste des abonnés"
2. Cliquer sur "Ajouter un abonné"
3. Remplir le formulaire

#### Via import CSV

1. Préparer un fichier CSV :
   ```csv
   email,first_name,last_name,phone
   client1@example.com,Jean,Dupont,0612345678
   client2@example.com,Marie,Martin,0698765432
   ```

2. Aller dans "Importer"
3. Uploader le fichier CSV

#### Via SQL (pour test)

```sql
INSERT INTO newsletter (email, first_name, last_name, statut, source)
VALUES
('test1@example.com', 'Test', 'User1', 'actif', 'manual'),
('test2@example.com', 'Test', 'User2', 'actif', 'manual'),
('test3@example.com', 'Test', 'User3', 'actif', 'manual');
```

### Créer une Campagne

1. Aller dans **"Composer"**
2. Remplir les informations :
   - Nom de la campagne
   - Sujet de l'email
   - Choisir un template (optionnel)
   - Éditer le contenu
3. Sélectionner les destinataires :
   - Tous les abonnés actifs
   - Un ou plusieurs segments
4. Choisir la planification :
   - 💾 Sauvegarder en brouillon
   - 🚀 Envoyer maintenant
   - ⏰ Programmer pour plus tard
5. Cliquer sur **"Créer la campagne"**

### Variables Disponibles

Dans vos emails, vous pouvez utiliser :
- `{{first_name}}` : Prénom de l'abonné
- `{{last_name}}` : Nom de l'abonné
- `{{email}}` : Email de l'abonné
- `{{unsubscribe_link}}` : Lien de désabonnement

---

## 🐛 Dépannage

### Problème : Tables non créées

**Solution :**
```sql
-- Supprimer les tables existantes si nécessaire
DROP TABLE IF EXISTS newsletter_link_clicks;
DROP TABLE IF EXISTS newsletter_links;
DROP TABLE IF EXISTS newsletter_queue;
DROP TABLE IF EXISTS newsletter_tracking;
DROP TABLE IF EXISTS newsletter_logs;
DROP TABLE IF EXISTS newsletter_campaigns;
DROP TABLE IF EXISTS newsletter_subscriber_segments;
DROP TABLE IF EXISTS newsletter_segments;
DROP TABLE IF EXISTS newsletter_templates;
DROP TABLE IF EXISTS newsletter;

-- Puis réimporter le script
```

### Problème : Erreur "could not find driver"

**Solution :**
1. Ouvrir `php.ini`
2. Décommenter : `extension=pdo_mysql`
3. Redémarrer WAMP/XAMPP

### Problème : Permission refusée

**Solution :**
```sql
-- Vérifier les permissions
SELECT ap.*, p.page
FROM admin_permissions ap
JOIN permissions p ON ap.permission_id = p.id
WHERE ap.admin_id = 1;  -- Remplacer 1 par votre admin_id

-- Ajouter la permission si manquante (voir section Configuration)
```

### Problème : Erreur 404 sur les pages

**Vérifier que tous les fichiers existent :**
- ✅ `admin/admin_newsletter.php`
- ✅ `admin/admin_newsletter_compose.php`
- ✅ `admin/admin_newsletter_campaigns.php`
- ✅ `admin/admin_newsletter_analytics.php`
- ✅ `admin/admin_newsletter_delete.php`
- ✅ `admin/admin_newsletter_toggle.php`
- ✅ `admin/sidebar.php`

---

## 📈 Statistiques et Analytics

### Voir les statistiques globales

```sql
SELECT * FROM newsletter_stats_overview;
```

### Top 10 des abonnés les plus engagés

```sql
SELECT email, first_name, last_name, total_opens, total_clicks
FROM newsletter
WHERE statut = 'actif'
ORDER BY (total_opens + total_clicks) DESC
LIMIT 10;
```

### Taux d'ouverture par campagne

```sql
SELECT
    nc.name,
    nc.total_recipients,
    nc.total_sent,
    nc.total_opens,
    ROUND((nc.total_opens / nc.total_sent) * 100, 2) as open_rate
FROM newsletter_campaigns nc
WHERE nc.status = 'sent'
ORDER BY nc.created_at DESC;
```

---

## 🔐 Sécurité

### Bonnes Pratiques

1. ✅ Ne jamais exposer les tokens de désabonnement
2. ✅ Valider tous les emails avant insertion
3. ✅ Limiter le nombre d'envois par heure
4. ✅ Logger toutes les actions administratives
5. ✅ Utiliser HTTPS pour les liens de désabonnement
6. ✅ Respecter le RGPD

### Protection Anti-Spam

```sql
-- Limite : Max 1000 emails par campagne
ALTER TABLE newsletter_campaigns
ADD CHECK (total_recipients <= 1000);

-- Créer un index pour les recherches rapides
CREATE INDEX idx_email_search ON newsletter(email);
```

---

## 🎨 Personnalisation

### Ajouter un nouveau segment

```sql
INSERT INTO newsletter_segments (name, description, color)
VALUES ('Clients fidèles', 'Clients avec plus de 5 commandes', '#10b981');
```

### Créer un template personnalisé

1. Aller dans **"Templates"**
2. Cliquer sur **"Nouveau template"**
3. Donner un nom
4. Éditer le HTML
5. Sauvegarder

---

## 📞 Support

Si vous rencontrez des problèmes :

1. Vérifier les logs PHP : `wamp64/logs/php_error.log`
2. Vérifier les logs MySQL
3. Consulter la documentation du projet

---

## ✅ Checklist Post-Installation

- [ ] Tables créées avec succès
- [ ] Segments par défaut présents
- [ ] Template de base présent
- [ ] Permissions admin configurées
- [ ] Configuration SMTP (si nécessaire)
- [ ] Test d'ajout d'abonné
- [ ] Test de création de campagne
- [ ] Test d'envoi de test
- [ ] Sidebar visible et fonctionnel
- [ ] Toutes les pages accessibles

---

## 🚀 Prochaines Étapes

1. Personnaliser le template de base
2. Importer vos premiers abonnés
3. Créer vos premiers segments
4. Envoyer votre première campagne de test
5. Analyser les statistiques

---

**Bonne utilisation du système de newsletter ! 📧✨**