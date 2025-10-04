<?php
// classes/FinanceHelper.php

class FinanceHelper {
    
    // Formatage monétaire
    public static function formatMontant($montant, $devise = '€') {
        return number_format($montant, 2, ',', ' ') . ' ' . $devise;
    }
    
    // Calcul pourcentage d'évolution
    public static function calculPourcentageEvolution($ancien, $nouveau) {
        if ($ancien == 0) return $nouveau > 0 ? 100 : 0;
        return round((($nouveau - $ancien) / $ancien) * 100, 2);
    }
    
    // Génération couleur selon pourcentage
    public static function getCouleurEvolution($pourcentage) {
        if ($pourcentage > 10) return '#16a085'; // Vert
        if ($pourcentage > 0) return '#f39c12';  // Orange
        if ($pourcentage > -10) return '#e67e22'; // Orange foncé
        return '#e74c3c'; // Rouge
    }
    
    // Validation SIRET
    public static function validerSIRET($siret) {
        $siret = preg_replace('/[^0-9]/', '', $siret);
        if (strlen($siret) !== 14) return false;
        
        // Algorithme de validation SIRET
        $sum = 0;
        for ($i = 0; $i < 14; $i++) {
            $digit = intval($siret[$i]);
            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) $digit -= 9;
            }
            $sum += $digit;
        }
        
        return $sum % 10 === 0;
    }
    
    // Formatage de date française
    public static function formatDateFr($date) {
        return date('d/m/Y', strtotime($date));
    }
    
    // Formatage de date et heure française
    public static function formatDateHeureFr($datetime) {
        return date('d/m/Y H:i', strtotime($datetime));
    }
    
    // Conversion nombre en lettres (pour factures)
    public static function nombreEnLettres($nombre) {
        $unites = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf'];
        $dizaines = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante-dix', 'quatre-vingt', 'quatre-vingt-dix'];
        
        // Implémentation simplifiée pour les montants courants
        $entier = floor($nombre);
        $decimales = round(($nombre - $entier) * 100);
        
        if ($entier < 10) {
            $resultat = $unites[$entier];
        } elseif ($entier < 100) {
            $dizaine = floor($entier / 10);
            $unite = $entier % 10;
            $resultat = $dizaines[$dizaine];
            if ($unite > 0) {
                $resultat .= ($dizaine == 1 ? '' : '-') . $unites[$unite];
            }
        } else {
            $resultat = $entier; // Pour les montants élevés, on garde les chiffres
        }
        
        return $resultat . ' euros' . ($decimales > 0 ? ' et ' . $decimales . ' centimes' : '');
    }
    
    // Génération de couleur aléatoire pour graphiques
    public static function genererCouleur($index = null) {
        $couleurs = [
            '#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6',
            '#EC4899', '#06B6D4', '#84CC16', '#F97316', '#6366F1'
        ];
        
        if ($index !== null && isset($couleurs[$index])) {
            return $couleurs[$index];
        }
        
        return $couleurs[array_rand($couleurs)];
    }
    
    // Validation email
    public static function validerEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    // Génération de référence unique
    public static function genererReference($prefix = 'REF', $length = 8) {
        $caracteres = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $reference = $prefix . '-';
        
        for ($i = 0; $i < $length; $i++) {
            $reference .= $caracteres[rand(0, strlen($caracteres) - 1)];
        }
        
        return $reference;
    }
    
    // Calcul de la TVA
    public static function calculerTVA($montant_ht, $taux = 20) {
        return round($montant_ht * ($taux / 100), 2);
    }
    
    // Calcul TTC
    public static function calculerTTC($montant_ht, $taux_tva = 20) {
        $tva = self::calculerTVA($montant_ht, $taux_tva);
        return $montant_ht + $tva;
    }
    
    // Arrondi commercial
    public static function arrondiCommercial($montant, $precision = 2) {
        return round($montant, $precision);
    }
}
?>