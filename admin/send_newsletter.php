<?php
/**
 * Envoi d'email Newsletter - Version Simple
 */
session_start();
require_once '../config.php';

// Charger PHPMailer si disponible
$phpmailer_available = false;
if (file_exists('../vendor/autoload.php')) {
    require '../vendor/autoload.php';
    $phpmailer_available = class_exists('PHPMailer\PHPMailer\PHPMailer');
}

// Vérifier si admin connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Infos admin
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_email = $_SESSION['admin_email'] ?? '';

$message = '';
$error = '';
$preview = false;

// Récupérer les paramètres SMTP depuis settings
$smtp_settings = [];
try {
    $stmt = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp_%'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $smtp_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (\Exception $e) {
    // Valeurs par défaut
}

// Envoi de l'email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    try {
        $subject = trim($_POST['subject'] ?? '');
        $message_body = trim($_POST['message'] ?? '');
        $send_to = $_POST['send_to'] ?? 'all';

        if (empty($subject) || empty($message_body)) {
            throw new \Exception('Le sujet et le message sont obligatoires');
        }

        // Récupérer les destinataires
        $recipients = [];
        if ($send_to === 'all') {
            $stmt = $conn->query("SELECT email, first_name, last_name FROM newsletter WHERE statut = 'actif'");
        } elseif ($send_to === 'active') {
            $stmt = $conn->query("SELECT email, first_name, last_name FROM newsletter WHERE statut = 'actif'");
        } else {
            // Source spécifique
            $stmt = $conn->prepare("SELECT email, first_name, last_name FROM newsletter WHERE statut = 'actif' AND source = ?");
            $stmt->execute([$send_to]);
        }

        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($recipients)) {
            throw new \Exception('Aucun destinataire trouvé');
        }

        $sent = 0;
        $failed = 0;
        $errors_list = [];

        foreach ($recipients as $recipient) {
            try {
                // Préparer le message personnalisé
                $personalized_message = str_replace(
                    ['{first_name}', '{last_name}', '{email}'],
                    [$recipient['first_name'], $recipient['last_name'], $recipient['email']],
                    $message_body
                );

                if ($phpmailer_available && !empty($smtp_settings['smtp_host'])) {
                    // Utiliser PHPMailer
                    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

                    $mail->isSMTP();
                    $mail->Host = $smtp_settings['smtp_host'] ?? 'localhost';
                    $mail->SMTPAuth = !empty($smtp_settings['smtp_auth']);
                    $mail->Username = $smtp_settings['smtp_username'] ?? '';
                    $mail->Password = $smtp_settings['smtp_password'] ?? '';
                    $mail->SMTPSecure = $smtp_settings['smtp_secure'] ?? 'tls';
                    $mail->Port = $smtp_settings['smtp_port'] ?? 587;
                    $mail->CharSet = 'UTF-8';

                    $mail->setFrom(
                        $smtp_settings['smtp_from_email'] ?? 'noreply@restaurant.com',
                        $smtp_settings['smtp_from_name'] ?? 'Restaurant'
                    );

                    $mail->addAddress($recipient['email'], trim($recipient['first_name'] . ' ' . $recipient['last_name']));

                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body = nl2br($personalized_message);
                    $mail->AltBody = strip_tags($personalized_message);

                    $mail->send();
                } else {
                    // Utiliser mail() PHP natif
                    $headers = "From: Restaurant <noreply@restaurant.com>\r\n";
                    $headers .= "Reply-To: noreply@restaurant.com\r\n";
                    $headers .= "MIME-Version: 1.0\r\n";
                    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

                    if (!mail($recipient['email'], $subject, nl2br($personalized_message), $headers)) {
                        throw new \Exception('Échec mail()');
                    }
                }

                $sent++;

                // Petit délai pour éviter le spam
                usleep(100000); // 0.1 seconde

            } catch (\Exception $e) {
                $failed++;
                $errors_list[] = $recipient['email'] . ': ' . $e->getMessage();
            }
        }

        $message = "✅ Email envoyé avec succès à $sent destinataire(s)";
        if ($failed > 0) {
            $message .= " | ❌ $failed échec(s)";
        }

    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}

// Aperçu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preview'])) {
    $preview = true;
    $preview_subject = $_POST['subject'] ?? '';
    $preview_message = $_POST['message'] ?? '';
}

// Statistiques
$stats = [
    'total_actif' => $conn->query("SELECT COUNT(*) FROM newsletter WHERE statut = 'actif'")->fetchColumn(),
    'total_inactif' => $conn->query("SELECT COUNT(*) FROM newsletter WHERE statut = 'inactif'")->fetchColumn(),
];

