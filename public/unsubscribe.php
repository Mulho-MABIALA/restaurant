<?php
// unsubscribe.php - Système de désabonnement
require_once '../config.php';

$message = '';
$error = '';
$subscriber = null;

// Récupérer le token
$token = isset($_GET['token']) ? $_GET['token'] : '';

if ($token) {
    // Vérifier le token et récupérer l'abonné
    $subscriber = verifyUnsubscribeToken($conn, $token);
    
    if (!$subscriber) {
        $error = "Lien de désabonnement invalide ou expiré.";
    }
}

// Traitement du formulaire de désabonnement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $subscriber) {
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    $custom_reason = isset($_POST['custom_reason']) ? trim($_POST['custom_reason']) : '';
    
    // Effectuer le désabonnement
    $success = unsubscribeUser($conn, $subscriber['id'], $reason, $custom_reason);
    
    if ($success) {
        $message = "Vous avez été désabonné(e) avec succès de notre newsletter.";
    } else {
        $error = "Une erreur s'est produite lors du désabonnement.";
    }
}

/**
 * Vérifier le token de désabonnement
 */
function verifyUnsubscribeToken($conn, $token) {
    try {
        // Récupérer tous les abonnés actifs et vérifier le token
        $stmt = $conn->query("SELECT id, email, first_name, last_name FROM newsletter WHERE statut = 'actif'");
        $subscribers = $stmt->fetchAll();
        
        $secret = $_ENV['APP_SECRET'] ?? 'default_secret_change_me';
        
        foreach ($subscribers as $subscriber) {
            $expected_token = hash_hmac('sha256', $subscriber['id'], $secret);
            if (hash_equals($expected_token, $token)) {
                return $subscriber;
            }
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("Erreur verifyUnsubscribeToken: " . $e->getMessage());
        return null;
    }
}

/**
 * Désabonner un utilisateur
 */
function unsubscribeUser($conn, $subscriber_id, $reason, $custom_reason) {
    try {
        $conn->beginTransaction();
        
        // Mettre à jour le statut de l'abonné
        $stmt = $conn->prepare("
            UPDATE newsletter 
            SET statut = 'inactif', 
                unsubscribed_at = NOW(), 
                unsubscribe_reason = ?
            WHERE id = ?
        ");
        
        $final_reason = $reason;
        if ($reason === 'other' && $custom_reason) {
            $final_reason = 'Autre: ' . $custom_reason;
        }
        
        $stmt->execute([$final_reason, $subscriber_id]);
        
        // Optionnel: Enregistrer dans une table de logs de désabonnement
        try {
            $stmt = $conn->prepare("
                INSERT INTO newsletter_unsubscribe_log (subscriber_id, reason, unsubscribed_at, ip_address, user_agent)
                VALUES (?, ?, NOW(), ?, ?)
            ");
            $stmt->execute([
                $subscriber_id,
                $final_reason,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            // Table optionnelle, ne pas échouer si elle n'existe pas
        }
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Erreur unsubscribeUser: " . $e->getMessage());
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Désabonnement Newsletter</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <div class="bg-white rounded-lg shadow-lg p-8">
            
            <?php if ($message): ?>
                <!-- Message de succès -->
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                        <i class="fas fa-check text-green-600 text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Désabonnement confirmé</h2>
                    <p class="text-gray-600 mb-6"><?= htmlspecialchars($message) ?></p>
                    <p class="text-sm text-gray-500">Nous sommes désolés de vous voir partir. Vous pouvez vous réabonner à tout moment sur notre site.</p>
                </div>
                
            <?php elseif ($error): ?>
                <!-- Message d'erreur -->
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                        <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Erreur</h2>
                    <p class="text-gray-600 mb-6"><?= htmlspecialchars($error) ?></p>
                    <a href="/" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-home mr-2"></i>Retour à l'accueil
                    </a>
                </div>
                
            <?php elseif ($subscriber): ?>
                <!-- Formulaire de désabonnement -->
                <div class="text-center mb-6">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-yellow-100 mb-4">
                        <i class="fas fa-envelope-open text-yellow-600 text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Désabonnement</h2>
                    <p class="text-gray-600">
                        Bonjour <?= htmlspecialchars($subscriber['first_name'] ?: 'cher(e) abonné(e)') ?>,
                    </p>
                    <p class="text-gray-600">
                        Vous êtes sur le point de vous désabonner avec l'adresse : 
                        <strong><?= htmlspecialchars($subscriber['email']) ?></strong>
                    </p>
                </div>

                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-3">
                            Pourquoi souhaitez-vous vous désabonner ? (optionnel)
                        </label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="radio" name="reason" value="too_frequent" class="mr-2">
                                <span class="text-sm text-gray-700">Je reçois trop d'emails</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="reason" value="not_relevant" class="mr-2">
                                <span class="text-sm text-gray-700">Le contenu ne m'intéresse plus</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="reason" value="never_signed" class="mr-2">
                                <span class="text-sm text-gray-700">Je ne me souviens pas m'être inscrit(e)</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="reason" value="spam" class="mr-2">
                                <span class="text-sm text-gray-700">Je considère ceci comme du spam</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="reason" value="other" class="mr-2" onchange="toggleCustomReason()">
                                <span class="text-sm text-gray-700">Autre raison</span>
                            </label>
                        </div>
                        
                        <div id="customReasonDiv" class="mt-3 hidden">
                            <textarea name="custom_reason" 
                                      placeholder="Veuillez préciser..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                      rows="3"></textarea>
                        </div>
                    </div>

                    <!-- Options alternatives -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-blue-800 mb-2">Avant de partir...</h4>
                        <p class="text-sm text-blue-700 mb-3">
                            Saviez-vous que vous pouvez personnaliser vos préférences d'emails ?
                        </p>
                        <div class="space-y-2">
                            <a href="/newsletter/preferences.php?token=<?= htmlspecialchars($token) ?>" 
                               class="inline-flex items-center text-sm text-blue-600 hover:text-blue-700">
                                <i class="fas fa-cog mr-1"></i>Gérer mes préférences
                            </a>
                            <br>
                            <a href="/newsletter/frequency.php?token=<?= htmlspecialchars($token) ?>" 
                               class="inline-flex items-center text-sm text-blue-600 hover:text-blue-700">
                                <i class="fas fa-clock mr-1"></i>Changer la fréquence
                            </a>
                        </div>
                    </div>

                    <div class="flex space-x-4">
                        <button type="submit" 
                                class="flex-1 bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-lg transition-colors">
                            <i class="fas fa-unlink mr-2"></i>Confirmer le désabonnement
                        </button>
                        <a href="/" 
                           class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 py-2 px-4 rounded-lg transition-colors text-center">
                            <i class="fas fa-times mr-2"></i>Annuler
                        </a>
                    </div>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-xs text-gray-500">
                        Cette action est réversible. Vous pourrez vous réabonner à tout moment.
                    </p>
                </div>
                
            <?php else: ?>
                <!-- Aucun token fourni -->
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gray-100 mb-4">
                        <i class="fas fa-question text-gray-600 text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Lien manquant</h2>
                    <p class="text-gray-600 mb-6">
                        Pour vous désabonner, veuillez utiliser le lien fourni dans l'email que vous avez reçu.
                    </p>
                    <a href="/" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-home mr-2"></i>Retour à l'accueil
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6">
            <p class="text-xs text-gray-500">
                © 2024 Votre Site. Tous droits réservés.
            </p>
        </div>
    </div>

    <script>
    function toggleCustomReason() {
        const customDiv = document.getElementById('customReasonDiv');
        const otherRadio = document.querySelector('input[name="reason"][value="other"]');
        
        if (otherRadio.checked) {
            customDiv.classList.remove('hidden');
            customDiv.querySelector('textarea').focus();
        } else {
            customDiv.classList.add('hidden');
        }
    }

    // Gestion des autres boutons radio
    document.querySelectorAll('input[name="reason"]:not([value="other"])').forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked) {
                document.getElementById('customReasonDiv').classList.add('hidden');
            }
        });
    });

    // Confirmation avant désabonnement
    document.querySelector('form').addEventListener('submit', function(e) {
        if (!confirm('Êtes-vous sûr(e) de vouloir vous désabonner de notre newsletter ?')) {
            e.preventDefault();
        }
    });
    </script>
</body>
</html>



