# 🚀 Comment Exécuter le Script SQL

## ✅ Le script a été ajusté pour votre base de données

Le script `create_payment_tables.sql` est maintenant **100% compatible** avec votre table `commandes` existante.

**Modifications apportées:**
- ✅ N'ajoute PAS de colonnes en double (`payment_method`, `payment_status`)
- ✅ Utilise vos colonnes existantes (`mode_paiement`, `statut_paiement`)
- ✅ Ajoute uniquement `payment_id` et `paid_at`
- ✅ Trigger mis à jour pour utiliser vos colonnes

---

## 📋 Méthode 1: Via phpMyAdmin (Recommandé)

### Étape 1: Ouvrir phpMyAdmin
```
http://localhost/phpmyadmin
```

### Étape 2: Sélectionner la base de données
- Cliquer sur **`restaurant`** dans le panneau de gauche

### Étape 3: Aller dans l'onglet SQL
- Cliquer sur l'onglet **"SQL"** en haut

### Étape 4: Copier-coller le script
1. Ouvrir le fichier **`admin/sql/create_payment_tables_v2.sql`** ⚠️ (VERSION 2 - Ultra Compatible)
2. **Tout sélectionner** (Ctrl+A)
3. **Copier** (Ctrl+C)
4. **Coller** dans la zone de texte de phpMyAdmin

> ⚠️ **IMPORTANT:** Utilisez `create_payment_tables_v2.sql` (VERSION LA PLUS RÉCENTE)
>
> Cette version résout:
> - ✅ Erreur "Failed to open the referenced table 'commandes'"
> - ✅ Erreur "IF NOT EXISTS" dans ADD COLUMN
> - ✅ Compatible MySQL 5.7+, MariaDB 10.2+

### Étape 5: Exécuter
- Cliquer sur le bouton **"Exécuter"** en bas à droite
- ⏳ Attendre quelques secondes...

### Étape 6: Vérifier le résultat
Vous devriez voir des messages de succès:
```
✅ Table paiements créée
✅ Table payment_webhooks_log créée
✅ Table payment_methods créée
✅ Table payment_statistics créée
✅ 4 lignes insérées dans payment_methods
✅ Vue v_payment_dashboard créée
✅ Trigger after_payment_success créé
✅ Procédure sp_get_daily_payment_stats créée
```

---

## 📋 Méthode 2: Via Ligne de Commande MySQL

### Windows (WAMP)

```bash
# Ouvrir CMD dans le dossier du projet
cd C:\wamp64\www\restaurant

# Se connecter à MySQL et exécuter le script (VERSION 2)
C:\wamp64\bin\mysql\mysql8.0.x\bin\mysql.exe -u root -p restaurant < admin/sql/create_payment_tables_v2.sql
```

### Linux / Mac

```bash
# Se placer dans le dossier du projet
cd /path/to/restaurant

# Exécuter le script (VERSION 2)
mysql -u root -p restaurant < admin/sql/create_payment_tables_v2.sql
```

> ⚠️ **Note:** Utilisez toujours `create_payment_tables_v2.sql` (version la plus récente et compatible)

Entrer votre mot de passe MySQL quand demandé.

---

## 🔍 Vérifier que tout est créé

### Via phpMyAdmin
1. Rafraîchir la liste des tables (F5)
2. Vous devriez voir les nouvelles tables:
   - ✅ `paiements`
   - ✅ `payment_webhooks_log`
   - ✅ `payment_methods`
   - ✅ `payment_statistics`

### Via SQL
Exécuter cette requête dans phpMyAdmin:

```sql
-- Vérifier les tables
SHOW TABLES LIKE 'paiement%';
SHOW TABLES LIKE 'payment%';

-- Vérifier les nouvelles colonnes dans commandes
DESCRIBE commandes;

-- Vérifier les méthodes de paiement
SELECT provider, name, is_active FROM payment_methods;
```

**Résultat attendu:**
```
+------------------+----------------------------------+-----------+
| provider         | name                             | is_active |
+------------------+----------------------------------+-----------+
| orange_money     | Orange Money                     |         1 |
| wave             | Wave                             |         1 |
| paydunya         | Carte bancaire                   |         1 |
| cash             | Paiement sur place               |         1 |
+------------------+----------------------------------+-----------+
```

