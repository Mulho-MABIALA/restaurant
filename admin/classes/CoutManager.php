<?php
class CoutManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    // Calcul automatique coût de revient
    public function calculerCoutPlat($plat_id) {
        // Coût ingrédients
        $cout_ingredients = $this->calculerCoutIngredients($plat_id);
        
        // Coût main d'œuvre (estimation)
        $cout_main_oeuvre = $this->calculerCoutMainOeuvre($plat_id);
        
        // Charges fixes réparties
        $cout_charges = $this->calculerCoutCharges();
        
        $cout_total = $cout_ingredients + $cout_main_oeuvre + $cout_charges;
        
        // Récupérer prix de vente
        $prix_vente = $this->getPrixVentePlat($plat_id);
        
        $benefice = $prix_vente - $cout_total;
        $marge_pourcentage = ($benefice / $prix_vente) * 100;
        
        // Sauvegarder
        $this->sauvegarderCoutPlat($plat_id, [
            'cout_ingredients' => $cout_ingredients,
            'cout_main_oeuvre' => $cout_main_oeuvre,
            'cout_charges' => $cout_charges,
            'cout_total' => $cout_total,
            'prix_vente' => $prix_vente,
            'benefice' => $benefice,
            'marge_pourcentage' => $marge_pourcentage
        ]);
        
        return [
            'cout_total' => $cout_total,
            'benefice' => $benefice,
            'marge_pourcentage' => $marge_pourcentage
        ];
    }
    
    private function calculerCoutIngredients($plat_id) {
        // Utiliser votre table ingredients_plats
        $sql = "SELECT SUM(ip.quantite * s.prix_unitaire) as cout_total
                FROM ingredients_plats ip
                JOIN stocks s ON ip.stock_id = s.id
                WHERE ip.plat_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$plat_id]);
        return $stmt->fetchColumn() ?: 0;
    }
    
    // Analyse rentabilité globale
    public function analyseRentabiliteGlobale($periode_debut, $periode_fin) {
        $sql = "SELECT 
                    p.nom,
                    cp.cout_total,
                    cp.marge_pourcentage,
                    sv.quantite_vendue,
                    sv.ca_total,
                    (cp.benefice * sv.quantite_vendue) as benefice_total
                FROM plats p
                JOIN couts_plats cp ON p.id = cp.plat_id
                JOIN (
                    SELECT plat_id, 
                           SUM(quantite_vendue) as quantite_vendue,
                           SUM(ca_total) as ca_total
                    FROM stats_ventes 
                    WHERE date_stat BETWEEN ? AND ?
                    GROUP BY plat_id
                ) sv ON p.id = sv.plat_id
                WHERE cp.periode_fin IS NULL
                ORDER BY benefice_total DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$periode_debut, $periode_fin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>