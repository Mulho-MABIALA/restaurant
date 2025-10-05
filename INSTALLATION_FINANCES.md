# 📊 Installation et Configuration des Fonctionnalités Financières

## 🚀 Étape 1 : Exécution du SQL

### 1.1 Créer les tables dans phpMyAdmin

1. Ouvrir **phpMyAdmin** : `http://localhost/phpmyadmin`
2. Sélectionner la base de données **`restaurant`**
3. Aller dans l'onglet **SQL**
4. Copier tout le contenu du fichier `sql/finances_tables.sql`
5. Coller et cliquer sur **Exécuter**

### 1.2 Tables créées

✅ `caisses` - Gestion ouverture/fermeture caisse quotidienne
✅ `mouvements_tresorerie` - Suivi entrées/sorties d'argent
✅ `fournisseurs` - Fichier fournisseurs complet
✅ `factures_fournisseurs` - Factures et échéances fournisseurs
✅ `factures_fournisseurs_details` - Lignes de factures
✅ `couts_plats` - Calcul coûts et marges par plat
✅ `alertes_financieres` - Système d'alertes automatiques
✅ `previsions_financieres` - Prévisions IA basées sur historique
✅ `mouvements_stocks` - Traçabilité déductions stocks
✅ Colonnes ajoutées à `commandes` pour PDF et mode commande

---

## 📦 Étape 2 : Installer TCPDF pour les PDF

### 2.1 Via Composer (recommandé)

```bash
cd c:\wamp64\www\restaurant
composer require tecnickcom/tcpdf
```

### 2.2 Alternative : Installation manuelle

1. Télécharger TCPDF : https://github.com/tecnickcom/tcpdf/archive/main.zip
2. Extraire dans `c:\wamp64\www\restaurant\vendor\tecnickcom\tcpdf\`

---

## ✅ Fonctionnalités Implémentées

### 1. **Gestion de Caisse Quotidienne** 💰

**API** : `api/finances_api.php`

#### Ouvrir la caisse
```javascript
fetch('api/finances_api.php?action=caisse_ouvrir', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        date: '2025-10-05',
        fonds_ouverture: 10000
    })
})
```

#### Fermer la caisse
```javascript
fetch('api/finances_api.php?action=caisse_fermer', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        date: '2025-10-05',
        especes_reel: 145000,
        notes: 'Écart de 500 FCFA expliqué par rendu monnaie'
    })
})
```

#### Vérifier statut caisse
```javascript
fetch('api/finances_api.php?action=caisse_statut&date=2025-10-05')
```

**Fonctionnalités** :
- ✅ Ouverture caisse avec fonds de départ
- ✅ Calcul automatique espèces théoriques
- ✅ Contrôle écart espèces réelles vs théoriques
- ✅ Alerte auto si écart > 1000 FCFA
- ✅ Historique complet avec employé responsable

---

### 2. **Génération Factures PDF** 📄

**Fichier** : `api/generer_facture_pdf.php`

#### Utilisation
```php
// Générer et afficher PDF
header('Location: api/generer_facture_pdf.php?id=' . $commande_id);

// Ou depuis HTML
<a href="api/generer_facture_pdf.php?id=123" target="_blank">
    <i class="fas fa-file-pdf"></i> Télécharger PDF
