# Guide de résolution - Problème de récupération des employés

## Problème identifié

La page `admin/gestion_paie.php` n'arrive pas à récupérer les employés de la base de données.

## Solution rapide (Recommandé)

### ⚡ Étape 0 : Créer toutes les tables manquantes

**Si vous voyez l'erreur "La table 'restaurant.type_primes' n'existe pas"** (ou toute autre table manquante) :

1. Ouvrez : `http://localhost/restaurant/admin/create_rh_tables.php`
2. Ce script va automatiquement créer **TOUTES** les tables nécessaires :
   - ✓ departements
   - ✓ postes
   - ✓ employes
   - ✓ horaires
   - ✓ presences
   - ✓ type_primes
   - ✓ primes_employes
   - ✓ conges
   - ✓ soldes_conges
   - ✓ avances_salaire
   - ✓ bulletins_paie
3. Il créera aussi les types de primes par défaut

**C'est la solution la plus rapide !** Ensuite, passez aux étapes suivantes.

## Diagnostic

### Étape 1 : Vérifier l'état de la base de données

Ouvrez dans votre navigateur : `http://localhost/restaurant/admin/diagnostic_employes.php`

Ce script va vérifier :
- ✓ La connexion à la base de données
- ✓ L'existence des tables (employes, postes, departements)
- ✓ Le nombre d'enregistrements dans chaque table
- ✓ La requête SQL utilisée pour récupérer les employés
- ✓ Le fonctionnement de la classe EmployeesManager
- ✓ L'encodage JSON des données

### Étape 2 : Solutions possibles

#### Solution 1 : La base de données est vide

Si le diagnostic montre que la table `employes` est vide ou n'existe pas :

1. Ouvrez : `http://localhost/restaurant/admin/init_test_data.php`
2. Ce script va créer automatiquement :
   - 4 départements (Cuisine, Service, Bar, Gestion)
   - 5 postes (Chef Cuisinier, Cuisinier, Serveur, Barman, Manager)
   - 6 employés de test avec le statut 'actif'

#### Solution 2 : Problème de configuration PDO

Si vous voyez l'erreur "could not find driver", vous devez activer les extensions PDO :

1. Ouvrez le fichier `php.ini` (dans WAMP : `C:\wamp64\bin\apache\apache2.x.x\bin\php.ini`)
2. Cherchez et décommentez (enlevez le `;` au début) les lignes :
   ```ini
   extension=pdo_mysql
   extension=mysqli
   ```
3. Redémarrez WAMP/Apache

#### Solution 3 : Tables manquantes

Si les tables n'existent pas, vous devez les créer. Voici les scripts SQL :

```sql
-- Créer la table departements
CREATE TABLE IF NOT EXISTS `departements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `couleur` varchar(7) DEFAULT '#3b82f6',
  `description` text,
  `actif` tinyint(1) DEFAULT 1,
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Créer la table postes
CREATE TABLE IF NOT EXISTS `postes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `departement_id` int(11) DEFAULT NULL,
  `salaire` decimal(10,2) DEFAULT NULL,
  `couleur` varchar(7) DEFAULT '#3b82f6',
  `type_contrat` enum('CDI','CDD','Stage','Interim') DEFAULT 'CDI',
  `heures_travail` int(11) DEFAULT 173,
  `actif` tinyint(1) DEFAULT 1,
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_departement` (`departement_id`),
  CONSTRAINT `fk_departement` FOREIGN KEY (`departement_id`) REFERENCES `departements` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Créer la table employes
CREATE TABLE IF NOT EXISTS `employes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `poste_id` int(11) DEFAULT NULL,
  `salaire` decimal(10,2) DEFAULT NULL,
  `statut` enum('actif','inactif','conge','suspendu') DEFAULT 'actif',
  `date_embauche` date DEFAULT NULL,
  `heure_debut` time DEFAULT '08:00:00',
  `heure_fin` time DEFAULT '17:00:00',
  `photo` varchar(255) DEFAULT NULL,
  `adresse` text,
  `ville` varchar(100) DEFAULT NULL,
  `code_postal` varchar(10) DEFAULT NULL,
  `date_naissance` date DEFAULT NULL,
  `numero_secu` varchar(50) DEFAULT NULL,
  `is_admin` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_poste` (`poste_id`),
  CONSTRAINT `fk_poste` FOREIGN KEY (`poste_id`) REFERENCES `postes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Étape 3 : Vérifier le fonctionnement

1. Après avoir exécuté l'une des solutions, retournez sur le diagnostic :
   `http://localhost/restaurant/admin/diagnostic_employes.php`

2. Vous devriez voir :
   - ✓ Connexion PDO établie
   - ✓ Tables existantes
   - ✓ Employés actifs dans la liste
   - ✓ JSON correctement encodé

3. Si tout est OK, allez sur la page de gestion :
   `http://localhost/restaurant/admin/gestion_paie.php`

## Problèmes JavaScript

Si les données sont bien récupérées côté PHP mais ne s'affichent pas dans les selects :

### 1. Ouvrir la console du navigateur
- Chrome/Edge : F12 → onglet Console
- Firefox : F12 → onglet Console

### 2. Vérifier window.initialData
Dans la console, tapez :
```javascript
console.log(window.initialData.employes);
```

Si vous voyez un tableau vide `[]` alors que les employés existent en base :
- Vérifier que la ligne 542 de `views/paie/index.php` contient bien :
  ```javascript
  employes: <?php echo json_encode($employes, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
  ```

### 3. Vérifier les erreurs JavaScript
Regardez dans la console s'il y a des erreurs JavaScript en rouge.

Erreurs courantes :
- `TypeError: Cannot read property 'map' of undefined` → La variable employes n'est pas définie
- `SyntaxError: Unexpected token` → Problème d'encodage JSON

## Support

Si le problème persiste après avoir suivi ces étapes :

1. Vérifiez les logs PHP :
   - WAMP : `C:\wamp64\logs\php_error.log`
   - Apache : `C:\wamp64\logs\apache_error.log`

2. Activez l'affichage des erreurs PHP en ajoutant au début de `gestion_paie.php` :
   ```php
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```

3. Consultez les logs dans la console du navigateur (F12)

## Fichiers créés pour vous aider

- **diagnostic_employes.php** : Diagnostic complet du système
- **init_test_data.php** : Création automatique de données de test
- **README_GESTION_PAIE.md** : Ce fichier d'aide

## Résumé des modifications apportées

### gestion_paie.php
- ✓ Ajout de logging pour tracer la récupération des employés
- ✓ Amélioration de la gestion d'erreurs avec messages explicites
- ✓ Affichage d'une page d'erreur claire en cas de problème
- ✓ Liens vers les outils de diagnostic et d'initialisation

### Avantages
- Messages d'erreur clairs et utiles
- Diagnostic automatique intégré
- Initialisation facile des données de test
- Meilleure traçabilité avec les logs
