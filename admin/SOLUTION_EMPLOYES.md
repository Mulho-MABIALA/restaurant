# ✅ Solution au problème de récupération des employés

## 🎯 Résumé du problème

Vous avez rencontré l'erreur suivante :

```
SQLSTATE[42S02]: Base table or view not found: 1146
La table 'restaurant.type_primes' n'existe pas
```

## 🚀 Solution en 3 clics

### Étape 1 : Créer toutes les tables RH
**👉 Cliquez ici : `http://localhost/restaurant/admin/create_rh_tables.php`**

Ce script va automatiquement créer **11 tables** :
- ✅ `departements` - Départements de l'entreprise
- ✅ `postes` - Postes et fonctions
- ✅ `employes` - Informations des employés
- ✅ `horaires` - Planning hebdomadaire
- ✅ `presences` - Enregistrement des présences
- ✅ `type_primes` - Types de primes disponibles
- ✅ `primes_employes` - Attribution des primes
- ✅ `conges` - Demandes de congés
- ✅ `soldes_conges` - Soldes de congés
- ✅ `avances_salaire` - Avances sur salaire
- ✅ `bulletins_paie` - Bulletins de paie

**Résultat attendu :** Un message de succès avec le nombre de tables créées.

---

### Étape 2 : Créer des données de test
**👉 Cliquez ici : `http://localhost/restaurant/admin/init_test_data.php`**

Ce script va créer :
- 🏢 **4 départements** (Cuisine, Service, Bar, Gestion)
- 💼 **5 postes** (Chef Cuisinier, Cuisinier, Serveur, Barman, Manager)
- 👥 **6 employés de test** avec des salaires appropriés

**Résultat attendu :** Liste des éléments créés avec confirmation.

---

### Étape 3 : Vérifier et utiliser le système
**👉 Retournez sur : `http://localhost/restaurant/admin/gestion_paie.php`**

**Résultat attendu :** La page s'affiche correctement avec les employés dans les listes déroulantes.

---

## 🔍 Diagnostic (optionnel)

Pour vérifier que tout fonctionne correctement :

**👉 Diagnostic complet : `http://localhost/restaurant/admin/diagnostic_employes.php`**

Ce script affiche :
- ✅ État de la connexion à la base de données
- ✅ Liste de toutes les tables et leur contenu
- ✅ Structure de la table employes
- ✅ Test de la requête SQL
- ✅ Test de l'encodage JSON

---

## 📊 Que contiennent les données de test ?

### Départements créés
| Nom | Couleur | Description |
|-----|---------|-------------|
| Cuisine | Rouge | Service de préparation culinaire |
| Service | Bleu | Service en salle |
| Bar | Orange | Service bar et boissons |
| Gestion | Violet | Direction et gestion |

### Postes créés
| Nom | Département | Salaire | Type |
|-----|-------------|---------|------|
| Chef Cuisinier | Cuisine | 450 000 FCFA | CDI |
| Cuisinier | Cuisine | 300 000 FCFA | CDI |
| Serveur | Service | 200 000 FCFA | CDI |
| Barman | Bar | 250 000 FCFA | CDI |
| Manager | Gestion | 500 000 FCFA | CDI |

### Employés créés
| Nom | Prénom | Email | Poste |
|-----|--------|-------|-------|
| DIOP | Mamadou | mamadou.diop@restaurant.sn | Chef Cuisinier |
| NDIAYE | Fatou | fatou.ndiaye@restaurant.sn | Serveur |
| SALL | Ibrahima | ibrahima.sall@restaurant.sn | Cuisinier |
| FALL | Aminata | aminata.fall@restaurant.sn | Serveur |
| KANE | Ousmane | ousmane.kane@restaurant.sn | Barman |
| SARR | Aissatou | aissatou.sarr@restaurant.sn | Manager |

### Types de primes créés
- Prime de performance (10% du salaire)
- Prime de présence (50 000 FCFA)
- Prime exceptionnelle (montant variable)
- Prime de fin d'année (100% du salaire - 13ème mois)
- Prime de transport (25 000 FCFA)
- Prime de restauration (30 000 FCFA)

---

## 🎓 Comment ça marche ?

### Flux de données

