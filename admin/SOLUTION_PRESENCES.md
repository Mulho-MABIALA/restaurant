# 🔧 Solution - Erreur "Employé non trouvé" dans les Présences

## 🎯 Problème identifié

Lorsque vous cliquez sur "Cliquer pour plus de détails" dans l'onglet **Présences**, vous obtenez l'erreur :
```
Erreur: Employé non trouvé
```

## 🔍 Cause du problème

La requête SQL dans `gestion_paie.php` (ligne 270) utilisait :
```sql
LEFT JOIN departements d ON e.departement_id = d.id
```

**Problème** : La colonne `departement_id` n'existe peut-être pas dans la table `employes`, ou bien les employés n'ont pas de poste assigné, ce qui empêche de récupérer leurs informations.

## ✅ Correction appliquée

Le fichier `gestion_paie.php` a été corrigé pour utiliser :
```sql
LEFT JOIN departements d ON p.departement_id = d.id
```

Cette correction :
- ✅ Récupère le département via la table `postes` (plus logique)
- ✅ Ajoute des logs de diagnostic pour tracer les recherches
- ✅ Affiche l'ID de l'employé dans le message d'erreur si non trouvé

## 🚀 Comment vérifier

### Étape 1 : Lancer le diagnostic
```
http://localhost/restaurant/admin/diagnostic_presences.php
```

Ce script va :
- ✅ Vérifier la structure de la table employes
- ✅ Lister tous les employés actifs et leurs relations
- ✅ Tester la requête corrigée
- ✅ Vérifier les présences enregistrées
- ✅ Identifier les problèmes (employés sans poste, etc.)

### Étape 2 : Vérifier le résultat

Retournez sur :
```
http://localhost/restaurant/admin/gestion_paie.php
```

Allez dans l'onglet **Présences** et cliquez sur "Cliquer pour plus de détails" sur un employé.

**Résultat attendu** : Le modal s'ouvre avec les détails de l'employé et ses statistiques de présence.

## 🔧 Si le problème persiste

### Problème 1 : Employés sans poste assigné

Si le diagnostic montre que certains employés n'ont pas de poste :

1. Allez dans la gestion des employés
2. Éditez chaque employé et assignez-lui un poste
3. Ou exécutez cette requête SQL pour les assigner automatiquement à un poste par défaut :

```sql
-- Créer un poste par défaut si nécessaire
INSERT INTO postes (nom, departement_id, salaire, actif)
SELECT 'Employé', 1, 200000, 1
WHERE NOT EXISTS (SELECT 1 FROM postes WHERE nom = 'Employé');

-- Assigner tous les employés sans poste au poste "Employé"
UPDATE employes e
SET e.poste_id = (SELECT id FROM postes WHERE nom = 'Employé' LIMIT 1)
WHERE e.poste_id IS NULL OR e.poste_id = 0;
```

### Problème 2 : Employés avec ID incorrect

Si l'ID de l'employé cliqué n'existe pas en base :

1. Vérifiez les logs PHP dans `C:\wamp64\logs\php_error.log`
2. Cherchez les lignes commençant par "Recherche employé ID:"
3. Vérifiez que cet ID existe dans la table employes

### Problème 3 : Problème de permissions

Vérifiez que vous êtes bien connecté en tant qu'administrateur.

## 📊 Logs de diagnostic

Les logs sont maintenant activés. Pour les voir :

**Windows (WAMP)** :
```
C:\wamp64\logs\php_error.log
```

Recherchez les lignes contenant :
- `Recherche employé ID: X`
- `Employé trouvé: NOM PRENOM`
- `Employé X non trouvé dans la base de données`

## 🎓 Comprendre la structure

### Relations entre tables

```
employes
  └── poste_id → postes
                   └── departement_id → departements
```

L'employé est lié à :
- Un **poste** (obligatoire pour afficher les détails)
- Le poste est lié à un **département** (optionnel)

### Données retournées pour chaque employé

```javascript
{
  id: 1,
  nom: "DIOP",
  prenom: "Mamadou",
  poste_nom: "Chef Cuisinier",        // depuis table postes
  departement_nom: "Cuisine",         // depuis table departements (via postes)
  heures_semaine: 35,                 // depuis table postes
  heures_mois: 152,                   // depuis table postes
  heures_par_mois: 173                // depuis table postes
}
```

## ✅ Vérification finale

Une fois la correction appliquée, vous devriez pouvoir :

1. ✅ Cliquer sur "Cliquer pour plus de détails" dans les Présences
2. ✅ Voir le modal s'ouvrir avec :
   - Nom et prénom de l'employé
   - Poste et département
   - Horaires contractuels (heures/semaine, heures/mois)
   - Présence du jour (planifié vs réel)
   - Statistiques du mois (jours travaillés, retards, absences)

## 📁 Fichiers modifiés/créés

| Fichier | Modification | Ligne |
|---------|-------------|-------|
| `gestion_paie.php` | Correction requête SQL | 273 |
| `gestion_paie.php` | Ajout logs diagnostic | 266, 280-284 |
| `diagnostic_presences.php` | **Nouveau** - Outil diagnostic | - |
| `SOLUTION_PRESENCES.md` | **Nouveau** - Ce guide | - |

## 🔗 Liens utiles

- **Diagnostic des présences** : [diagnostic_presences.php](diagnostic_presences.php)
- **Configuration RH** : [rh_setup.php](rh_setup.php)
- **Système RH** : [gestion_paie.php](gestion_paie.php)
- **Guide complet RH** : [README_GESTION_PAIE.md](README_GESTION_PAIE.md)

---

**Mise à jour** : <?= date('d/m/Y H:i') ?>

Si le problème persiste après avoir suivi ce guide, lancez le diagnostic et consultez les logs pour identifier précisément le problème.
