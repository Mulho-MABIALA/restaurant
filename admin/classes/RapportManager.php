<?php
class RapportManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    // Rapport de ventes détaillé
    public function rapportVentesDetaille($date_debut, $date_fin) {
        return [
            'resume' => $this->getResumeVentes($date_debut, $date_fin),
            'par_jour' => $this->getVentesParJour($date_debut, $date_fin),
            'par_heure' => $this->getVentesParHeure($date_debut, $date_fin),
            'par_plat' => $this->getVentesParPlat($date_debut, $date_fin),
            'par_mode_commande' => $this->getVentesParModeCommande($date_debut, $date_fin),
            'evolution' => $this->getEvolutionVentes($date_debut, $date_fin)
        ];
    }
    
    private function getResumeVentes($date_debut, $date_fin) {
        $sql = "SELECT 
                    COUNT(*) as nb_commandes,
                    SUM(montant_ttc) as ca_total,
                    SUM(montant_ht) as ca_ht,
                    AVG(montant_ttc) as panier_moyen,
                    MIN(montant_ttc) as panier_min,
                    MAX(montant_ttc) as panier_max
                FROM factures_clients 
                WHERE date_facture BETWEEN ? AND ? 
                AND statut = 'payee'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$date_debut, $date_fin]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getVentesParJour($date_debut, $date_fin) {
        $sql = "SELECT 
                    DATE(date_facture) as jour,
                    COUNT(*) as nb_commandes,
                    SUM(montant_ttc) as ca_jour,
                    AVG(montant_ttc) as panier_moyen
                FROM factures_clients 
                WHERE date_facture BETWEEN ? AND ? 
                AND statut = 'payee'
                GROUP BY DATE(date_facture)
                ORDER BY jour";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$date_debut, $date_fin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getVentesParHeure($date_debut, $date_fin) {
        $sql = "SELECT 
                    HOUR(date_creation) as heure,
                    COUNT(*) as nb_commandes,
                    SUM(montant_ttc) as ca_heure,
                    AVG(montant_ttc) as panier_moyen
                FROM factures_clients 
                WHERE date_facture BETWEEN ? AND ? 
                AND statut = 'payee'
                GROUP BY HOUR(date_creation)
                ORDER BY heure";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$date_debut, $date_fin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Top 10 plats les plus vendus
    public function getTop10Plats($date_debut, $date_fin) {
        $sql = "SELECT 
                    p.nom,
                    p.prix,
                    SUM(cd.quantite) as quantite_vendue,
                    SUM(cd.prix * cd.quantite) as ca_plat,
                    cp.marge_pourcentage,
                    (cp.benefice * SUM(cd.quantite)) as benefice_total
                FROM commande_details cd
                JOIN plats p ON cd.plat_id = p.id
                JOIN commandes c ON cd.commande_id = c.id
                LEFT JOIN couts_plats cp ON p.id = cp.plat_id AND cp.periode_fin IS NULL
                WHERE c.date_commande BETWEEN ? AND ?
                GROUP BY cd.plat_id
                ORDER BY quantite_vendue DESC
                LIMIT 10";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$date_debut, $date_fin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>