```
1. Requête SQL (gestion_paie.php ligne 453)
   ↓
2. EmployeesManager récupère les employés actifs
   ↓
3. Données encodées en JSON (ligne 542)
   ↓
4. Injection dans JavaScript (window.initialData.employes)
   ↓
5. Affichage dans les selects (ligne 1228)
```

### Pourquoi l'erreur se produisait ?

Le code PHP à la ligne 520-527 de `gestion_paie.php` essayait de faire une requête sur la table `type_primes` qui n'existait pas :

```php
$stmt = $conn->prepare("
    SELECT p.*, e.nom, e.prenom, tp.nom as type_prime_nom
    FROM primes_employes p
    LEFT JOIN employes e ON p.id_employe = e.id
    LEFT JOIN type_primes tp ON p.id_type_prime = tp.id  // ❌ Table manquante
    WHERE p.valide = 0
    ORDER BY p.created_at DESC
");
```

---

## ✅ Vérification finale

Après avoir suivi les 3 étapes, vous devriez :

### 1. Dans la base de données
```sql
-- Vérifier les tables
SHOW TABLES LIKE '%employes%';
SHOW TABLES LIKE '%type_primes%';

-- Compter les employés
SELECT COUNT(*) FROM employes WHERE statut = 'actif';
-- Résultat attendu : 6

-- Lister les employés
SELECT nom, prenom, statut FROM employes;
```

### 2. Dans le navigateur
- ✅ La page `gestion_paie.php` s'affiche sans erreur
- ✅ Les selects d'employés contiennent 6 options
- ✅ Vous pouvez générer des bulletins de paie
- ✅ Toutes les fonctionnalités RH sont accessibles

### 3. Dans la console JavaScript (F12)
```javascript
// Vérifier les données chargées
console.log(window.initialData.employes);
// Devrait afficher un tableau de 6 employés

console.log(window.initialData.employes.length);
// Devrait afficher : 6
```

---

## 🆘 En cas de problème

### Problème 1 : "Permission denied"
**Solution :** Vérifiez que vous êtes connecté en tant qu'administrateur.

### Problème 2 : "could not find driver"
**Solution :** Activez l'extension PDO MySQL dans `php.ini` :
```ini
extension=pdo_mysql
extension=mysqli
```
Puis redémarrez Apache/WAMP.

### Problème 3 : Les selects sont toujours vides
**Solution :**
1. Ouvrez la console du navigateur (F12)
2. Tapez : `console.log(window.initialData.employes)`
3. Si vous voyez `[]` (tableau vide), relancez `init_test_data.php`
4. Si vous voyez `undefined`, rechargez la page avec Ctrl+F5

### Problème 4 : Erreur "Duplicate entry"
**Solution :** Les données existent déjà. C'est normal ! Continuez vers l'étape suivante.

---

## 📁 Fichiers créés pour vous

| Fichier | Description | Utilisation |
|---------|-------------|-------------|
| `create_rh_tables.php` | Crée toutes les tables du système RH | **1ère étape obligatoire** |
| `init_test_data.php` | Ajoute des données de démonstration | **2ème étape recommandée** |
| `diagnostic_employes.php` | Diagnostic complet du système | Optionnel - pour déboguer |
| `README_GESTION_PAIE.md` | Guide détaillé de résolution | Documentation complète |
| `SOLUTION_EMPLOYES.md` | Ce fichier - Guide rapide | Solution en 3 étapes |

---

## 🎉 C'est terminé !

Votre système RH est maintenant opérationnel. Vous pouvez :

- ✅ Gérer les employés
- ✅ Enregistrer les présences
- ✅ Générer des bulletins de paie
- ✅ Gérer les congés
- ✅ Attribuer des primes
- ✅ Gérer les avances sur salaire

**Bon travail ! 🚀**

---

## 📞 Support

Si vous rencontrez encore des problèmes après avoir suivi ce guide :

1. Consultez le fichier `README_GESTION_PAIE.md` pour plus de détails
2. Vérifiez les logs PHP dans `C:\wamp64\logs\php_error.log`
3. Vérifiez la console JavaScript (F12) pour les erreurs
4. Assurez-vous que toutes les étapes ont été suivies dans l'ordre

**Dernière mise à jour :** <?= date('d/m/Y H:i') ?>
