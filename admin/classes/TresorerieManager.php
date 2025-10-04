<?php
class TresorerieManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    // Ouverture caisse quotidienne
    public function ouvrirCaisse($date, $fonds_ouverture, $employe_id) {
        $sql = "INSERT INTO caisses_quotidiennes 
                (date_caisse, fonds_ouverture, employe_ouverture_id, heure_ouverture)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                fonds_ouverture = VALUES(fonds_ouverture),
                employe_ouverture_id = VALUES(employe_ouverture_id),
                heure_ouverture = VALUES(heure_ouverture)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$date, $fonds_ouverture, $employe_id]);
    }
    
    // Fermeture caisse quotidienne
    public function fermerCaisse($date, $especes_reel, $employe_id) {
        // Calculer totaux théoriques
        $totaux = $this->calculerTotauxJour($date);
        
        $ecart = $especes_reel - $totaux['especes_theorique'];
        
        $sql = "UPDATE caisses_quotidiennes SET
                total_especes_theorique = ?,
                total_especes_reel = ?,
                ecart = ?,
                total_cartes = ?,
                total_ventes = ?,
                employe_fermeture_id = ?,
                heure_fermeture = NOW(),
                statut = 'fermee'
                WHERE date_caisse = ?";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            $totaux['especes_theorique'],
            $especes_reel,
            $ecart,
            $totaux['cartes'],
            $totaux['total_ventes'],
            $employe_id,
            $date
        ]);
        
        // Créer alerte si écart important
        if (abs($ecart) > 50) {
            $this->creerAlerteEcartCaisse($date, $ecart);
        }
        
        return $result;
    }
    
    private function calculerTotauxJour($date) {
        $sql = "SELECT 
                    SUM(CASE WHEN mode_paiement = 'especes' THEN montant_ttc ELSE 0 END) as especes,
                    SUM(CASE WHEN mode_paiement = 'carte' THEN montant_ttc ELSE 0 END) as cartes,
                    SUM(montant_ttc) as total_ventes
                FROM factures_clients 
                WHERE DATE(date_facture) = ? AND statut = 'payee'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$date]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'especes_theorique' => $result['especes'] ?: 0,
            'cartes' => $result['cartes'] ?: 0,
            'total_ventes' => $result['total_ventes'] ?: 0
        ];
    }
}
?>