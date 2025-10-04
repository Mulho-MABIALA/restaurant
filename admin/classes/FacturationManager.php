<?php
class FacturationManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    // Génération automatique facture client
    public function genererFactureCommande($commande_id) {
        try {
            $this->db->beginTransaction();
            
            // Récupérer infos commande
            $commande = $this->getCommandeDetails($commande_id);
            if (!$commande) {
                throw new Exception("Commande introuvable");
            }
            
            // Générer numéro facture
            $numero_facture = $this->genererNumeroFacture();
            
            // Calculer montants
            $montants = $this->calculerMontantsCommande($commande_id);
            
            // Créer facture
            $facture_id = $this->creerFactureClient([
                'numero_facture' => $numero_facture,
                'commande_id' => $commande_id,
                'montant_ht' => $montants['ht'],
                'montant_tva' => $montants['tva'],
                'montant_ttc' => $montants['ttc'],
                'mode_paiement' => $commande['mode_paiement'] ?? 'especes'
            ]);
            
            // Générer PDF
            $pdf_path = $this->genererPDFFacture($facture_id);
            
            // Mettre à jour chemin PDF
            $this->updateFacturePDF($facture_id, $pdf_path);
            
            // Enregistrer mouvement trésorerie
            $this->enregistrerMouvementVente($facture_id, $montants['ttc']);
            
            // Déduire stocks
            $this->deduireStocks($commande_id);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'facture_id' => $facture_id,
                'numero_facture' => $numero_facture,
                'pdf_path' => $pdf_path
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function genererNumeroFacture() {
        $annee = date('Y');
        $sql = "SELECT COUNT(*) FROM factures_clients WHERE YEAR(date_facture) = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$annee]);
        $count = $stmt->fetchColumn() + 1;
        
        return sprintf("FC%d-%04d", $annee, $count);
    }
    
    private function calculerMontantsCommande($commande_id) {
        $sql = "SELECT SUM(prix * quantite) as total_ht
                FROM commande_details 
                WHERE commande_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$commande_id]);
        $total_ht = $stmt->fetchColumn() ?: 0;
        
        // TVA à 18% pour le Sénégal
        $tva = $total_ht * 0.18;
        $total_ttc = $total_ht + $tva;
        
        return [
            'ht' => round($total_ht, 0),
            'tva' => round($tva, 0),
            'ttc' => round($total_ttc, 0)
        ];
    }
    
    private function creerFactureClient($data) {
        $sql = "INSERT INTO factures_clients 
                (numero_facture, commande_id, date_facture, montant_ht, 
                 montant_tva, montant_ttc, mode_paiement, statut, date_paiement)
                VALUES (?, ?, NOW(), ?, ?, ?, ?, 'payee', NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['numero_facture'],
            $data['commande_id'],
            $data['montant_ht'],
            $data['montant_tva'],
            $data['montant_ttc'],
            $data['mode_paiement']
        ]);
        
        return $this->db->lastInsertId();
    }
    
    // Saisie facture fournisseur
    public function saisirFactureFournisseur($data) {
        try {
            $this->db->beginTransaction();
            
            // Valider les données
            if (empty($data['numero_facture']) || empty($data['fournisseur_id'])) {
                throw new Exception("Données obligatoires manquantes");
            }
            
            // Calculer les montants à partir des lignes
            $montants = $this->calculerMontantsLignes($data['lignes']);
            
            // Créer facture fournisseur
            $sql = "INSERT INTO factures_fournisseurs 
                    (numero_facture, fournisseur_id, date_facture, date_echeance,
                     montant_ht, montant_tva, montant_ttc, statut)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'en_attente')";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['numero_facture'],
                $data['fournisseur_id'],
                $data['date_facture'],
                $data['date_echeance'],
                $montants['ht'],
                $montants['tva'],
                $montants['ttc']
            ]);
            
            $facture_id = $this->db->lastInsertId();
            
            // Ajouter lignes de facture
            foreach ($data['lignes'] as $ligne) {
                $this->ajouterLigneFactureFournisseur($facture_id, $ligne);
            }
            
            // Créer alerte échéance
            $this->creerAlerteEcheance($facture_id, $data['date_echeance']);
            
            $this->db->commit();
            return ['success' => true, 'facture_id' => $facture_id];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function calculerMontantsLignes($lignes) {
        $total_ht = 0;
        $total_tva = 0;
        
        foreach ($lignes as $ligne) {
            $ht_ligne = $ligne['quantite'] * $ligne['prix_unitaire_ht'];
            $tva_ligne = $ht_ligne * ($ligne['taux_tva'] / 100);
            
            $total_ht += $ht_ligne;
            $total_tva += $tva_ligne;
        }
        
        return [
            'ht' => round($total_ht, 0),
            'tva' => round($total_tva, 0),
            'ttc' => round($total_ht + $total_tva, 0)
        ];
    }
    
    private function ajouterLigneFactureFournisseur($facture_id, $ligne) {
        $sql = "INSERT INTO factures_fournisseurs_lignes 
                (facture_id, designation, quantite, prix_unitaire_ht, taux_tva, total_ht, total_tva, total_ttc)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $ht = $ligne['quantite'] * $ligne['prix_unitaire_ht'];
        $tva = $ht * ($ligne['taux_tva'] / 100);
        $ttc = $ht + $tva;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $facture_id,
            $ligne['designation'],
            $ligne['quantite'],
            $ligne['prix_unitaire_ht'],
            $ligne['taux_tva'],
            round($ht, 0),
            round($tva, 0),
            round($ttc, 0)
        ]);
    }
    
    private function creerAlerteEcheance($facture_id, $date_echeance) {
        // Créer alerte 7 jours avant échéance
        $date_alerte = date('Y-m-d', strtotime($date_echeance . ' -7 days'));
        
        $sql = "INSERT INTO alertes_echeances 
                (facture_id, date_echeance, date_alerte, statut)
                VALUES (?, ?, ?, 'active')";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$facture_id, $date_echeance, $date_alerte]);
    }
    
    // Méthodes utilitaires
    private function getCommandeDetails($commande_id) {
        $sql = "SELECT * FROM commandes WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$commande_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function genererPDFFacture($facture_id) {
        // TODO: Implémenter génération PDF avec TCPDF ou FPDF
        // Pour l'instant, retourner un chemin fictif
        return "factures/FC" . date('Y') . "-" . sprintf("%04d", $facture_id) . ".pdf";
    }
    
    private function updateFacturePDF($facture_id, $pdf_path) {
        $sql = "UPDATE factures_clients SET pdf_path = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$pdf_path, $facture_id]);
    }
    
    private function enregistrerMouvementVente($facture_id, $montant) {
        $sql = "INSERT INTO mouvements_tresorerie 
                (type, reference_id, reference_type, montant, description, date_mouvement)
                VALUES ('entree', ?, 'facture_client', ?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $facture_id,
            $montant,
            "Vente - Facture #" . $facture_id
        ]);
    }
    
    private function deduireStocks($commande_id) {
        // Récupérer les articles de la commande
        $sql = "SELECT article_id, quantite FROM commande_details WHERE commande_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$commande_id]);
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Déduire du stock pour chaque article
        foreach ($articles as $article) {
            $sql_update = "UPDATE stocks SET quantite = quantite - ? WHERE article_id = ?";
            $stmt_update = $this->db->prepare($sql_update);
            $stmt_update->execute([$article['quantite'], $article['article_id']]);
        }
    }
    
    // Méthodes pour l'API
    public function getFacturesClients($date_debut = null, $date_fin = null) {
        $sql = "SELECT fc.*, c.nom_client 
                FROM factures_clients fc 
                LEFT JOIN commandes cmd ON fc.commande_id = cmd.id
                LEFT JOIN clients c ON cmd.client_id = c.id
                WHERE 1=1";
        
        $params = [];
        
        if ($date_debut) {
            $sql .= " AND fc.date_facture >= ?";
            $params[] = $date_debut;
        }
        
        if ($date_fin) {
            $sql .= " AND fc.date_facture <= ?";
            $params[] = $date_fin;
        }
        
        $sql .= " ORDER BY fc.date_facture DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getFacturesFournisseurs() {
        $sql = "SELECT ff.*, f.nom as nom_fournisseur 
                FROM factures_fournisseurs ff 
                JOIN fournisseurs f ON ff.fournisseur_id = f.id
                ORDER BY ff.date_facture DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getEcheances() {
        $sql = "SELECT ff.*, f.nom as nom_fournisseur,
                DATEDIFF(ff.date_echeance, CURDATE()) as jours_restants
                FROM factures_fournisseurs ff 
                JOIN fournisseurs f ON ff.fournisseur_id = f.id
                WHERE ff.statut = 'en_attente'
                ORDER BY ff.date_echeance ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function marquerFacturePayee($facture_id) {
        try {
            $this->db->beginTransaction();
            
            // Marquer facture comme payée
            $sql = "UPDATE factures_fournisseurs 
                    SET statut = 'payee', date_paiement = NOW() 
                    WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$facture_id]);
            
            // Récupérer info facture pour mouvement trésorerie
            $sql_info = "SELECT montant_ttc, numero_facture FROM factures_fournisseurs WHERE id = ?";
            $stmt_info = $this->db->prepare($sql_info);
            $stmt_info->execute([$facture_id]);
            $facture = $stmt_info->fetch(PDO::FETCH_ASSOC);
            
            if ($facture) {
                // Enregistrer sortie trésorerie
                $sql_mouvement = "INSERT INTO mouvements_tresorerie 
                                 (type, reference_id, reference_type, montant, description, date_mouvement)
                                 VALUES ('sortie', ?, 'facture_fournisseur', ?, ?, NOW())";
                $stmt_mouvement = $this->db->prepare($sql_mouvement);
                $stmt_mouvement->execute([
                    $facture_id,
                    $facture['montant_ttc'],
                    "Paiement fournisseur - Facture " . $facture['numero_facture']
                ]);
            }
            
            $this->db->commit();
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function getFournisseurs() {
        $sql = "SELECT id, nom, email, telephone FROM fournisseurs ORDER BY nom";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>