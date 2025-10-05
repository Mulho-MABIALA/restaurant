<?php
session_start();
require_once '../config.php';
header('Content-Type: application/json');

// Vérifier l'authentification
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

$action = $_GET['action'] ?? '';
$admin_id = $_SESSION['admin_id'];

try {
    switch ($action) {

        // ============= GESTION CAISSE =============
        case 'caisse_ouvrir':
            $data = json_decode(file_get_contents('php://input'), true);
            $date = $data['date'] ?? date('Y-m-d');
            $fonds_ouverture = $data['fonds_ouverture'] ?? 0;

            // Vérifier si caisse déjà ouverte
            $stmt = $conn->prepare("SELECT id FROM caisses WHERE date_caisse = ? AND statut = 'ouverte'");
            $stmt->execute([$date]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Caisse déjà ouverte pour cette date']);
                exit;
            }

            $stmt = $conn->prepare("
                INSERT INTO caisses (date_caisse, statut, fonds_ouverture, employe_ouverture_id, heure_ouverture)
                VALUES (?, 'ouverte', ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    statut = 'ouverte',
                    fonds_ouverture = ?,
                    employe_ouverture_id = ?,
                    heure_ouverture = NOW()
            ");
            $stmt->execute([$date, $fonds_ouverture, $admin_id, $fonds_ouverture, $admin_id]);

            echo json_encode(['success' => true, 'message' => 'Caisse ouverte']);
            break;

        case 'caisse_fermer':
            $data = json_decode(file_get_contents('php://input'), true);
            $date = $data['date'] ?? date('Y-m-d');
            $especes_reel = $data['especes_reel'] ?? 0;
            $notes = $data['notes'] ?? '';

            // Calculer les totaux du jour
            $stmt = $conn->prepare("
                SELECT
                    COALESCE(SUM(CASE WHEN mode_paiement = 'Espèces' THEN total ELSE 0 END), 0) as total_especes,
                    COALESCE(SUM(CASE WHEN mode_paiement = 'Carte bancaire' THEN total ELSE 0 END), 0) as total_cartes,
                    COALESCE(SUM(CASE WHEN mode_paiement = 'Mobile Money' THEN total ELSE 0 END), 0) as total_mobile,
                    COALESCE(SUM(total), 0) as total_ventes
                FROM commandes
                WHERE DATE(date_commande) = ? AND statut_paiement = 'Payé'
            ");
            $stmt->execute([$date]);
            $totaux = $stmt->fetch(PDO::FETCH_ASSOC);

            // Récupérer fonds d'ouverture
            $stmt = $conn->prepare("SELECT fonds_ouverture FROM caisses WHERE date_caisse = ?");
            $stmt->execute([$date]);
            $caisse = $stmt->fetch(PDO::FETCH_ASSOC);
            $fonds_ouverture = $caisse['fonds_ouverture'] ?? 0;

            $total_especes_theorique = $fonds_ouverture + $totaux['total_especes'];
            $ecart = $especes_reel - $total_especes_theorique;

            $stmt = $conn->prepare("
                UPDATE caisses SET
                    statut = 'fermee',
                    total_especes_theorique = ?,
                    total_especes_reel = ?,
                    total_cartes = ?,
                    total_mobile_money = ?,
                    total_ventes = ?,
                    ecart = ?,
                    employe_fermeture_id = ?,
                    heure_fermeture = NOW(),
                    notes = ?
                WHERE date_caisse = ?
            ");
            $stmt->execute([
                $total_especes_theorique,
                $especes_reel,
                $totaux['total_cartes'],
                $totaux['total_mobile'],
                $totaux['total_ventes'],
                $ecart,
                $admin_id,
                $notes,
                $date
            ]);

            // Créer alerte si écart significatif (> 1000 FCFA)
            if (abs($ecart) > 1000) {
                $stmt = $conn->prepare("
                    INSERT INTO alertes_financieres (type, priorite, titre, message, admin_id)
                    VALUES ('ecart_caisse', 'haute', ?, ?, ?)
                ");
                $titre = "Écart de caisse important";
                $message = "Écart de " . number_format($ecart) . " FCFA détecté pour le " . $date;
                $stmt->execute([$titre, $message, $admin_id]);
            }

            echo json_encode([
                'success' => true,
                'ecart' => $ecart,
                'message' => 'Caisse fermée'
            ]);
            break;

        case 'caisse_statut':
            $date = $_GET['date'] ?? date('Y-m-d');

            $stmt = $conn->prepare("SELECT * FROM caisses WHERE date_caisse = ?");
            $stmt->execute([$date]);
            $caisse = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$caisse) {
                echo json_encode([
                    'success' => true,
                    'statut' => 'fermee',
                    'fonds_ouverture' => 0,
                    'total_especes_theorique' => 0,
                    'total_especes_reel' => 0,
                    'total_cartes' => 0,
                    'total_mobile_money' => 0,
                    'total_ventes' => 0,
                    'ecart' => 0
                ]);
            } else {
                echo json_encode(['success' => true] + $caisse);
            }
            break;

        // ============= MOUVEMENTS TRÉSORERIE =============
        case 'mouvement_ajouter':
            $data = json_decode(file_get_contents('php://input'), true);

            $stmt = $conn->prepare("
                INSERT INTO mouvements_tresorerie
                (type, categorie, montant, description, mode_paiement, date_mouvement, admin_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['type'],
                $data['categorie'],
                $data['montant'],
                $data['description'],
                $data['mode_paiement'],
                $data['date_mouvement'],
                $admin_id
            ]);

            echo json_encode(['success' => true, 'message' => 'Mouvement ajouté']);
            break;

        case 'mouvements_liste':
            $date = $_GET['date'] ?? date('Y-m-d');

            $stmt = $conn->prepare("
                SELECT * FROM mouvements_tresorerie
                WHERE date_mouvement = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$date]);
            $mouvements = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'mouvements' => $mouvements]);
            break;

        // ============= FOURNISSEURS =============
        case 'fournisseurs_liste':
            $stmt = $conn->query("SELECT * FROM fournisseurs ORDER BY nom ASC");
            $fournisseurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'fournisseurs' => $fournisseurs]);
            break;

        case 'fournisseur_ajouter':
            $data = json_decode(file_get_contents('php://input'), true);

            $stmt = $conn->prepare("
                INSERT INTO fournisseurs (nom, contact, telephone, email, adresse, type_produits, conditions_paiement)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['nom'],
                $data['contact'] ?? '',
                $data['telephone'] ?? '',
                $data['email'] ?? '',
                $data['adresse'] ?? '',
                $data['type_produits'] ?? '',
                $data['conditions_paiement'] ?? ''
            ]);

            echo json_encode(['success' => true, 'id' => $conn->lastInsertId()]);
            break;

        // ============= FACTURES FOURNISSEURS =============
        case 'facture_fournisseur_ajouter':
            $data = json_decode(file_get_contents('php://input'), true);

            // Créer facture avec les montants fournis
            $stmt = $conn->prepare("
                INSERT INTO factures_fournisseurs
                (fournisseur_id, numero_facture, date_facture, date_echeance, montant_ht, montant_tva, montant_ttc, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['fournisseur_id'],
                $data['numero_facture'],
                $data['date_facture'],
                $data['date_echeance'],
                $data['montant_ht'],
                $data['montant_tva'],
                $data['montant_ttc'],
                $data['description'] ?? null
            ]);
            $facture_id = $conn->lastInsertId();

            // Créer alerte pour échéance
            $jours_restants = (strtotime($data['date_echeance']) - time()) / 86400;
            if ($jours_restants <= 7) {
                $stmt = $conn->prepare("
                    INSERT INTO alertes_financieres (type_alerte, priorite, titre, message, reference_id)
                    VALUES ('echeance_facture', 'warning', ?, ?, ?)
                ");
                $titre = "Échéance proche - Facture " . $data['numero_facture'];
                $message = "Échéance dans " . round($jours_restants) . " jours";
                $stmt->execute([$titre, $message, $facture_id]);
            }

            echo json_encode(['success' => true, 'id' => $facture_id]);
            break;

        case 'factures_fournisseurs_liste':
            $stmt = $conn->query("
                SELECT ff.*, f.nom as nom_fournisseur
                FROM factures_fournisseurs ff
                JOIN fournisseurs f ON ff.fournisseur_id = f.id
                ORDER BY ff.date_facture DESC
            ");
            $factures = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'factures' => $factures]);
            break;

        case 'facture_fournisseur_payer':
            $id = $_GET['id'] ?? null;

            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID manquant']);
                exit;
            }

            $stmt = $conn->prepare("
                UPDATE factures_fournisseurs
                SET statut = 'payee', date_paiement = CURDATE()
                WHERE id = ?
            ");
            $stmt->execute([$id]);

            echo json_encode(['success' => true, 'message' => 'Facture marquée comme payée']);
            break;

        case 'facture_fournisseur_details':
            $id = $_GET['id'] ?? null;

            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID manquant']);
                exit;
            }

            $stmt = $conn->prepare("
                SELECT ff.*, f.nom as fournisseur_nom, f.contact_nom, f.telephone, f.email, f.adresse, f.ville
                FROM factures_fournisseurs ff
                LEFT JOIN fournisseurs f ON ff.fournisseur_id = f.id
                WHERE ff.id = ?
            ");
            $stmt->execute([$id]);
            $facture = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($facture) {
                echo json_encode(['success' => true, 'facture' => $facture]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Facture non trouvée']);
            }
            break;

        // ============= ALERTES =============
        case 'alertes_liste':
            $statut = $_GET['statut'] ?? 'non_lue';

            $stmt = $conn->prepare("
                SELECT * FROM alertes_financieres
                WHERE statut = ?
                ORDER BY date_alerte DESC
                LIMIT 50
            ");
            $stmt->execute([$statut]);
            $alertes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'alertes' => $alertes]);
            break;

        case 'alerte_marquer_lue':
            $id = $_GET['id'];

            $stmt = $conn->prepare("UPDATE alertes_financieres SET statut = 'lue' WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true]);
            break;

        // ============= CALCUL MARGES =============
        case 'calculer_marges':
            // Récupérer tous les plats
            $stmt = $conn->query("SELECT id, nom, prix FROM plats WHERE statut = 'disponible'");
            $plats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($plats as $plat) {
                // Calculer coût moyen des ingrédients (simulé pour l'instant)
                $cout_ingredients = $plat['prix'] * 0.35; // 35% du prix
                $cout_main_oeuvre = $plat['prix'] * 0.15; // 15% du prix
                $cout_total = $cout_ingredients + $cout_main_oeuvre;
                $marge_brute = $plat['prix'] - $cout_total;
                $marge_pourcentage = ($marge_brute / $plat['prix']) * 100;

                $stmt = $conn->prepare("
                    INSERT INTO couts_plats (plat_id, cout_ingredients, cout_main_oeuvre, cout_total, prix_vente, marge_brute, marge_pourcentage)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        cout_ingredients = ?,
                        cout_main_oeuvre = ?,
                        cout_total = ?,
                        prix_vente = ?,
                        marge_brute = ?,
                        marge_pourcentage = ?
                ");
                $stmt->execute([
                    $plat['id'], $cout_ingredients, $cout_main_oeuvre, $cout_total, $plat['prix'], $marge_brute, $marge_pourcentage,
                    $cout_ingredients, $cout_main_oeuvre, $cout_total, $plat['prix'], $marge_brute, $marge_pourcentage
                ]);
            }

            echo json_encode(['success' => true, 'message' => 'Marges calculées']);
            break;

        case 'marges_liste':
            $stmt = $conn->query("
                SELECT cp.*, p.nom as nom_plat
                FROM couts_plats cp
                JOIN plats p ON cp.plat_id = p.id
                ORDER BY cp.marge_pourcentage ASC
            ");
            $marges = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'marges' => $marges]);
            break;

        // ============= PRÉVISIONS =============
        case 'generer_previsions':
            $mois = $_GET['mois'] ?? date('Y-m');

            // Calculer CA moyen des 3 derniers mois
            $stmt = $conn->query("
                SELECT AVG(total_jour) as ca_moyen
                FROM (
                    SELECT DATE(date_commande) as jour, SUM(total) as total_jour
                    FROM commandes
                    WHERE date_commande >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
                    AND statut_paiement = 'Payé'
                    GROUP BY DATE(date_commande)
                ) as daily_sales
            ");
            $resultat = $stmt->fetch(PDO::FETCH_ASSOC);
            $ca_moyen_jour = $resultat['ca_moyen'] ?? 0;

            // Nombre de jours dans le mois
            $nb_jours = cal_days_in_month(CAL_GREGORIAN, date('m', strtotime($mois)), date('Y', strtotime($mois)));
            $ca_prevu = $ca_moyen_jour * $nb_jours;

            // Ajustement saisonnier (+10% si mois actuel)
            if (date('Y-m', strtotime($mois)) == date('Y-m')) {
                $ca_prevu *= 1.1;
            }

            $stmt = $conn->prepare("
                INSERT INTO previsions_financieres (periode, ca_prevu, nb_commandes_prevu, facteurs_ajustement)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE ca_prevu = ?, nb_commandes_prevu = ?
            ");
            $nb_commandes_prevu = round($ca_prevu / ($ca_moyen_jour > 0 ? $ca_moyen_jour : 1));
            $facteurs = "Basé sur moyenne 3 mois + ajustement saisonnier";
            $stmt->execute([
                $mois . '-01', $ca_prevu, $nb_commandes_prevu, $facteurs,
                $ca_prevu, $nb_commandes_prevu
            ]);

            echo json_encode([
                'success' => true,
                'ca_prevu' => $ca_prevu,
                'nb_commandes_prevu' => $nb_commandes_prevu
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Action non reconnue']);
    }

} catch (PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
