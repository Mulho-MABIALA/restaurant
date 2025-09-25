<?php
// preview_email.php
require_once 'includes/newsletter_functions.php';

$template_id = $_GET['template_id'] ?? 0;
$template = getTemplateById($template_id);

if (!$template) {
    echo '<p>Template non trouvé</p>';
    exit();
}

// Variables de remplacement pour l'aperçu
$replacements = [
    '{SUBSCRIBER_NAME}' => 'John Doe',
    '{UNSUBSCRIBE_LINK}' => '#',
    '{COMPANY_NAME}' => 'Votre Entreprise',
    '{CURRENT_DATE}' => date('d/m/Y')
];

$content = str_replace(array_keys($replacements), array_values($replacements), $template['content']);
echo $content;
?>