---

## ⚠️ En cas d'erreur

### Erreur: "Table 'paiements' already exists"
**Cause:** Le script a déjà été exécuté

**Solution:** Aucun problème! Le script utilise `CREATE TABLE IF NOT EXISTS`, donc il ne recrée pas les tables existantes.

### Erreur: "Duplicate column name 'payment_id'"
**Cause:** La colonne existe déjà

**Solution:**
```sql
-- Vérifier si elle existe
SELECT COLUMN_NAME
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'restaurant'
  AND TABLE_NAME = 'commandes'
  AND COLUMN_NAME IN ('payment_id', 'paid_at');
```

Si les colonnes existent déjà, c'est parfait - vous pouvez ignorer cette partie du script.

### Erreur: "Trigger 'after_payment_success' already exists"
**Cause:** Le trigger existe déjà

**Solution:** Supprimer l'ancien trigger puis réexécuter:
```sql
DROP TRIGGER IF EXISTS after_payment_success;
```

Puis réexécuter le script complet.

---

## 📊 Tester que tout fonctionne

### Test 1: Vérifier les méthodes de paiement actives
```sql
SELECT * FROM payment_methods WHERE is_active = 1;
```

### Test 2: Tester la vue dashboard
```sql
SELECT * FROM v_payment_dashboard;
```
(Résultat vide normal si aucun paiement encore)

### Test 3: Tester la procédure stockée
```sql
CALL sp_get_daily_payment_stats(CURDATE());
```
(Résultat vide normal si aucun paiement aujourd'hui)

### Test 4: Vérifier les nouvelles colonnes
```sql
SELECT id, mode_paiement, statut_paiement, payment_id, paid_at
FROM commandes
LIMIT 5;
```

**Résultat attendu:**
```
+----+---------------------+------------------+------------+---------+
| id | mode_paiement       | statut_paiement  | payment_id | paid_at |
+----+---------------------+------------------+------------+---------+
|  1 | paiement_livraison  | Impayé           | NULL       | NULL    |
|  2 | paiement_livraison  | Payé             | NULL       | NULL    |
+----+---------------------+------------------+------------+---------+
```

---

## ✅ Checklist Après Installation

- [ ] Tables créées (`paiements`, `payment_webhooks_log`, `payment_methods`, `payment_statistics`)
- [ ] 4 méthodes de paiement insérées dans `payment_methods`
- [ ] Colonnes `payment_id` et `paid_at` ajoutées à `commandes`
- [ ] Vues créées (`v_payment_dashboard`, `v_recent_payments`)
- [ ] Trigger `after_payment_success` créé
- [ ] Procédure `sp_get_daily_payment_stats` créée
- [ ] Tests SQL exécutés avec succès

---

## 🎯 Prochaines Étapes

Une fois le script exécuté avec succès:

1. **Configurer le fichier `.env`** (voir [PAYMENT_INSTALLATION.md](../../PAYMENT_INSTALLATION.md))
   ```bash
   cp .env.example .env
   # Éditer .env avec vos vraies credentials
   ```

2. **Créer les comptes providers**
   - Orange Money: https://developer.orange.com/
   - Wave: Contacter support Wave
   - Paydunya: https://paydunya.com/

3. **Tester en mode sandbox**
   - Configurer `PAYMENT_TEST_MODE=true` dans .env
   - Tester une commande avec paiement Wave/Orange Money

4. **Activer HTTPS** (requis pour webhooks en production)

5. **Configurer les webhooks** dans les dashboards providers

---

## 📞 Besoin d'aide?

- **Documentation complète:** [PAYMENT_INSTALLATION.md](../../PAYMENT_INSTALLATION.md)
- **Mapping des colonnes:** [COLONNES_PAIEMENT_MAPPING.md](COLONNES_PAIEMENT_MAPPING.md)
- **Fichiers créés:** [PAYMENT_IMPLEMENTATION_COMPLETE.md](../../PAYMENT_IMPLEMENTATION_COMPLETE.md)

---

**Créé le**: 2025-10-24
**Dernière mise à jour**: Compatible avec votre structure de base existante
