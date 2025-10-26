<?php
/**
 * API Endpoint: Enregistrer un token FCM
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../admin/classes/Notifications/PushNotificationService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

try {
    // Récupérer les données POST
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['fcm_token']) || empty($input['fcm_token'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Token FCM requis']);
        exit;
    }

    // Initialiser le service
    $pushService = new PushNotificationService($conn);

    // Enregistrer le token
    $result = $pushService->registerToken([
        'fcm_token' => $input['fcm_token'],
        'user_id' => $input['user_id'] ?? null,
        'email' => $input['email'] ?? null,
        'phone' => $input['phone'] ?? null,
        'device_type' => $input['device_type'] ?? 'web',
        'device_name' => $input['device_name'] ?? null,
        'browser' => $input['browser'] ?? null,
        'os' => $input['os'] ?? null
    ]);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Token enregistré avec succès',
            'token_id' => $result['token_id']
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Erreur lors de l\'enregistrement'
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
