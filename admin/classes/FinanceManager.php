<?php
class FinanceManager {
    private $db;
    private $facturationManager;
    private $coutManager;
    private $tresorenieManager;
    private $previsionManager;
    
    public function __construct($database) {
        $this->db = $database;
        $this->facturationManager = new FacturationManager($database);
        $this->coutManager = new CoutManager($database);
        $this->tresorerieManager = new TresorerieManager($database);
        $this->previsionManager = new PrevisionManager($database);
    }
    
    // Dashboard principal
    public function getDashboardData($date = null) {
        if (!$date) $date = date('Y-m-d');
        
        return [
            'caisse_quotidienne' => $this->tresorerieManager->getCaisseQuotidienne($date),
            'ventes_jour' => $this->getVentesJour($date),
            'objectifs' => $this->previsionManager->getObjectifsJour($date),
            'alertes' => $this->getAlertesActives(),
            'top_plats' => $this->getTopPlatsJour($date),
            'ecarts_previsions' => $this->previsionManager->getEcartsPrevisions($date)
        ];
    }
    
    private function getVentesJour($date) {
        $sql = "SELECT 
                    COUNT(*) as nb_commandes,
                    SUM(montant_ttc) as ca_total,
                    SUM(montant_ht) as ca_ht,
                    AVG(montant_ttc) as panier_moyen
                FROM factures_clients 
                WHERE DATE(date_facture) = ? AND statut = 'payee'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$date]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getAlertesActives() {
        $sql = "SELECT * FROM alertes_financieres 
                WHERE statut = 'active' 
                ORDER BY priorite DESC, date_creation DESC 
                LIMIT 10";
        
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getTopPlatsJour($date) {
        $sql = "SELECT p.nom, SUM(cd.quantite) as quantite, 
                       SUM(cd.prix * cd.quantite) as ca
                FROM commande_details cd
                JOIN plats p ON cd.plat_id = p.id
                JOIN commandes c ON cd.commande_id = c.id
                WHERE DATE(c.date_commande) = ?
                GROUP BY cd.plat_id
                ORDER BY quantite DESC
                LIMIT 5";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>