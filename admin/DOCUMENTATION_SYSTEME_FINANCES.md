# 📊 DOCUMENTATION COMPLÈTE - SYSTÈME DE GESTION FINANCIÈRE

**Restaurant Mulho - Système de Finances**
**Dernière mise à jour** : 17 Octobre 2025

---

## 📑 TABLE DES MATIÈRES

1. [Vue d'ensemble du système](#vue-densemble)
2. [Architecture technique](#architecture)
3. [Modules du système](#modules)
4. [Flux de données](#flux-de-données)
5. [Base de données](#base-de-données)
6. [Permissions et sécurité](#permissions)
7. [Guide d'utilisation](#guide-dutilisation)
8. [API et intégrations](#api)
9. [Maintenance et troubleshooting](#maintenance)

---

## 🎯 VUE D'ENSEMBLE

### Objectif du système
Le système de gestion financière permet de **suivre, analyser et optimiser** toutes les opérations financières du restaurant :
- Suivi du chiffre d'affaires en temps réel
- Gestion de la trésorerie (espèces, cartes, mobile money)
- Facturation clients automatique
- Gestion des fournisseurs et factures fournisseurs
- Analyse des marges par plat
- Système d'alertes financières
- Rapports et statistiques avancés

### Accès au système
- **URL** : `admin/finances_dashboard.php`
- **Permission requise** : `finances` (table `admin_permissions`)
- **Rôles autorisés** : Admin, Gestionnaire financier

---

## 🏗️ ARCHITECTURE TECHNIQUE

### Structure des fichiers

```
admin/
├── finances_dashboard.php      # Dashboard principal (KPIs, graphiques)
├── facturation.php             # Gestion factures clients
├── rapports.php                # Rapports et analyses
├── tresorerie.php              # Suivi trésorerie par mode de paiement
├── marges.php                  # Analyse marges par plat
├── alertes.php                 # Alertes financières
├── fournisseurs.php            # Gestion fournisseurs
├── factures_fournisseur.php    # Factures fournisseurs
├── imprimer_facture_fournisseur.php  # Impression PDF factures
└── permissions.php             # Système de permissions

api/
├── finances_api.php            # API pour actions finances
└── export_rapport.php          # Export PDF rapports
```

### Technologies utilisées

| Technologie | Usage |
|-------------|-------|
| **PHP 7.4+** | Backend, logique métier |
| **MySQL/PDO** | Base de données |
| **TailwindCSS** | Framework CSS |
| **Chart.js** | Graphiques et visualisations |
| **Font Awesome** | Icônes |
| **JavaScript ES6** | Interactions frontend |

---

## 📦 MODULES DU SYSTÈME

### 1. FINANCES DASHBOARD (`finances_dashboard.php`)

**🎯 Rôle** : Vue d'ensemble centralisée des performances financières

**Fonctionnalités** :
- ✅ KPIs du jour (CA, panier moyen, nb commandes, objectif)
- ✅ Graphique évolution 7 jours
- ✅ Top 5 plats les plus vendus
- ✅ Répartition par mode de paiement
- ✅ Filtre par date

**Données affichées** :
```php
// CA du jour
SELECT COUNT(*) as nb_commandes,
       COALESCE(SUM(total), 0) as ca_total,
       COALESCE(AVG(total), 0) as panier_moyen
FROM commandes
WHERE DATE(date_commande) = ?
AND statut_paiement = 'Payé'
```

**Calculs** :
- **Objectif jour** : 500 000 FCFA (configurable ligne 68)
- **Progression** : `(CA réalisé / Objectif) * 100`
- **Panier moyen** : `CA total / Nombre de commandes`

---

### 2. FACTURATION (`facturation.php`)

**🎯 Rôle** : Gestion des factures clients (basé sur les commandes payées)

**Fonctionnalités** :
- ✅ Liste des factures du mois
- ✅ Statistiques (total factures, payées, en attente, montant total)
- ✅ Filtrage par date
- ✅ Visualisation détails facture
- ✅ Impression facture PDF

**Flux de facturation** :
```
Commande payée → Facture générée automatiquement
↓
Statut : Payé / En attente
↓
Actions possibles :
- Voir détails
- Imprimer PDF
- Filtrer par période
```

**Données source** :
```php
// Les factures proviennent de la table commandes
SELECT id,
       numero_commande as numero_facture,
       date_commande as date_facture,
       nom_client,
       total as montant_ttc,
       statut_paiement,
       mode_paiement
FROM commandes
WHERE statut_paiement = 'Payé'
```

---

### 3. TRÉSORERIE (`tresorerie.php`)

**🎯 Rôle** : Suivi des encaissements par mode de paiement

**Modes de paiement trackés** :
- 💵 **Espèces** : Paiements cash
- 💳 **Cartes bancaires** : Visa, Mastercard, etc.
- 📱 **Mobile Money** : Wave, Orange Money, Free Money

**KPIs affichés** :
```
┌─────────────────────┐
│  Espèces du jour    │
│  125,000 FCFA       │
└─────────────────────┘

┌─────────────────────┐
│  Cartes du jour     │
│  85,000 FCFA        │
└─────────────────────┘

┌─────────────────────┐
│  Mobile Money       │
│  40,000 FCFA        │
└─────────────────────┘

┌─────────────────────┐
│  TOTAL VENTES       │
│  250,000 FCFA       │
└─────────────────────┘
```

**Requêtes clés** :
```php
SELECT
    COALESCE(SUM(CASE WHEN mode_paiement = 'Espèces' THEN total ELSE 0 END), 0) as total_especes,
    COALESCE(SUM(CASE WHEN mode_paiement = 'Carte bancaire' THEN total ELSE 0 END), 0) as total_cartes,
    COALESCE(SUM(CASE WHEN mode_paiement = 'Mobile Money' THEN total ELSE 0 END), 0) as total_mobile
FROM commandes
WHERE DATE(date_commande) = ?
AND statut_paiement = 'Payé'
```

---

### 4. RAPPORTS (`rapports.php`)

**🎯 Rôle** : Analyses financières sur période personnalisée

**Rapports disponibles** :
- 📈 **Évolution CA par jour** : Graphique ligne
- 🥧 **Répartition par mode de paiement** : Camembert
- 🔥 **Top 10 plats de la période** : Tableau avec quantités et CA
- 📊 **KPIs globaux** : CA total, nb commandes, panier moyen, CA quotidien moyen

**Filtres** :
- Date début / Date fin
- Export PDF du rapport

**Calculs** :
```php
// CA quotidien moyen
$nb_jours = count($ca_par_jour);
$ca_quotidien_moyen = $stats['ca_total'] / $nb_jours;

// Top plats
SELECT cd.nom_plat,
       SUM(cd.quantite) as quantite_vendue,
       SUM(cd.prix * cd.quantite) as ca_plat
FROM commande_details cd
JOIN commandes c ON cd.commande_id = c.id
WHERE DATE(c.date_commande) BETWEEN ? AND ?
GROUP BY cd.nom_plat
ORDER BY ca_plat DESC
LIMIT 10
```

---

### 5. ANALYSE DES MARGES (`marges.php`)

**🎯 Rôle** : Calculer et analyser la rentabilité de chaque plat

**Méthode de calcul** :
```
Prix de vente du plat : 15,000 FCFA
Coût de revient estimé : Prix de vente × 32% = 4,800 FCFA
Marge en FCFA : 15,000 - 4,800 = 10,200 FCFA
Marge en % : (10,200 / 15,000) × 100 = 68%
```

**Note importante** : Le système utilise une estimation à **32% du prix de vente** pour le coût de revient (standard restauration : 30-35%).

**Catégories de marges** :
- 🔴 **Faible** : < 30%
- 🟡 **Moyenne** : 30-50%
- 🟢 **Bonne** : ≥ 50%

**Indicateurs** :
- Marge moyenne sur tous les plats
- Nombre de plats à marge faible
- Nombre de plats à bonne marge
- Top 5 meilleures marges
- Flop 5 (marges à améliorer)

**Graphique** : Répartition en camembert (Faibles / Moyennes / Bonnes)

---

### 6. GESTION FOURNISSEURS (`fournisseurs.php`)

**🎯 Rôle** : Centraliser les informations fournisseurs

**Informations stockées** :
```
┌──────────────────────────┐
│ Informations générales   │
├──────────────────────────┤
│ - Nom fournisseur        │
│ - Contact (nom)          │
│ - Téléphone              │
│ - Email                  │
│ - Adresse complète       │
│ - Ville, Code postal     │
│ - Pays                   │
└──────────────────────────┘

┌──────────────────────────┐
│ Informations légales     │
├──────────────────────────┤
│ - SIRET                  │
│ - Numéro TVA             │
└──────────────────────────┘

┌──────────────────────────┐
│ Conditions commerciales  │
├──────────────────────────┤
│ - Délai paiement (jours) │
│ - Mode de paiement       │
│ - Notes                  │
└──────────────────────────┘
```

**Actions** :
- ➕ Ajouter nouveau fournisseur
- 👁️ Voir détails
- 🧾 Accès factures fournisseur
- ✏️ Modifier (via API)
- 🗑️ Désactiver (statut actif/inactif)

**Table BDD** : `fournisseurs`

---

### 7. FACTURES FOURNISSEURS (`factures_fournisseur.php`)

**🎯 Rôle** : Gérer les factures reçues des fournisseurs

**Cycle de vie d'une facture** :
```
1. Création facture
   ↓
2. Statut : En attente
   ↓
3. Ajout de lignes (articles)
   ↓
4. Validation
   ↓
5. Paiement (partiel ou total)
   ↓
6. Statut : Payée
```

**Statuts possibles** :
- 📝 **Brouillon** : En cours de saisie
- ⏳ **En attente** : Validée, en attente de paiement
- 💰 **Payée partiellement** : Acompte versé
- ✅ **Payée** : Soldée complètement

**Informations par facture** :
- Numéro facture
- Date facture / Date échéance
- Fournisseur
- Lignes de facture (articles, quantité, prix unitaire, total)
- Montant HT, TVA, TTC
- Montant payé, montant restant
- Historique paiements

**Actions** :
- Créer facture
- Ajouter lignes
- Enregistrer paiement
- Imprimer PDF
- Supprimer

---

### 8. ALERTES FINANCIÈRES (`alertes.php`)

**🎯 Rôle** : Système d'alertes automatiques pour surveiller les problèmes

**Types d'alertes** :
- 📦 **rupture_stock** : Stock produit en rupture
- ⏰ **echeance_facture** : Facture fournisseur arrive à échéance
- 💰 **ecart_caisse** : Différence entre caisse théorique et réelle
- 📉 **baisse_marge** : Marge d'un plat en baisse
- 🎯 **objectif_rate** : Objectif journalier non atteint

**Priorités** :
- 🔴 **Critical** : Action immédiate requise
- 🟠 **Warning** : À surveiller
- 🔵 **Info** : Informationnel

**États** :
- 🆕 **Active** : Non lue, nouvelle alerte
- 👁️ **Lue** : Prise en connaissance
- ✅ **Résolue** : Traitée et résolue

**Workflow** :
```
Alerte créée (active)
   ↓
Marquer comme lue
   ↓
Traiter le problème
   ↓
Marquer comme résolue
```

**Table BDD** : `alertes_financieres`

---

## 🔄 FLUX DE DONNÉES

### 1. Flux vente → CA

```
┌──────────────────┐
│   CLIENT         │
│   Passe          │
│   commande       │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│  Table:          │
│  commandes       │
│  - total         │
│  - mode_paiement │
│  - statut        │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│  Paiement        │
│  effectué        │
│  statut = 'Payé' │
└────────┬─────────┘
         │
         ├──────────────────┐
         ▼                  ▼
┌──────────────┐   ┌────────────────┐
│ Dashboard    │   │ Trésorerie     │
│ (CA du jour) │   │ (par mode)     │
└──────────────┘   └────────────────┘
         │
         ▼
┌──────────────────┐
│  Facturation     │
│  (facture créée) │
└──────────────────┘
```

### 2. Flux fournisseur → Paiement

```
┌──────────────────┐
│  FOURNISSEUR     │
│  Envoie facture  │
└────────┬─────────┘
         │
         ▼
┌──────────────────────────┐
│  Table:                  │
│  factures_fournisseur    │
│  - montant_total         │
│  - montant_paye          │
│  - statut                │
└────────┬─────────────────┘
         │
         ▼
┌──────────────────┐
│  Paiement        │
│  enregistré      │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│  Statut mis      │
│  à jour          │
│  (Payée si soldé)│
└──────────────────┘
```

### 3. Flux marge

```
┌──────────────┐
│  Plat créé   │
│  Prix défini │
└──────┬───────┘
       │
       ▼
┌─────────────────────┐
│  Calcul marge       │
│  Coût = Prix × 32%  │
│  Marge = Prix-Coût  │
│  Marge% calculé     │
└──────┬──────────────┘
       │
       ▼
┌──────────────────┐
│  Analyse marges  │
│  Classification  │
│  (Faible/Moy/Bon)│
└──────────────────┘
```

---

## 🗄️ BASE DE DONNÉES

### Tables principales

#### `commandes`
```sql
CREATE TABLE commandes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero_commande VARCHAR(50) UNIQUE,
    date_commande DATETIME,
    nom_client VARCHAR(255),
    total DECIMAL(10,2),
    mode_paiement ENUM('Espèces', 'Carte bancaire', 'Mobile Money', 'Virement'),
    statut_paiement ENUM('Payé', 'En attente', 'Annulé'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### `commande_details`
```sql
CREATE TABLE commande_details (
    id INT PRIMARY KEY AUTO_INCREMENT,
    commande_id INT,
    nom_plat VARCHAR(255),
    quantite INT,
    prix DECIMAL(10,2),
    FOREIGN KEY (commande_id) REFERENCES commandes(id)
);
```

#### `fournisseurs`
```sql
CREATE TABLE fournisseurs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(255) NOT NULL,
    contact_nom VARCHAR(255),
    telephone VARCHAR(20),
    email VARCHAR(255),
    adresse TEXT,
    ville VARCHAR(100),
    code_postal VARCHAR(10),
    pays VARCHAR(100) DEFAULT 'France',
    siret VARCHAR(14),
    tva_numero VARCHAR(20),
    conditions_paiement INT DEFAULT 30,
    mode_paiement VARCHAR(50),
    notes TEXT,
    actif BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### `factures_fournisseur`
```sql
CREATE TABLE factures_fournisseur (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fournisseur_id INT,
    numero_facture VARCHAR(100),
    date_facture DATE,
    date_echeance DATE,
    montant_ht DECIMAL(10,2),
    taux_tva DECIMAL(5,2),
    montant_tva DECIMAL(10,2),
    montant_ttc DECIMAL(10,2),
    montant_paye DECIMAL(10,2) DEFAULT 0,
    montant_restant DECIMAL(10,2),
    statut ENUM('brouillon', 'en_attente', 'payee_partiellement', 'payee'),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fournisseur_id) REFERENCES fournisseurs(id)
);
```

#### `alertes_financieres`
```sql
CREATE TABLE alertes_financieres (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type_alerte VARCHAR(50),
    priorite ENUM('critical', 'warning', 'info'),
    titre VARCHAR(255),
    message TEXT,
    statut ENUM('active', 'lue', 'resolue') DEFAULT 'active',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_traitement TIMESTAMP NULL
);
```

---

## 🔐 PERMISSIONS ET SÉCURITÉ

### Système de permissions

**Fichier** : `permissions.php`

**Fonction principale** :
```php
function requireAccess($conn, $admin_id, $permission) {
    // Vérifie si l'admin a la permission requise
    // Redirige vers denied.php si refusé
}
```

**Permissions finances** :
- `finances` : Accès général au module finances
- `dashboard_finances` : Dashboard
- `facturation` : Factures clients
- `rapports` : Rapports
- `tresorerie` : Trésorerie
- `fournisseurs` : Gestion fournisseurs
- `factures_fournisseur` : Factures fournisseurs
- `marges` : Analyse marges
- `alertes` : Alertes financières

### Sécurité

**Mesures en place** :
- ✅ Vérification session admin sur toutes les pages
- ✅ Vérification permissions via `requireAccess()`
- ✅ Requêtes SQL préparées (PDO)
- ✅ Échappement HTML (`htmlspecialchars()`)
- ✅ Validation côté serveur
- ✅ Protection CSRF (à implémenter si pas déjà fait)

---

## 📖 GUIDE D'UTILISATION

### Pour le gestionnaire financier

#### 1. Consulter le CA du jour
1. Accéder à `finances_dashboard.php`
2. Visualiser les KPIs (CA, panier moyen, objectif)
3. Changer la date si besoin avec le sélecteur

#### 2. Vérifier la trésorerie
1. Aller sur `tresorerie.php`
2. Voir la répartition Espèces / Cartes / Mobile Money
3. Comparer avec l'évolution sur 7 jours

#### 3. Générer un rapport mensuel
1. Ouvrir `rapports.php`
2. Sélectionner date début (01/10/2025)
3. Sélectionner date fin (31/10/2025)
4. Cliquer "Filtrer"
5. Analyser les graphiques et le top 10 plats
6. Cliquer "Export PDF" pour sauvegarder

#### 4. Analyser les marges
1. Accéder à `marges.php`
2. Consulter la marge moyenne
3. Identifier les plats à marge faible (< 30%)
4. Décider d'ajuster les prix ou négocier avec fournisseurs

#### 5. Gérer une facture fournisseur
1. Aller sur `fournisseurs.php`
2. Vérifier que le fournisseur existe
3. Cliquer sur l'icône facture
4. Créer une nouvelle facture
5. Ajouter les lignes (articles)
6. Enregistrer les paiements au fur et à mesure

#### 6. Traiter les alertes
1. Ouvrir `alertes.php`
2. Voir les alertes "Non lues"
3. Cliquer "Marquer lue" pour chaque alerte
4. Résoudre le problème
5. Cliquer "Traiter" pour marquer comme résolue

---

## 🔌 API ET INTÉGRATIONS

### API Finances (`api/finances_api.php`)

**Endpoints disponibles** :

```php
// Marquer alerte comme lue
GET /api/finances_api.php?action=alerte_marquer_lue&id=123

// Marquer alerte comme traitée
GET /api/finances_api.php?action=alerte_marquer_traitee&id=123

// Calculer les marges
GET /api/finances_api.php?action=calculer_marges

// Ajouter fournisseur
POST /api/finances_api.php?action=fournisseur_ajouter
Body: JSON avec données fournisseur
```

### Export PDF (`api/export_rapport.php`)

```php
// Exporter rapport PDF
GET /api/export_rapport.php?date_debut=2025-10-01&date_fin=2025-10-31
```

### Génération facture client

```php
// Générer facture PDF
GET /api/generer_facture.php?id=456
```

---

## 🛠️ MAINTENANCE ET TROUBLESHOOTING

### Problèmes courants

#### 1. CA à 0 alors qu'il y a des commandes
**Cause** : Les commandes ne sont pas marquées comme "Payé"
**Solution** :
```sql
UPDATE commandes
SET statut_paiement = 'Payé'
WHERE id = 123;
```

#### 2. Marges incorrectes
**Cause** : Le coefficient de coût (32%) ne correspond pas à la réalité
**Solution** : Modifier ligne 35 de `marges.php`
```php
// Changer de 0.32 à 0.30 par exemple
$cout_revient = $prix_vente * 0.30;
```

#### 3. Graphiques ne s'affichent pas
**Cause** : Chart.js non chargé ou erreur JavaScript
**Solution** :
1. Vérifier console navigateur (F12)
2. Vérifier que `<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>` est chargé
3. Vérifier les données PHP (pas de `null` ou `undefined`)

#### 4. Permission refusée
**Cause** : L'administrateur n'a pas la permission `finances`
**Solution** :
```sql
INSERT INTO admin_permissions (admin_id, permission)
VALUES (1, 'finances');
```

### Logs et débogage

**Activer les logs** :
```php
// En haut de chaque fichier finances
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

**Vérifier requêtes SQL** :
```php
try {
    $stmt = $conn->prepare("SELECT...");
    $stmt->execute();
} catch (PDOException $e) {
    error_log("Erreur SQL : " . $e->getMessage());
    echo "Erreur : " . $e->getMessage(); // En dev seulement
}
```

---

## 📈 ÉVOLUTIONS FUTURES

### Fonctionnalités à ajouter

1. **Budget prévisionnel**
   - Définir objectifs mensuels
   - Comparer réel vs prévisionnel
   - Alertes écarts budgétaires

2. **Gestion des stocks**
   - Lier coûts réels des ingrédients
   - Calcul marge précis
   - Stock valorisé

3. **Analyse ABC**
   - Classifier plats selon rentabilité
   - Focus sur top 20% produits

4. **Dashboard temps réel**
   - WebSocket pour maj auto
   - Notifications push alertes

5. **Export comptable**
   - Format FEC
   - Intégration logiciels compta

---

## 📞 SUPPORT

**Pour toute question technique** :
- Consulter ce fichier de documentation
- Vérifier les logs d'erreurs
- Contacter l'équipe technique

**Version du système** : 1.0
**Date de création** : Octobre 2025

---

*Ce document est maintenu et mis à jour régulièrement. Dernière mise à jour : 17/10/2025*

---

## 🆕 NOUVELLE PAGE : TRÉSORERIE GLOBALE

### 📍 `tresorerie_globale.php` - Vue d'ensemble Entrées/Sorties/Solde

**Ajoutée le** : 17 Octobre 2025

**🎯 Objectif** : Centraliser la vision des flux financiers (entrées et sorties) avec calcul automatique du solde

**Fonctionnalités** :
- ✅ **KPI Solde de trésorerie** : Entrées - Sorties (en vert si positif, rouge si négatif)
- ✅ **Total Entrées** : Somme de toutes les ventes payées
- ✅ **Total Sorties** : Somme de tous les paiements fournisseurs
- ✅ **Taux de marge** : (Solde / Entrées) × 100
- ✅ **Détail des entrées par mode de paiement** : Espèces, Cartes, Mobile Money avec barres de progression
- ✅ **Top 5 fournisseurs** : Les plus payés sur la période
- ✅ **Factures à payer** : Montant total en attente + nombre de factures
- ✅ **Graphique évolution quotidienne** : 3 courbes (Entrées, Sorties, Solde cumulé)
- ✅ **Camembert Entrées/Sorties** : Visualisation de la répartition
- ✅ **Trésorerie prévisionnelle** : Solde - Factures en attente = Ce qu'il restera après paiement

**Données sources** :
```php
// Entrées : Table commandes
SELECT SUM(total) as total_entrees
FROM commandes
WHERE statut_paiement = 'Payé'
AND DATE(date_commande) BETWEEN ? AND ?

// Sorties : Table factures_fournisseur
SELECT SUM(montant_paye) as total_sorties
FROM factures_fournisseur
WHERE DATE(date_facture) BETWEEN ? AND ?

// Solde
$solde = $total_entrees - $total_sorties
```

**Formules de calcul** :
```
Solde = Entrées - Sorties
Taux de marge = (Solde / Entrées) × 100
Trésorerie prévisionnelle = Solde - Total factures en attente
```

**Accès** :
- Menu Finances → Trésorerie Globale
- Permission requise : `tresorerie`

---