// Sources disponibles
$sources = $conn->query("SELECT DISTINCT source, COUNT(*) as count FROM newsletter WHERE statut = 'actif' AND source IS NOT NULL GROUP BY source ORDER BY source")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envoyer une Newsletter - Restaurant Mulho</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .glass-morphism {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900">

    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="glass-morphism shadow-lg border-b border-white/10">
                <div class="px-4 sm:px-6 lg:px-8 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <a href="admin_newsletter.php" class="text-gray-300 hover:text-white transition">
                                <i class="fas fa-arrow-left text-xl"></i>
                            </a>
                            <div>
                                <h1 class="text-2xl lg:text-3xl font-bold text-white">
                                    <i class="fas fa-paper-plane mr-2"></i>Envoyer une Newsletter
                                </h1>
                                <p class="text-gray-400 text-sm mt-1">Composez et envoyez un email à vos abonnés</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-400">Connecté en tant que</p>
                            <p class="text-white font-semibold"><?= htmlspecialchars($admin_name) ?></p>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                <div class="max-w-5xl mx-auto">

                    <!-- Messages -->
                    <?php if ($message): ?>
                    <div class="mb-6 p-4 bg-green-500/20 border border-green-500/50 text-green-100 rounded-lg backdrop-blur-sm animate-fade-in">
                        <p><?= htmlspecialchars($message) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                    <div class="mb-6 p-4 bg-red-500/20 border border-red-500/50 text-red-100 rounded-lg backdrop-blur-sm animate-fade-in">
                        <p><?= htmlspecialchars($error) ?></p>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($errors_list) && !empty($errors_list)): ?>
                    <div class="mb-6 p-4 bg-yellow-500/20 border border-yellow-500/50 text-yellow-100 rounded-lg backdrop-blur-sm animate-fade-in">
                        <p class="font-semibold mb-2">Détails des erreurs:</p>
                        <ul class="text-sm list-disc list-inside space-y-1">
                            <?php foreach (array_slice($errors_list, 0, 5) as $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                            <?php if (count($errors_list) > 5): ?>
                                <li>... et <?= count($errors_list) - 5 ?> autre(s)</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <!-- Statistiques rapides -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="glass-morphism rounded-lg p-6 hover:shadow-xl transition">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-gray-400 text-sm">Abonnés actifs</p>
                                    <p class="text-4xl font-bold text-green-400 mt-2"><?= number_format($stats['total_actif']) ?></p>
                                    <p class="text-xs text-gray-500 mt-1">Recevront l'email</p>
                                </div>
                                <div class="bg-green-500/20 rounded-full p-4">
                                    <i class="fas fa-check-circle text-green-400 text-3xl"></i>
                                </div>
                            </div>
                        </div>

                        <div class="glass-morphism rounded-lg p-6 hover:shadow-xl transition">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-gray-400 text-sm">Abonnés inactifs</p>
                                    <p class="text-4xl font-bold text-gray-400 mt-2"><?= number_format($stats['total_inactif']) ?></p>
                                    <p class="text-xs text-gray-500 mt-1">Ne recevront pas l'email</p>
                                </div>
                                <div class="bg-gray-500/20 rounded-full p-4">
                                    <i class="fas fa-times-circle text-gray-400 text-3xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Formulaire d'envoi -->
                    <div class="glass-morphism rounded-lg p-6 mb-6">
                        <form method="POST" id="emailForm">
                            <div class="space-y-6">

                                <!-- Destinataires -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-300 mb-2">
                                        <i class="fas fa-users mr-2"></i>Envoyer à
                                    </label>
                                    <select name="send_to" required class="w-full px-4 py-3 bg-slate-800 border border-gray-600 text-white rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="all">Tous les abonnés actifs (<?= $stats['total_actif'] ?>)</option>
                                        <?php foreach ($sources as $src): ?>
                                            <option value="<?= htmlspecialchars($src['source']) ?>">
                                                Source: <?= htmlspecialchars($src['source']) ?> (<?= $src['count'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">Seuls les abonnés actifs recevront l'email</p>
                                </div>

                                <!-- Sujet -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-300 mb-2">
                                        <i class="fas fa-heading mr-2"></i>Sujet de l'email
                                    </label>
                                    <input type="text" name="subject" required
                                           placeholder="Ex: Nouvelle offre spéciale du restaurant"
                                           value="<?= isset($preview_subject) ? htmlspecialchars($preview_subject) : '' ?>"
                                           class="w-full px-4 py-3 bg-slate-800 border border-gray-600 text-white rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent placeholder-gray-500">
                                </div>

                                <!-- Message -->
                                <div>
                                    <label class="block text-sm font-semibold text-gray-300 mb-2">
                                        <i class="fas fa-envelope mr-2"></i>Message
                                    </label>
                                    <textarea name="message" required rows="10"
                                              placeholder="Votre message ici...&#10;&#10;Vous pouvez utiliser ces variables :&#10;{first_name} - Prénom du destinataire&#10;{last_name} - Nom du destinataire&#10;{email} - Email du destinataire"
                                              class="w-full px-4 py-3 bg-slate-800 border border-gray-600 text-white rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono text-sm placeholder-gray-500"><?= isset($preview_message) ? htmlspecialchars($preview_message) : '' ?></textarea>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Utilisez {first_name}, {last_name}, {email} pour personnaliser
                                    </p>
                                    <p id="charCount" class="text-xs text-gray-500 mt-1 text-right">0 caractères</p>
                                </div>

                                <!-- Boutons -->
                                <div class="flex flex-col sm:flex-row gap-3">
                                    <button type="submit" name="preview"
                                            class="flex-1 px-6 py-3 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition font-semibold">
                                        <i class="fas fa-eye mr-2"></i>Aperçu
                                    </button>
                                    <button type="submit" name="send_email"
                                            onclick="return confirm('Êtes-vous sûr de vouloir envoyer cet email à ' + document.querySelector('[name=send_to] option:checked').text + ' ?')"
                                            class="flex-1 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition font-semibold shadow-lg">
                                        <i class="fas fa-paper-plane mr-2"></i>Envoyer maintenant
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Aperçu -->
                    <?php if ($preview && isset($preview_subject) && isset($preview_message)): ?>
                    <div class="glass-morphism rounded-lg p-6 mb-6 animate-fade-in">
                        <h2 class="text-xl font-bold text-white mb-4">
                            <i class="fas fa-eye mr-2"></i>Aperçu de l'email
                        </h2>

                        <div class="border-2 border-gray-600 rounded-lg p-6 bg-slate-800">
                            <div class="mb-4 pb-4 border-b border-gray-600">
                                <p class="text-sm text-gray-400">Sujet:</p>
                                <p class="text-lg font-semibold text-white"><?= htmlspecialchars($preview_subject) ?></p>
                            </div>

                            <div class="prose prose-invert max-w-none text-gray-300">
                                <?php
                                // Exemple de personnalisation
                                $example = str_replace(
                                    ['{first_name}', '{last_name}', '{email}'],
                                    ['<strong class="text-blue-400">Jean</strong>', '<strong class="text-blue-400">Dupont</strong>', '<strong class="text-blue-400">jean.dupont@example.com</strong>'],
                                    $preview_message
                                );
                                echo nl2br($example);
                                ?>
                            </div>
                        </div>

                        <p class="text-xs text-gray-500 mt-4">
                            <i class="fas fa-info-circle mr-1"></i>
                            Cet aperçu montre comment l'email apparaîtra avec des exemples de données
                        </p>
                    </div>
                    <?php endif; ?>

                    <!-- Aide -->
                    <div class="bg-blue-500/20 border border-blue-500/50 p-4 rounded-lg backdrop-blur-sm">
                        <h3 class="font-semibold text-blue-300 mb-2">
                            <i class="fas fa-info-circle mr-2"></i>Conseils pour un bon email
                        </h3>
                        <ul class="text-sm text-blue-200 space-y-1">
                            <li>• Utilisez un sujet clair et accrocheur</li>
                            <li>• Personnalisez avec {first_name} pour plus d'impact</li>
                            <li>• Gardez le message concis et lisible</li>
                            <li>• Testez avec l'aperçu avant d'envoyer</li>
                            <li>• N'envoyez pas trop souvent (1-2 fois par semaine max)</li>
                        </ul>
                    </div>

                </div>
            </main>

            <!-- Footer -->
            <footer class="glass-morphism border-t border-white/10 py-4 px-6">
                <div class="flex flex-col sm:flex-row justify-between items-center text-sm text-gray-400">
                    <p>&copy; <?= date('Y') ?> Restaurant Mulho. Tous droits réservés.</p>
                    <p>Envoi d'emails sécurisé</p>
                </div>
            </footer>
        </div>
    </div>

    <script>
        // Compteur de caractères
        const textarea = document.querySelector('textarea[name="message"]');
        const charCount = document.getElementById('charCount');

        if (textarea && charCount) {
            function updateCounter() {
                charCount.textContent = textarea.value.length + ' caractères';
            }

            textarea.addEventListener('input', updateCounter);
            updateCounter();
        }

        // Animation de fade-in
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .animate-fade-in {
                animation: fadeIn 0.3s ease-out;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