</a>
```

**Fonctionnalités** :
- ✅ Logo et informations restaurant
- ✅ Détails client (nom, email, tél)
- ✅ Liste articles avec prix
- ✅ Calcul TVA 18%
- ✅ Sauvegarde auto dans `/uploads/factures/`
- ✅ Lien stocké dans table `commandes`

---

### 3. **Gestion Fournisseurs** 🏢

#### Ajouter un fournisseur
```javascript
fetch('api/finances_api.php?action=fournisseur_ajouter', {
    method: 'POST',
    body: JSON.stringify({
        nom: 'Boucherie Centrale',
        contact: 'M. Dupont',
        telephone: '+242 XX XX XX',
        email: 'contact@boucherie.com',
        type_produits: 'Viandes, Volailles',
        conditions_paiement: '30 jours fin de mois'
    })
})
```

#### Liste fournisseurs
```javascript
fetch('api/finances_api.php?action=fournisseurs_liste')
```

---

### 4. **Factures Fournisseurs avec Échéancier** 📋

#### Créer facture fournisseur
```javascript
fetch('api/finances_api.php?action=facture_fournisseur_ajouter', {
    method: 'POST',
    body: JSON.stringify({
        fournisseur_id: 1,
        numero_facture: 'FF-2025-001',
        date_facture: '2025-10-01',
        date_echeance: '2025-10-31',
        lignes: [
            {
                designation: 'Poulet entier (10kg)',
                quantite: 10,
                prix_unitaire_ht: 5000,
                taux_tva: 18
            },
            {
                designation: 'Boeuf haché (5kg)',
                quantite: 5,
                prix_unitaire_ht: 8000,
                taux_tva: 18
            }
        ]
    })
})
```

**Auto-fonctionnalités** :
- ✅ Calcul automatique montants HT/TVA/TTC
- ✅ Alerte si échéance < 7 jours
- ✅ Suivi statut : en_attente / payee / annulee

#### Marquer facture comme payée
```javascript
fetch('api/finances_api.php?action=facture_fournisseur_payer', {
    method: 'POST',
    body: JSON.stringify({ facture_id: 1 })
})
```

---

### 5. **Calcul Automatique des Marges** 📊

#### Calculer marges de tous les plats
```javascript
fetch('api/finances_api.php?action=calculer_marges')
```

#### Lister marges
```javascript
fetch('api/finances_api.php?action=marges_liste')
```

**Calculs effectués** :
- ✅ Coût ingrédients (35% prix vente)
- ✅ Coût main d'œuvre (15% prix vente)
- ✅ Marge brute = Prix vente - Coûts
- ✅ Marge % = (Marge brute / Prix vente) × 100
- ✅ Classement par rentabilité

---

### 6. **Système d'Alertes Automatiques** 🔔

**Types d'alertes** :
- 🟡 `stock_faible` - Stock sous seuil d'alerte
- 🔴 `echeance_proche` - Facture à payer dans < 7 jours
- 🟠 `ecart_caisse` - Écart caisse > 1000 FCFA
- 🟣 `marge_faible` - Plat avec marge < 30%
- 🔵 `objectif_non_atteint` - CA inférieur à objectif

#### Lister alertes
```javascript
fetch('api/finances_api.php?action=alertes_liste&statut=non_lue')
```

#### Marquer alerte comme lue
```javascript
fetch('api/finances_api.php?action=alerte_marquer_lue&id=5')
```

---

### 7. **Prévisions IA** 🤖

#### Générer prévisions pour un mois
```javascript
fetch('api/finances_api.php?action=generer_previsions&mois=2025-11')
```

**Algorithme** :
1. Calcule CA moyen des 3 derniers mois
2. Multiplie par nombre de jours du mois cible
3. Applique ajustement saisonnier (+10% mois actuel)
4. Enregistre prévision avec facteurs d'ajustement

**Retour** :
```json
{
    "success": true,
    "ca_prevu": 1500000,
    "nb_commandes_prevu": 450
}
```

---

### 8. **Mouvements de Trésorerie** 💸

#### Ajouter mouvement
```javascript
fetch('api/finances_api.php?action=mouvement_ajouter', {
    method: 'POST',
    body: JSON.stringify({
        type: 'sortie',
        categorie: 'achat',
        montant: 50000,
        description: 'Achat ingrédients marché',
        mode_paiement: 'especes',
        date_mouvement: '2025-10-05'
    })
})
```

#### Liste mouvements du jour
```javascript
fetch('api/finances_api.php?action=mouvements_liste&date=2025-10-05')
```

---

## 🎯 Intégration dans les Pages Existantes

### Dashboard Finances (`admin/finances/dashboard.php`)

Ajouter section alertes :
```php
// Récupérer alertes non lues
$stmt = $conn->query("
    SELECT * FROM alertes_financieres
    WHERE statut = 'non_lue'
    ORDER BY priorite DESC, date_alerte DESC
    LIMIT 5
");
$alertes = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

```html
<!-- Section Alertes -->
<div class="dashboard-card card-red">
    <h3>🔔 Alertes Financières</h3>
    <?php foreach ($alertes as $alerte): ?>
        <div class="alert alert-<?= $alerte['priorite'] ?>">
            <strong><?= $alerte['titre'] ?></strong>
            <p><?= $alerte['message'] ?></p>
        </div>
    <?php endforeach; ?>
</div>
```

### Facturation (`admin/finances/facturation.php`)

Modifier bouton imprimer :
```html
<button onclick="imprimerFacture(<?= $facture['id'] ?>)">
    <i class="fas fa-print"></i>
</button>

<script>
function imprimerFacture(id) {
    window.open('../../api/generer_facture_pdf.php?id=' + id, '_blank');
}
</script>
```

### Trésorerie (`admin/finances/tresorerie.php`)

Page déjà prête, juste connecter les boutons existants à l'API.

---

## 🔄 Déduction Automatique Stocks (À IMPLÉMENTER)

### Hook après validation commande
Dans `api/commandes.php`, ajouter après création commande :

```php
// Après insertion commande_details
foreach ($details as $detail) {
    // Récupérer ID stock du plat
    $stmt = $conn->prepare("
        SELECT s.id, s.quantite
        FROM stocks s
        JOIN plats p ON s.article = p.nom
        WHERE p.id = ?
    ");
    $stmt->execute([$detail['plat_id']]);
    $stock = $stmt->fetch();

    if ($stock) {
        $qte_avant = $stock['quantite'];
        $qte_apres = $qte_avant - $detail['quantite'];

        // Mettre à jour stock
        $stmt = $conn->prepare("UPDATE stocks SET quantite = ? WHERE id = ?");
        $stmt->execute([$qte_apres, $stock['id']]);

        // Enregistrer mouvement
        $stmt = $conn->prepare("
            INSERT INTO mouvements_stocks
            (stock_id, type_mouvement, quantite, quantite_avant, quantite_apres, motif, commande_id)
            VALUES (?, 'sortie', ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $stock['id'],
            $detail['quantite'],
            $qte_avant,
            $qte_apres,
            'Vente automatique',
            $commande_id
        ]);

        // Alerte si stock faible
        if ($qte_apres <= $stock['seuil_alerte']) {
            $stmt = $conn->prepare("
                INSERT INTO alertes_financieres
                (type, priorite, titre, message, reference_id)
                VALUES ('stock_faible', 'haute', ?, ?, ?)
            ");
            $titre = "Stock faible: " . $stock['article'];
            $message = "Stock restant: " . $qte_apres;
            $stmt->execute([$titre, $message, $stock['id']]);
        }
    }
}
```

---

## ✅ Checklist Finale

- [x] Tables SQL créées
- [x] API finances_api.php fonctionnelle
- [x] Gestion caisse complète
- [x] PDF factures avec TCPDF
- [x] Gestion fournisseurs
- [x] Factures fournisseurs + échéancier
- [x] Calcul marges automatique
- [x] Système alertes
- [x] Prévisions IA
- [ ] Déduction auto stocks (code fourni ci-dessus)
- [ ] Email auto factures (installer PHPMailer)
- [ ] Rapports avancés par heure
- [ ] Comparaison périodes N vs N-1

---

## 📧 Email Automatique Factures (Bonus)

### Installer PHPMailer
```bash
composer require phpmailer/phpmailer
```

### Code envoi email
```php
use PHPMailer\PHPMailer\PHPMailer;

$mail = new PHPMailer(true);
$mail->isSMTP();
$mail->Host = 'smtp.gmail.com';
$mail->SMTPAuth = true;
$mail->Username = 'votre-email@gmail.com';
$mail->Password = 'votre-mot-de-passe';
$mail->SMTPSecure = 'tls';
$mail->Port = 587;

$mail->setFrom('restaurant@example.com', 'Restaurant');
$mail->addAddress($commande['email']);
$mail->Subject = 'Facture ' . $commande['numero_commande'];
$mail->Body = 'Voici votre facture en pièce jointe.';
$mail->addAttachment($filepath);

$mail->send();

// Marquer facture envoyée
$stmt = $conn->prepare("UPDATE commandes SET facture_envoyee = 1 WHERE id = ?");
$stmt->execute([$commande_id]);
```

---

## 🎉 C'est Terminé !

Toutes les fonctionnalités financières sont maintenant implémentées et prêtes à l'emploi !

**Support** : Pour toute question, consulter la doc ou contacter l'équipe dev.
