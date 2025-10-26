<?php
// send_newsletter.php
session_start();
require_once 'includes/newsletter_functions.php';
require_once 'includes/email_queue.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';
$templates = getAllTemplates();
$subscribers = getAllActiveSubscribers();

if ($_POST && isset($_POST['send_newsletter'])) {
    $template_id = $_POST['template_id'];
    $subject = $_POST['subject'];
    $custom_content = $_POST['custom_content'] ?? '';
    $send_immediately = isset($_POST['send_immediately']);
    $scheduled_time = $_POST['scheduled_time'] ?? null;
    
    if ($send_immediately) {
        $result = sendNewsletterNow($template_id, $subject, $custom_content);
    } else {
        $result = scheduleNewsletter($template_id, $subject, $custom_content, $scheduled_time);
    }
    
    $message = $result ? 'Newsletter envoyée/programmée avec succès!' : 'Erreur lors de l\'envoi.';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envoyer Newsletter</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1><i class="fas fa-paper-plane"></i> Envoyer Newsletter</h1>
            <nav class="admin-nav">
                <a href="admin_newsletter_templates.php"><i class="fas fa-template"></i> Templates</a>
                <a href="send_newsletter.php" class="active"><i class="fas fa-paper-plane"></i> Envoyer</a>
                <a href="upload_subscribers.php"><i class="fas fa-users"></i> Abonnés</a>
                <a href="includes/email_analytics.php"><i class="fas fa-chart-bar"></i> Statistiques</a>
            </nav>
        </header>

        <main class="admin-main">
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form method="POST" class="newsletter-form">
                <div class="form-section">
                    <h3>Sélection du template</h3>
                    <select name="template_id" required onchange="previewTemplate(this.value)">
                        <option value="">Choisir un template</option>
                        <?php foreach ($templates as $template): ?>
                            <option value="<?php echo $template['id']; ?>"
                                    <?php echo (isset($_GET['template_id']) && $_GET['template_id'] == $template['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($template['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-section">
                    <h3>Contenu de la newsletter</h3>
                    <input type="text" name="subject" placeholder="Sujet de l'email" required>
                    <textarea name="custom_content" rows="10" 
                              placeholder="Contenu personnalisé (optionnel)"></textarea>
                </div>

                <div class="form-section">
                    <h3>Options d'envoi</h3>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="send_option" value="now" checked 
                                   onchange="toggleSchedule(false)">
                            Envoyer maintenant
                        </label>
                        <label>
                            <input type="radio" name="send_option" value="schedule" 
                                   onchange="toggleSchedule(true)">
                            Programmer l'envoi
                        </label>
                    </div>
                    
                    <div id="schedule_section" style="display: none;">
                        <input type="datetime-local" name="scheduled_time">
                    </div>
                </div>

                <div class="form-section">
                    <h3>Destinataires</h3>
                    <p><strong><?php echo count($subscribers); ?></strong> abonnés actifs</p>
                </div>

                <button type="submit" name="send_newsletter" class="btn btn-primary btn-large">
                    <i class="fas fa-paper-plane"></i> Envoyer la newsletter
                </button>
            </form>

            <div id="template_preview" class="template-preview-section">
                <h3>Aperçu du template</h3>
                <iframe id="preview_frame" src="" frameborder="0" height="400"></iframe>
            </div>
        </main>
    </div>

    <script src="assets/js/newsletter.js"></script>
</body>
</html>