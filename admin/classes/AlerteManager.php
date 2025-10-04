<?php
class AlerteManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    // Vérification automatique des alertes
    public function verifierAlertesAutomatiques() {
        $this->verifierEcheancesFactures();
        $this->verifierStocksCritiques();
        $this->verifierMargesFaibles();
        $this->verifierObjectifsRates();
    }
    
    private function verifierEcheancesFactures() {
        $sql = "SELECT f.*, fou.nom as nom_fournisseur
                FROM factures_fournisseurs f
                JOIN fournisseurs fou ON f.fournisseur_id = fou.id
                WHERE f.statut = 'en_attente' 
                AND f.date_echeance <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
        
        $factures = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($factures as $facture) {
            $jours_restants = floor((strtotime($facture['date_echeance']) - time()) / 86400);
            
            $priorite = $jours_restants <= 0 ? 'critical' : ($jours_restants <= 3 ? 'warning' : 'info');
            
            $this->creerAlerte([
                'type_alerte' => 'echeance_facture',
                'priorite' => $priorite,
                'titre' => "Échéance facture {$facture['numero_facture']}",
                'message' => "Facture {$facture['numero_facture']} de {$facture['nom_fournisseur']} échue dans {$jours_restants} jours ({$facture['montant_ttc']}€)",
                'reference_id' => $facture['id'],
                'reference_table' => 'factures_fournisseurs',
                'date_echeance' => $facture['date_echeance'],
                'montant' => $facture['montant_ttc']
            ]);
        }
    }
    
    private function creerAlerte($data) {
        // Vérifier si l'alerte existe déjà
        $sql = "SELECT id FROM alertes_financieres 
                WHERE type_alerte = ? AND reference_id = ? AND statut = 'active'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$data['type_alerte'], $data['reference_id']]);
        
        if ($stmt->fetchColumn()) return; // Alerte déjà existante
        
        $sql = "INSERT INTO alertes_financieres 
                (type_alerte, priorite, titre, message, reference_id, reference_table, 
                 date_echeance, montant, pourcentage)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['type_alerte'],
            $data['priorite'],
            $data['titre'],
            $data['message'],
            $data['reference_id'] ?? null,
            $data['reference_table'] ?? null,
            $data['date_echeance'] ?? null,
            $data['montant'] ?? null,
            $data['pourcentage'] ?? null
        ]);
    }
}
?>