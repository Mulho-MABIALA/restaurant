# 📋 Mapping des Colonnes de Paiement

## Table `commandes` - Colonnes Existantes vs Nouvelles

### Colonnes EXISTANTES (déjà dans votre base de données)

| Colonne Existante | Type | Valeurs | Usage |
|-------------------|------|---------|-------|
| `mode_paiement` | VARCHAR(50) | 'paiement_livraison', 'cash', 'wave', 'orange_money', 'paydunya' | **Stocke le provider de paiement** |
| `statut_paiement` | ENUM | 'Impayé', 'Payé' | **Statut simplifié du paiement** |

### Colonnes AJOUTÉES par le script SQL

| Nouvelle Colonne | Type | Valeur par défaut | Usage |
|------------------|------|-------------------|-------|
| `payment_id` | INT NULL | NULL | **Référence vers `paiements.id`** (clé étrangère) |
| `paid_at` | TIMESTAMP NULL | NULL | **Date exacte de confirmation du paiement** |

---

## 🔄 Flux de Données

### Quand un client commande avec paiement en ligne:

1. **Commande créée** → `commandes` table
   - `mode_paiement` = 'wave' (ou 'orange_money', 'paydunya')
   - `statut_paiement` = 'Impayé'
   - `payment_id` = NULL (pas encore de paiement)
   - `statut` = 'En attente'

2. **Paiement initié** → `paiements` table
   - Nouvelle entrée créée avec `statut` = 'pending'
   - `transaction_id` généré par le provider

3. **Paiement réussi** → Trigger `after_payment_success` s'exécute
   - `commandes.statut_paiement` → 'Payé' ✅
   - `commandes.payment_id` → ID du paiement
   - `commandes.paid_at` → Date de confirmation
   - `commandes.statut` → 'Confirmée' (si était 'En attente')

4. **Paiement remboursé** → Trigger s'exécute
   - `commandes.statut_paiement` → 'Impayé'
   - `commandes.statut` → 'Annulée'

---

## 📊 Détails du Paiement

Pour avoir les **détails complets** d'un paiement:

```sql
SELECT
    c.id as commande_id,
    c.mode_paiement,
    c.statut_paiement,
    c.paid_at,

    -- Détails dans la table paiements
    p.id as paiement_id,
    p.montant,
    p.provider,
    p.transaction_id,
    p.statut as statut_detaille,
    p.payment_confirmed_at,
    p.refund_amount

FROM commandes c
LEFT JOIN paiements p ON c.payment_id = p.id
WHERE c.id = 123;
```

### Statuts Détaillés (`paiements.statut`):
- `pending` - En attente de paiement
- `processing` - Paiement en cours
- `success` - ✅ Paiement réussi
- `failed` - ❌ Paiement échoué
- `refunded` - 💰 Remboursé
- `cancelled` - ⛔ Annulé par l'utilisateur

### Statuts Simplifiés (`commandes.statut_paiement`):
- `Impayé` - Pas encore payé ou remboursé
- `Payé` - ✅ Payé avec succès

---

## 🛠️ Exemples de Requêtes

### Toutes les commandes payées aujourd'hui
```sql
SELECT *
FROM commandes
WHERE statut_paiement = 'Payé'
  AND DATE(paid_at) = CURDATE();
```

### Commandes avec détails paiement Wave
```sql
SELECT
    c.id,
    c.client_nom,
    c.total,
    c.mode_paiement,
    c.statut_paiement,
    p.transaction_id,
    p.payment_confirmed_at
FROM commandes c
INNER JOIN paiements p ON c.payment_id = p.id
WHERE c.mode_paiement = 'wave'
  AND c.statut_paiement = 'Payé';
```

### Paiements échoués à traiter
```sql
SELECT
    c.id,
    c.client_nom,
    c.client_telephone,
    c.mode_paiement,
    p.statut,
    p.created_at
FROM commandes c
INNER JOIN paiements p ON c.payment_id = p.id
WHERE p.statut = 'failed'
  AND c.statut_paiement = 'Impayé';
```

---

## ⚠️ Important

**NE PAS** utiliser les anciennes colonnes `payment_method` et `payment_status` mentionnées dans certaines documentations - elles n'existent pas dans votre base de données.

**TOUJOURS** utiliser:
- ✅ `mode_paiement` (colonne existante)
- ✅ `statut_paiement` (colonne existante)
- ✅ `payment_id` (nouvelle colonne ajoutée par le script)
- ✅ `paid_at` (nouvelle colonne ajoutée par le script)

---

**Créé le**: 2025-10-24
**Compatible avec**: MySQL 5.7+, MariaDB 10.2+
