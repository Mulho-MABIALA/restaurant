<?php
// admin_newsletter_compose.php
session_start();

require_once '../config.php';

// Rediriger si l'admin n'est pas connecté
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_campaign') {
        try {
            $name = trim($_POST['campaign_name'] ?? '');
            $subject = trim($_POST['email_subject'] ?? '');
            $content = $_POST['email_content'] ?? '';
            $template_id = !empty($_POST['template_id']) ? (int)$_POST['template_id'] : null;
            $segments = $_POST['segments'] ?? [];
            $schedule_type = $_POST['schedule_type'] ?? 'draft';
            $scheduled_at = null;
            
            if ($schedule_type === 'scheduled' && !empty($_POST['scheduled_datetime'])) {
                $scheduled_at = $_POST['scheduled_datetime'];
            }
            
            // Validation des champs obligatoires
            if (empty($name) || empty($subject) || empty($content)) {
                $_SESSION['error_message'] = "Tous les champs obligatoires doivent être remplis.";
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }
            
            // Compter les destinataires
            $recipients_query = "SELECT COUNT(*) FROM newsletter WHERE statut = 'actif'";
            $params = [];
            
            if (!empty($segments)) {
                $placeholders = str_repeat('?,', count($segments) - 1) . '?';
                $recipients_query .= " AND id IN (
                    SELECT subscriber_id FROM newsletter_subscriber_segments 
                    WHERE segment_id IN ($placeholders)
                )";
                $params = $segments;
            }
            
            $stmt = $conn->prepare($recipients_query);
            $stmt->execute($params);
            $total_recipients = $stmt->fetchColumn();
            
            // Insérer la campagne
            $status = $schedule_type === 'now' ? 'pending' : ($schedule_type === 'scheduled' ? 'scheduled' : 'draft');
            
            $insert_query = "INSERT INTO newsletter_campaigns 
                        (name, subject, content, template_id, status, scheduled_at, total_recipients) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($insert_query);
            $stmt->execute([$name, $subject, $content, $template_id, $status, $scheduled_at, $total_recipients]);
            $campaign_id = $conn->lastInsertId();
            
            // Ajouter à la file d'attente si envoi immédiat
            if ($status === 'pending') {
                $queue_query = "INSERT INTO newsletter_queue (campaign_id, subscriber_id, email)
                            SELECT ?, id, email FROM newsletter WHERE statut = 'actif'";
                
                if (!empty($segments)) {
                    $placeholders = str_repeat('?,', count($segments) - 1) . '?';
                    $queue_query .= " AND id IN (
                        SELECT subscriber_id FROM newsletter_subscriber_segments 
                        WHERE segment_id IN ($placeholders)
                    )";
                    $queue_params = array_merge([$campaign_id], $segments);
                } else {
                    $queue_params = [$campaign_id];
                }
                
                $stmt = $conn->prepare($queue_query);
                $stmt->execute($queue_params);
            }
            
            $_SESSION['success_message'] = "Campagne créée avec succès ! " . 
                ($status === 'pending' ? "L'envoi va commencer." : 
                ($status === 'scheduled' ? "Programmée pour le " . date('d/m/Y H:i', strtotime($scheduled_at)) : "Sauvegardée en brouillon."));
            
            header('Location: admin_newsletter_campaigns.php');
            exit;
            
        } catch (PDOException $e) {
            error_log("Erreur création campagne: " . $e->getMessage());
            $_SESSION['error_message'] = "Erreur lors de la création de la campagne.";
        }
    }
    
    if ($action === 'send_test') {
        $test_email = trim($_POST['test_email'] ?? '');
        $subject = trim($_POST['email_subject'] ?? '');
        $content = $_POST['email_content'] ?? '';
        
        if (filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
            // Envoyer l'email de test
            $headers = [
                'From: ' . ($_ENV['MAIL_FROM'] ?? 'noreply@votresite.com'),
                'Reply-To: ' . ($_ENV['MAIL_FROM'] ?? 'noreply@votresite.com'),
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'X-Mailer: Newsletter System'
            ];
            
            $full_content = str_replace(
                ['{{first_name}}', '{{email}}', '{{unsubscribe_link}}'],
                ['Test', $test_email, '#'],
                $content
            );
            
            if (mail($test_email, '[TEST] ' . $subject, $full_content, implode("\r\n", $headers))) {
                $_SESSION['success_message'] = "Email de test envoyé à $test_email";
            } else {
                $_SESSION['error_message'] = "Erreur lors de l'envoi du test";
            }
        } else {
            $_SESSION['error_message'] = "Adresse email de test invalide";
        }
    }
}

