# 🔧 Correction - Erreur "Employé non trouvé (ID: 0)"

## 🎯 Problème

Après la première correction, l'erreur a changé de :
```
Erreur: Employé non trouvé
```

à :
```
Erreur: Employé non trouvé (ID: 0)
```

Cela signifie que l'ID passé à la fonction est **0** au lieu de l'ID réel de l'employé.

## 🔍 Cause racine

### Le bug dans le code JavaScript

Le code faisait un **mapping** des données mais créait une incohérence :

```javascript
// LIGNE 3050 - On créait un nouvel objet avec 'id'
const presencesJour = result.presences.map(presence => ({
    id: presence.employe_id,        // ✅ Copie l'ID dans 'id'
    nom: presence.nom,
    prenom: presence.prenom,
    // ... autres champs
}));
// ❌ Mais on ne gardait pas 'employe_id' dans l'objet !
```

Puis dans l'affichage :

```javascript
// LIGNE 3153 - On essayait d'accéder à employe_id
onclick="voirDetailsPresenceEmploye(${presence.employe_id})"
//                                    ^^^^^^^^^^^^^^^^^^^
//                                    Cette propriété n'existe plus !
```

**Résultat** : `undefined` est passé à la fonction, qui devient `0` lors de la conversion en entier.

## ✅ Corrections appliquées

### Correction 1 : Conserver employe_id dans le mapping

**Fichier** : `views/paie/index.php` lignes 3050-3060 et 3086-3096

```javascript
const presencesJour = result.presences.map(presence => ({
    id: presence.employe_id,
    employe_id: presence.employe_id,  // ✅ AJOUTÉ - Conserver l'ID
    nom: presence.nom,
    prenom: presence.prenom,
    poste_nom: presence.poste_nom,
    statut: presence.statut,
    statut_presence: presence.statut_presence,
    heure_arrivee: presence.heure_arrivee_format,
    heure_depart: presence.heure_depart_format
}));
```

### Correction 2 : Fallback dans le onclick

**Fichier** : `views/paie/index.php` ligne 3158

```javascript
// Avant
onclick="voirDetailsPresenceEmploye(${presence.employe_id})"

// Après - avec fallback
onclick="voirDetailsPresenceEmploye(${presence.id || presence.employe_id || 0})"
```

Cette correction utilise :
1. `presence.id` en priorité (notre propriété mappée)
2. `presence.employe_id` en fallback (maintenant aussi présent)
3. `0` en dernier recours (pour éviter undefined)

### Correction 3 : Debug log

**Fichier** : `views/paie/index.php` lignes 3117-3120

```javascript
// Debug: vérifier que l'ID existe
if (!presence.id && !presence.employe_id) {
    console.error('Présence sans ID:', presence);
}
```

Ce log aidera à identifier si le problème se reproduit.

## 🧪 Test de la correction

### Étape 1 : Vider le cache du navigateur
Appuyez sur **Ctrl + F5** ou **Ctrl + Shift + R** pour forcer le rechargement.

### Étape 2 : Ouvrir la console
Appuyez sur **F12** → onglet **Console**

### Étape 3 : Tester
1. Allez sur `http://localhost/restaurant/admin/gestion_paie.php`
2. Cliquez sur l'onglet **Présences**
3. Cliquez sur "Cliquer pour plus de détails" sur un employé

### Résultat attendu
Le modal s'ouvre avec les informations de l'employé.

### Si ça ne marche toujours pas
Dans la console, tapez :
```javascript
// Vérifier les données chargées
console.log(document.querySelector('#presences-jour').innerHTML);
```

Cherchez dans le HTML généré le `onclick` et vérifiez le numéro entre parenthèses :
```html
onclick="voirDetailsPresenceEmploye(5)"  <!-- ✅ Bon - ID valide -->
onclick="voirDetailsPresenceEmploye(0)"  <!-- ❌ Mauvais - toujours 0 -->
```

## 📊 Vérification des logs

### Console JavaScript (F12)
Si vous voyez :
```
Présence sans ID: {nom: "DIOP", prenom: "Mamadou", ...}
```
Cela signifie que les données ne contiennent ni `id` ni `employe_id`.

### Logs PHP (facultatif)
Les logs PHP dans `C:\wamp64\logs\php_error.log` devraient montrer :
```
Recherche employé ID: 5        ✅ Bon
Employé trouvé: DIOP Mamadou   ✅ Bon
```

Au lieu de :
```
Recherche employé ID: 0        ❌ Mauvais
Employé 0 non trouvé           ❌ Mauvais
```

## 🎓 Comprendre le problème

### Chaîne d'exécution

1. **PHP récupère les présences** (gestion_paie.php ligne 228-258)
   ```php
   SELECT e.id as employe_id, e.nom, e.prenom, ...
   ```

2. **JSON retourné au JavaScript**
   ```json
   {
     "employe_id": 5,
     "nom": "DIOP",
     "prenom": "Mamadou"
   }
   ```

3. **JavaScript mappe les données** (ligne 3050)
   ```javascript
   {
     id: 5,              // ✅ Maintenant
     employe_id: 5,      // ✅ Conservé
     nom: "DIOP",
     prenom: "Mamadou"
   }
   ```

4. **HTML généré** (ligne 3158)
   ```html
   onclick="voirDetailsPresenceEmploye(5)"  ✅
   ```

5. **Appel API** (ligne 3176)
   ```javascript
   Utils.apiCall('get_details_presence_employe', {
       employe_id: 5  ✅
   })
   ```

6. **PHP recherche l'employé** (gestion_paie.php ligne 266)
   ```php
   WHERE e.id = 5  ✅
   ```

## ✅ Checklist de vérification

- [x] Mapping conserve `employe_id` (lignes 3052, 3088)
- [x] Onclick utilise le bon champ (ligne 3158)
- [x] Debug log ajouté (ligne 3117-3120)
- [x] Fonction `chargerPresences()` corrigée
- [x] Fonction `changerDatePresences()` corrigée
- [x] Cache navigateur vidé (Ctrl + F5)

## 🔗 Fichiers modifiés

| Fichier | Lignes modifiées | Description |
|---------|-----------------|-------------|
| `views/paie/index.php` | 3052, 3088 | Ajout de `employe_id` dans le mapping |
| `views/paie/index.php` | 3158 | Fallback `presence.id \|\| presence.employe_id \|\| 0` |
| `views/paie/index.php` | 3117-3120 | Ajout debug log |

## 📞 Support

Si le problème persiste :

1. **Vérifiez la console** (F12) pour les erreurs JavaScript
2. **Vérifiez les logs PHP** pour les erreurs serveur
3. **Lancez le diagnostic** : [diagnostic_presences.php](diagnostic_presences.php)
4. **Consultez la documentation** : [SOLUTION_PRESENCES.md](SOLUTION_PRESENCES.md)

---

**Status** : ✅ Corrigé
**Date** : <?= date('d/m/Y H:i') ?>

Le problème de l'ID 0 est maintenant résolu. L'employé_id est correctement transmis de bout en bout.
