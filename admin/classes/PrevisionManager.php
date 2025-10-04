<?php
class PrevisionManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    // Prévision CA basée sur historique
    public function prevoir_ca_mensuel($annee, $mois) {
        // Récupérer historique des 12 derniers mois
        $historique = $this->getHistoriqueCA($annee, $mois);
        
        // Calcul tendance
        $tendance = $this->calculerTendance($historique);
        
        // Ajustements saisonniers
        $ajustement_saisonnier = $this->getAjustementSaisonnier($mois);
        
        // Facteurs externes (météo, événements)
        $facteurs_externes = $this->getFacteursExternes($annee, $mois);
        
        // Calcul prévision finale
        $ca_prevu = $tendance * $ajustement_saisonnier * $facteurs_externes;
        
        // Estimer charges
        $charges_prevues = $this->estimerCharges($ca_prevu);
        
        $benefice_prevu = $ca_prevu - $charges_prevues;
        
        // Sauvegarder prévision
        $this->sauvegarderPrevision([
            'periode_type' => 'mensuel',
            'date_debut' => "$annee-$mois-01",
            'date_fin' => date('Y-m-t', strtotime("$annee-$mois-01")),
            'ca_prevu' => $ca_prevu,
            'charges_prevues' => $charges_prevues,
            'benefice_prevu' => $benefice_prevu,
            'facteurs_ajustement' => json_encode($facteurs_externes)
        ]);
        
        return [
            'ca_prevu' => $ca_prevu,
            'charges_prevues' => $charges_prevues,
            'benefice_prevu' => $benefice_prevu,
            'confiance' => $this->calculerNiveauConfiance($historique)
        ];
    }
    
    private function getHistoriqueCA($annee, $mois) {
        $sql = "SELECT 
                    YEAR(date_facture) as annee,
                    MONTH(date_facture) as mois,
                    SUM(montant_ttc) as ca_mensuel
                FROM factures_clients 
                WHERE date_facture >= DATE_SUB(?, INTERVAL 12 MONTH)
                AND statut = 'payee'
                GROUP BY YEAR(date_facture), MONTH(date_facture)
                ORDER BY annee, mois";
        
        $date_ref = "$annee-$mois-01";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$date_ref]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function calculerTendance($historique) {
        if (count($historique) < 3) return 0;
        
        // Régression linéaire simple
        $n = count($historique);
        $sum_x = 0; $sum_y = 0; $sum_xy = 0; $sum_x2 = 0;
        
        foreach ($historique as $i => $data) {
            $x = $i + 1;
            $y = $data['ca_mensuel'];
            $sum_x += $x;
            $sum_y += $y;
            $sum_xy += $x * $y;
            $sum_x2 += $x * $x;
        }
        
        $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_x2 - $sum_x * $sum_x);
        $intercept = ($sum_y - $slope * $sum_x) / $n;
        
        // Prévision pour le mois suivant
        return $slope * ($n + 1) + $intercept;
    }
    
    private function getAjustementSaisonnier($mois) {
        // Coefficients saisonniers pour un restaurant (à ajuster selon votre activité)
        $coefficients = [
            1 => 0.85,  // Janvier - Calme post-fêtes
            2 => 0.90,  // Février
            3 => 1.00,  // Mars
            4 => 1.05,  // Avril
            5 => 1.10,  // Mai
            6 => 1.15,  // Juin
            7 => 1.20,  // Juillet - Haute saison
            8 => 1.15,  // Août
            9 => 1.05,  // Septembre
            10 => 1.00, // Octobre
            11 => 1.10, // Novembre
            12 => 1.25  // Décembre - Fêtes
        ];
        
        return $coefficients[$mois] ?? 1.00;
    }
    
    private function getFacteursExternes($annee, $mois) {
        // Facteurs météo, événements locaux, concurrence
        // À développer avec APIs météo et calendrier événements
        return 1.00; // Neutre pour l'instant
    }
    
    // Suggestions d'optimisation automatiques
    public function genererSuggestionsOptimisation() {
        $suggestions = [];
        
        // Analyser plats peu rentables
        $plats_peu_rentables = $this->getplatsPeuRentables();
        foreach ($plats_peu_rentables as $plat) {
            $suggestions[] = [
                'type' => 'marge_faible',
                'priorite' => 'warning',
                'titre' => "Marge faible sur {$plat['nom']}",
                'message' => "Le plat '{$plat['nom']}' a une marge de seulement {$plat['marge_pourcentage']}%. Considérez augmenter le prix ou réduire les coûts.",
                'actions' => [
                    'Augmenter le prix de ' . round($plat['prix'] * 0.1, 2) . '€',
                    'Négocier avec les fournisseurs',
                    'Modifier la recette'
                ]
            ];
        }
        
        // Analyser stocks en surconsommation
        $stocks_critiques = $this->getStocksCritiques();
        foreach ($stocks_critiques as $stock) {
            $suggestions[] = [
                'type' => 'stock_critique',
                'priorite' => 'critical',
                'titre' => "Stock critique: {$stock['nom']}",
                'message' => "Le stock de {$stock['nom']} sera épuisé dans {$stock['jours_restants']} jours.",
                'actions' => ['Commander immédiatement', 'Ajuster portions', 'Menu alternatif']
            ];
        }
        
        // Analyser tendances horaires
        $heures_creuses = $this->getHeuresCreuses();
        if ($heures_creuses) {
            $suggestions[] = [
                'type' => 'optimisation_horaire',
                'priorite' => 'info',
                'titre' => 'Optimisation des heures creuses',
                'message' => 'Heures creuses détectées entre ' . $heures_creuses['debut'] . 'h et ' . $heures_creuses['fin'] . 'h.',
                'actions' => ['Happy hour', 'Menu découverte', 'Promotion ciblée']
            ];
        }
        
        return $suggestions;
    }
}
?>