// Récupérer les templates
try {
    $templates = $conn->query("SELECT * FROM newsletter_templates WHERE is_active = 1 ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $templates = [];
}

// Récupérer les segments
try {
    $segments = $conn->query("SELECT * FROM newsletter_segments WHERE is_active = 1 ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $segments = [];
}

// Compter les abonnés par segment
$segment_counts = [];
foreach ($segments as $segment) {
    try {
        $count_query = "SELECT COUNT(*) FROM newsletter n 
                    JOIN newsletter_subscriber_segments nss ON n.id = nss.subscriber_id 
                    WHERE nss.segment_id = ? AND n.statut = 'actif'";
        $stmt = $conn->prepare($count_query);
        $stmt->execute([$segment['id']]);
        $segment_counts[$segment['id']] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $segment_counts[$segment['id']] = 0;
    }
}

// Compter tous les abonnés actifs
try {
    $total_active = $conn->query("SELECT COUNT(*) FROM newsletter WHERE statut = 'actif'")->fetchColumn();
} catch (PDOException $e) {
    $total_active = 0;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Composer une Newsletter</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Quill Editor (WYSIWYG gratuit) -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <style>
        /* Fix pour le sidebar */
        #sidebar {
            background: rgba(15, 23, 42, 0.95) !important;
            z-index: 50;
        }

        .template-card {
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .template-card:hover {
            border-color: #60a5fa;
        }
        .template-card.selected {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }

        /* Style pour Quill Editor */
        #editor-container {
            min-height: 400px;
            background: white;
        }
        .ql-container {
            font-size: 14px;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 min-h-screen">

    <div class="flex h-screen overflow-hidden">

        <?php include 'sidebar.php'; ?>

        <div class="flex-1 overflow-y-auto">

            <!-- Header -->
            <header class="bg-slate-900 shadow-lg sticky top-0 z-40">
                <div class="px-4 sm:px-6 lg:px-8 py-4">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-purple-600 rounded-xl flex items-center justify-center">
                                <i class="fas fa-edit text-white text-lg"></i>
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold text-white">Composer une Newsletter</h1>
                                <p class="text-gray-400 text-sm">Créez et envoyez des campagnes email</p>
                            </div>
                        </div>
                        <a href="admin_newsletter_campaigns.php" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors text-sm font-medium">
                            <i class="fas fa-arrow-left mr-2"></i>Retour aux campagnes
                        </a>
                    </div>
                </div>
            </header>

            <div class="p-8">

        <!-- Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-check-circle mr-2"></i>
                <?= htmlspecialchars($_SESSION['success_message']) ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($_SESSION['error_message']) ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <form method="POST" id="campaignForm">
            <input type="hidden" name="action" value="save_campaign">
            <input type="hidden" name="template_id" id="selectedTemplate" value="">
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Colonne principale -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <!-- Informations de base -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                            Informations de base
                        </h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nom de la campagne *</label>
                                <input type="text" name="campaign_name" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="Ex: Newsletter Janvier 2024">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Sujet de l'email *</label>
                                <input type="text" name="email_subject" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="Ex: Découvrez nos nouveautés du mois">
                            </div>
                        </div>
                    </div>

                    <!-- Templates -->
                    <?php if (!empty($templates)): ?>
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-layer-group text-blue-500 mr-2"></i>
                            Choisir un template
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="template-card" data-template="none">
                                <div class="text-center">
                                    <i class="fas fa-plus-circle text-2xl text-gray-400 mb-2"></i>
                                    <p class="font-medium">Template vierge</p>
                                    <p class="text-sm text-gray-500">Commencer de zéro</p>
                                </div>
                            </div>
                            
                            <?php foreach ($templates as $template): ?>
                            <div class="template-card" data-template="<?= $template['id'] ?>">
                                <div class="text-center">
                                    <i class="fas fa-file-alt text-2xl text-blue-500 mb-2"></i>
                                    <p class="font-medium"><?= htmlspecialchars($template['name']) ?></p>
                                    <p class="text-sm text-gray-500"><?= htmlspecialchars($template['description'] ?? '') ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Contenu de l'email -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-edit text-blue-500 mr-2"></i>
                            Contenu de l'email *
                        </h3>
                        
                        <div class="mb-4">
                            <div class="flex flex-wrap gap-2 mb-3">
                                <button type="button" onclick="insertVariable('{{first_name}}')"
                                        class="px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-sm">
                                    {{first_name}}
                                </button>
                                <button type="button" onclick="insertVariable('{{last_name}}')"
                                        class="px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-sm">
                                    {{last_name}}
                                </button>
                                <button type="button" onclick="insertVariable('{{email}}')"
                                        class="px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-sm">
                                    {{email}}
                                </button>
                                <button type="button" onclick="insertVariable('{{unsubscribe_link}}')"
                                        class="px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded text-sm">
                                    {{unsubscribe_link}}
                                </button>
                            </div>
                        </div>

                        <!-- Éditeur Quill -->
                        <div id="editor-container" class="border border-gray-300 rounded-md">
                            <h2>Bienvenue {{first_name}} !</h2>
                            <p>Votre contenu email ici...</p>
                            <p><a href="{{unsubscribe_link}}">Se désabonner</a></p>
                        </div>

                        <!-- Champ caché pour stocker le contenu HTML -->
                        <input type="hidden" id="emailContent" name="email_content">
                    </div>
                </div>

                <!-- Colonne latérale -->
                <div class="space-y-6">
                    <!-- Destinataires -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-users text-blue-500 mr-2"></i>
                            Destinataires
                        </h3>
                        
                        <div class="space-y-3">
                            <label class="flex items-center">
                                <input type="checkbox" name="segments[]" value="all" class="segment-checkbox mr-2" checked>
                                <span>Tous les abonnés actifs</span>
                                <span class="ml-auto text-sm text-gray-500">(<?= number_format($total_active) ?>)</span>
                            </label>
                            
                            <?php foreach ($segments as $segment): ?>
                            <label class="flex items-center">
                                <input type="checkbox" name="segments[]" value="<?= $segment['id'] ?>" class="segment-checkbox mr-2">
                                <span><?= htmlspecialchars($segment['name']) ?></span>
                                <span class="ml-auto text-sm text-gray-500">(<?= number_format($segment_counts[$segment['id']] ?? 0) ?>)</span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-4 p-3 bg-blue-50 rounded">
                            <p class="text-sm text-blue-800">
                                <strong>Total destinataires : <span id="totalRecipients"><?= number_format($total_active) ?></span></strong>
                            </p>
                        </div>
                    </div>

                    <!-- Planification -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-clock text-blue-500 mr-2"></i>
                            Planification
                        </h3>
                        
                        <div class="space-y-3">
                            <label class="flex items-center">
                                <input type="radio" name="schedule_type" value="draft" class="mr-2" checked>
                                <span>Sauvegarder en brouillon</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="radio" name="schedule_type" value="now" class="mr-2">
                                <span>Envoyer maintenant</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="radio" name="schedule_type" value="scheduled" class="mr-2">
                                <span>Programmer pour plus tard</span>
                            </label>
                            
                            <div id="scheduledOptions" class="hidden ml-6 mt-2">
                                <input type="datetime-local" name="scheduled_datetime" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                        </div>
                    </div>

                    <!-- Test -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-paper-plane text-blue-500 mr-2"></i>
                            Envoyer un test
                        </h3>
                        
                        <div class="space-y-3">
                            <input type="email" id="testEmail" placeholder="votre@email.com" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            
                            <button type="button" onclick="sendTest()" 
                                    class="w-full bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg transition-colors">
                                <i class="fas fa-paper-plane mr-2"></i>Envoyer le test
                            </button>
                        </div>
                    </div>

                    <!-- Prévisualisation -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            <i class="fas fa-eye text-blue-500 mr-2"></i>
                            Prévisualisation
                        </h3>
                        
                        <div class="space-y-2">
                            <button type="button" onclick="previewEmail('desktop')" 
                                    class="w-full bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                                <i class="fas fa-desktop mr-2"></i>Aperçu Desktop
                            </button>
                            <button type="button" onclick="previewEmail('mobile')" 
                                    class="w-full bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                                <i class="fas fa-mobile-alt mr-2"></i>Aperçu Mobile
                            </button>
                        </div>
                    </div>

                    <!-- Actions principales -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="space-y-3">
                            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                                <i class="fas fa-save mr-2"></i>Créer la campagne
                            </button>
                            
                            <button type="button" onclick="saveDraft()" 
                                    class="w-full bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded-lg transition-colors">
                                <i class="fas fa-file-alt mr-2"></i>Sauvegarder brouillon
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
            </div>
        </div>
    </div>

    <!-- Modal de prévisualisation -->
    <div id="previewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-4xl w-full max-h-screen overflow-hidden">
                <div class="flex items-center justify-between p-4 border-b">
                    <h3 class="text-lg font-semibold">Prévisualisation Email</h3>
                    <button onclick="closePreview()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="p-4 overflow-auto max-h-96">
                    <div id="previewContent" class="border rounded p-4">
                        <!-- Contenu de prévisualisation -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Initialisation de Quill Editor
    var quill = new Quill('#editor-container', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'color': [] }, { 'background': [] }],
                [{ 'align': [] }],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['link', 'image'],
                ['clean']
            ]
        }
    });

    // Gestion des templates
    document.querySelectorAll('.template-card').forEach(card => {
        card.addEventListener('click', function() {
            // Retirer la sélection précédente
            document.querySelectorAll('.template-card').forEach(c => c.classList.remove('selected'));
            
            // Ajouter la sélection
            this.classList.add('selected');
            
            // Mettre à jour le champ caché
            const templateId = this.dataset.template;
            document.getElementById('selectedTemplate').value = templateId === 'none' ? '' : templateId;
            
            // Charger le contenu du template si sélectionné
            if (templateId !== 'none') {
                loadTemplate(templateId);
            }
        });
    });

    // Gestion des segments
    document.querySelectorAll('.segment-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateRecipientCount);
    });

    // Gestion de la planification
    document.querySelectorAll('input[name="schedule_type"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const scheduledOptions = document.getElementById('scheduledOptions');
            if (this.value === 'scheduled') {
                scheduledOptions.classList.remove('hidden');
            } else {
                scheduledOptions.classList.add('hidden');
            }
        });
    });

    function loadTemplate(templateId) {
        fetch('ajax/get_template.php?id=' + templateId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    quill.root.innerHTML = data.content;
                }
            })
            .catch(error => console.error('Erreur:', error));
    }

    function updateRecipientCount() {
        const selectedSegments = Array.from(document.querySelectorAll('.segment-checkbox:checked'))
            .map(cb => cb.value);
        
        // Calculer le nombre de destinataires
        let total = 0;
        if (selectedSegments.includes('all')) {
            total = <?= $total_active ?>;
        } else {
            const segmentCounts = <?= json_encode($segment_counts) ?>;
            selectedSegments.forEach(segmentId => {
                total += segmentCounts[segmentId] || 0;
            });
        }
        
        document.getElementById('totalRecipients').textContent = total.toLocaleString();
    }

    function insertVariable(variable) {
        const range = quill.getSelection(true);
        quill.insertText(range.index, variable);
    }

    function sendTest() {
        const testEmail = document.getElementById('testEmail').value;
        if (!testEmail) {
            alert('Veuillez saisir une adresse email pour le test');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'send_test');
        formData.append('test_email', testEmail);
        formData.append('email_subject', document.querySelector('input[name="email_subject"]').value);
        formData.append('email_content', quill.root.innerHTML);

        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            // Recharger la page pour voir le message de confirmation
            location.reload();
        })
        .catch(error => {
            alert('Erreur lors de l\'envoi du test');
            console.error('Erreur:', error);
        });
    }

    function previewEmail(device) {
        const subject = document.querySelector('input[name="email_subject"]').value;
        const content = quill.root.innerHTML;
        
        const previewContent = document.getElementById('previewContent');
        const modal = document.getElementById('previewModal');
        
        // Simuler le contenu avec des variables remplacées
        const simulatedContent = content
            .replace(/\{\{first_name\}\}/g, 'John')
            .replace(/\{\{last_name\}\}/g, 'Doe')
            .replace(/\{\{email\}\}/g, 'john.doe@example.com')
            .replace(/\{\{unsubscribe_link\}\}/g, '#unsubscribe');
        
        previewContent.innerHTML = `
            <div class="email-preview ${device === 'mobile' ? 'max-w-sm mx-auto' : ''}">
                <div class="bg-gray-100 p-2 text-sm text-gray-600 border-b">
                    <strong>Sujet:</strong> ${subject}
                </div>
                <div class="p-4">
                    ${simulatedContent}
                </div>
            </div>
        `;
        
        modal.classList.remove('hidden');
    }

    function closePreview() {
        document.getElementById('previewModal').classList.add('hidden');
    }

    function saveDraft() {
        // Changer le type de planification à brouillon
        document.querySelector('input[name="schedule_type"][value="draft"]').checked = true;
        
        // Soumettre le formulaire
        document.getElementById('campaignForm').submit();
    }

    // Validation du formulaire
    document.getElementById('campaignForm').addEventListener('submit', function(e) {
        // Synchroniser le contenu avant validation
        document.getElementById('emailContent').value = quill.root.innerHTML;

        const campaignName = document.querySelector('input[name="campaign_name"]').value.trim();
        const emailSubject = document.querySelector('input[name="email_subject"]').value.trim();
        const emailContent = quill.root.innerHTML.trim();
        
        if (!campaignName || !emailSubject || !emailContent) {
            e.preventDefault();
            alert('Veuillez remplir tous les champs obligatoires');
            return;
        }
        
        const scheduleType = document.querySelector('input[name="schedule_type"]:checked').value;
        if (scheduleType === 'scheduled') {
            const scheduledDate = document.querySelector('input[name="scheduled_datetime"]').value;
            if (!scheduledDate) {
                e.preventDefault();
                alert('Veuillez sélectionner une date et heure pour la programmation');
                return;
            }
            
            // Vérifier que la date est dans le futur
            const selectedDate = new Date(scheduledDate);
            const now = new Date();
            if (selectedDate <= now) {
                e.preventDefault();
                alert('La date de programmation doit être dans le futur');
                return;
            }
        }
        
        if (scheduleType === 'now') {
            if (!confirm('Êtes-vous sûr de vouloir envoyer cette campagne maintenant ?')) {
                e.preventDefault();
                return;
            }
        }
    });

    // Auto-sauvegarde (optionnel)
    let autoSaveTimeout;
    function autoSave() {
        clearTimeout(autoSaveTimeout);
        autoSaveTimeout = setTimeout(() => {
            const formData = new FormData();
            formData.append('action', 'auto_save');
            formData.append('campaign_name', document.querySelector('input[name="campaign_name"]').value);
            formData.append('email_subject', document.querySelector('input[name="email_subject"]').value);
            formData.append('email_content', quill.root.innerHTML);
            
            fetch('ajax/auto_save.php', {
                method: 'POST',
                body: formData
            });
        }, 30000); // Auto-save toutes les 30 secondes
    }

    // Déclencher l'auto-save lors des changements
    document.querySelector('input[name="campaign_name"]').addEventListener('input', autoSave);
    document.querySelector('input[name="email_subject"]').addEventListener('input', autoSave);

    // Avertissement avant de quitter la page avec des modifications non sauvegardées
    let hasUnsavedChanges = false;
    
    document.querySelector('input[name="campaign_name"]').addEventListener('input', () => hasUnsavedChanges = true);
    document.querySelector('input[name="email_subject"]').addEventListener('input', () => hasUnsavedChanges = true);
    
    window.addEventListener('beforeunload', function(e) {
        if (hasUnsavedChanges) {
            e.preventDefault();
            e.returnValue = 'Vous avez des modifications non sauvegardées. Êtes-vous sûr de vouloir quitter ?';
        }
    });
    
    document.getElementById('campaignForm').addEventListener('submit', () => hasUnsavedChanges = false);
    </script>
</body>
</html>