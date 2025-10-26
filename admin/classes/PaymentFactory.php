<?php
/**
 * PaymentFactory
 *
 * Factory pour instancier le bon provider de paiement selon la méthode choisie
 */

require_once __DIR__ . '/PaymentGateway.php';
require_once __DIR__ . '/PaymentProviders/OrangeMoneyProvider.php';
require_once __DIR__ . '/PaymentProviders/WaveProvider.php';
require_once __DIR__ . '/PaymentProviders/PaydunyaProvider.php';

class PaymentFactory {
    /**
     * Créer une instance du provider de paiement
     *
     * @param string $provider Nom du provider ('orange_money', 'wave', 'paydunya', etc.)
     * @param PDO $conn Connexion à la base de données
     * @param array $config Configuration optionnelle
     * @return PaymentGateway Instance du provider
     * @throws Exception Si le provider n'est pas supporté
     */
    public static function create($provider, $conn, $config = []) {
        // Normaliser le nom du provider
        $provider = strtolower($provider);

        // Vérifier si le provider est actif
        if (!self::isProviderActive($provider, $conn)) {
            throw new Exception("Le mode de paiement '$provider' n'est pas disponible actuellement.");
        }

        // Instancier le bon provider
        switch ($provider) {
            case 'orange_money':
                return new OrangeMoneyProvider($conn, $config);

            case 'wave':
                return new WaveProvider($conn, $config);

            case 'paydunya':
                return new PaydunyaProvider($conn, $config);

            case 'cash':
                // Mode paiement sur place - pas besoin de provider
                return null;

            default:
                throw new Exception("Provider de paiement non supporté: $provider");
        }
    }

    /**
     * Vérifier si un provider est actif
     *
     * @param string $provider Nom du provider
     * @param PDO $conn Connexion BDD
     * @return bool
     */
    private static function isProviderActive($provider, $conn) {
        // Cash est toujours actif
        if ($provider === 'cash') {
            return true;
        }

        $stmt = $conn->prepare("
            SELECT is_active
            FROM payment_methods
            WHERE provider = ? AND is_active = 1
        ");
        $stmt->execute([$provider]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Obtenir tous les providers actifs
     *
     * @param PDO $conn Connexion BDD
     * @return array Liste des providers actifs
     */
    public static function getActiveProviders($conn) {
        try {
            $stmt = $conn->prepare("
                SELECT *
                FROM payment_methods
                WHERE is_active = 1
                ORDER BY display_order ASC
            ");
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Si la table n'existe pas, retourner les providers par défaut
            error_log("Payment methods table not found, using defaults: " . $e->getMessage());

            return [
                [
                    'provider' => 'cash',
                    'name' => 'Paiement sur place',
                    'description' => 'Espèces ou carte au restaurant',
                    'is_active' => 1,
                    'min_amount' => 0,
                    'max_amount' => null
                ],
                [
                    'provider' => 'wave',
                    'name' => 'Wave',
                    'description' => 'Paiement instantané avec Wave (0% de frais)',
                    'is_active' => 1,
                    'min_amount' => 100,
                    'max_amount' => 1000000
                ],
                [
                    'provider' => 'orange_money',
                    'name' => 'Orange Money',
                    'description' => 'Paiement sécurisé via Orange Money',
                    'is_active' => 1,
                    'min_amount' => 100,
                    'max_amount' => 1000000
                ]
            ];
        }
    }

    /**
     * Vérifier si un montant est valide pour un provider
     *
     * @param string $provider Nom du provider
     * @param float $amount Montant
     * @param PDO $conn Connexion BDD
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateAmount($provider, $amount, $conn) {
        $stmt = $conn->prepare("
            SELECT min_amount, max_amount, name
            FROM payment_methods
            WHERE provider = ? AND is_active = 1
        ");
        $stmt->execute([$provider]);
        $method = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$method) {
            return [
                'valid' => false,
                'error' => "Méthode de paiement non disponible"
            ];
        }

        // Vérifier montant minimum
        if ($method['min_amount'] && $amount < $method['min_amount']) {
            return [
                'valid' => false,
                'error' => "Montant minimum pour {$method['name']}: " . number_format($method['min_amount'], 0, ',', ' ') . " FCFA"
            ];
        }

        // Vérifier montant maximum
        if ($method['max_amount'] && $amount > $method['max_amount']) {
            return [
                'valid' => false,
                'error' => "Montant maximum pour {$method['name']}: " . number_format($method['max_amount'], 0, ',', ' ') . " FCFA"
            ];
        }

        return [
            'valid' => true,
            'error' => null
        ];
    }

    /**
     * Calculer les frais pour un provider et un montant
     *
     * @param string $provider Nom du provider
     * @param float $amount Montant
     * @param PDO $conn Connexion BDD
     * @return float Montant des frais
     */
    public static function calculateFees($provider, $amount, $conn) {
        $stmt = $conn->prepare("
            SELECT fee_type, fee_value
            FROM payment_methods
            WHERE provider = ? AND is_active = 1
        ");
        $stmt->execute([$provider]);
        $method = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$method) {
            return 0;
        }

        switch ($method['fee_type']) {
            case 'fixed':
                return (float) $method['fee_value'];

            case 'percentage':
                return $amount * ((float) $method['fee_value'] / 100);

            default:
                return 0;
        }
    }

    /**
     * Obtenir le montant total (montant + frais)
     *
     * @param string $provider Nom du provider
     * @param float $amount Montant de base
     * @param PDO $conn Connexion BDD
     * @return array ['amount' => float, 'fees' => float, 'total' => float]
     */
    public static function getTotalAmount($provider, $amount, $conn) {
        $fees = self::calculateFees($provider, $amount, $conn);

        return [
            'amount' => $amount,
            'fees' => $fees,
            'total' => $amount + $fees
        ];
    }
}
