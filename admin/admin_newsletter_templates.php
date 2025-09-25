<?php
// admin_newsletter_templates.php
session_start();
require_once 'includes/newsletter_functions.php';

// Vérification de l'authentification admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

// Traitement des actions
if ($_POST) {
    switch ($_POST['action']) {
        case 'create_template':
            $result = createTemplate($_POST['template_name'], $_POST['template_content'], $_POST['template_type']);
            $message = $result ? 'Template créé avec succès!' : 'Erreur lors de la création du template.';
            break;
        
        case 'update_template':
            $result = updateTemplate($_POST['template_id'], $_POST['template_name'], $_POST['template_content']);
            $message = $result ? 'Template mis à jour avec succès!' : 'Erreur lors de la mise à jour.';
            break;
        
        case 'delete_template':
            $result = deleteTemplate($_POST['template_id']);
            $message = $result ? 'Template supprimé avec succès!' : 'Erreur lors de la suppression.';
            break;
    }
}

$templates = getAllTemplates();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Templates Newsletter</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1><i class="fas fa-envelope"></i> Gestion des Templates Newsletter</h1>
            <nav class="admin-nav">
                <a href="admin_newsletter_templates.php" class="active"><i class="fas fa-template"></i> Templates</a>
                <a href="send_newsletter.php"><i class="fas fa-paper-plane"></i> Envoyer</a>
                <a href="upload_subscribers.php"><i class="fas fa-users"></i> Abonnés</a>
                <a href="includes/email_analytics.php"><i class="fas fa-chart-bar"></i> Statistiques</a>
            </nav>
        </header>

        <main class="admin-main">
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Création de nouveau template -->
            <section class="template-creator">
                <h2><i class="fas fa-plus-circle"></i> Créer un nouveau template</h2>
                <form method="POST" class="template-form">
                    <input type="hidden" name="action" value="create_template">
                    
                    <div class="form-group">
                        <label for="template_name">Nom du template:</label>
                        <input type="text" id="template_name" name="template_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="template_type">Type de template:</label>
                        <select id="template_type" name="template_type" required>
                            <option value="basic">Basique</option>
                            <option value="modern">Moderne</option>
                            <option value="corporate">Corporatif</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="template_content">Contenu HTML:</label>
                        <textarea id="template_content" name="template_content" rows="15" required></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Créer le template
                    </button>
                </form>
            </section>

            <!-- Liste des templates existants -->
            <section class="templates-list">
                <h2><i class="fas fa-list"></i> Templates existants</h2>
                
                <?php if (empty($templates)): ?>
                    <p class="no-data">Aucun template trouvé.</p>
                <?php else: ?>
                    <div class="templates-grid">
                        <?php foreach ($templates as $template): ?>
                            <div class="template-card">
                                <div class="template-header">
                                    <h3><?php echo htmlspecialchars($template['name']); ?></h3>
                                    <span class="template-type"><?php echo htmlspecialchars($template['type']); ?></span>
                                </div>
                                
                                <div class="template-preview">
                                    <iframe src="preview_email.php?template_id=<?php echo $template['id']; ?>" 
                                            frameborder="0" height="200"></iframe>
                                </div>
                                
                                <div class="template-actions">
                                    <button class="btn btn-secondary edit-template" 
                                            data-id="<?php echo $template['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($template['name']); ?>"
                                            data-content="<?php echo htmlspecialchars($template['content']); ?>">
                                        <i class="fas fa-edit"></i> Éditer
                                    </button>
                                    
                                    <form method="POST" style="display: inline-block;" 
                                          onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce template?');">
                                        <input type="hidden" name="action" value="delete_template">
                                        <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                                        <button type="submit" class="btn btn-danger">
                                            <i class="fas fa-trash"></i> Supprimer
                                        </button>
                                    </form>
                                    
                                    <a href="send_newsletter.php?template_id=<?php echo $template['id']; ?>" 
                                       class="btn btn-success">
                                        <i class="fas fa-paper-plane"></i> Utiliser
                                    </a>
                                </div>
                                
                                <div class="template-meta">
                                    <small>Créé le: <?php echo date('d/m/Y H:i', strtotime($template['created_at'])); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <!-- Modal d'édition -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Éditer le template</h3>
                <span class="close">&times;</span>
            </div>
            
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="update_template">
                <input type="hidden" name="template_id" id="edit_template_id">
                
                <div class="form-group">
                    <label for="edit_template_name">Nom du template:</label>
                    <input type="text" id="edit_template_name" name="template_name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_template_content">Contenu HTML:</label>
                    <textarea id="edit_template_content" name="template_content" rows="15" required></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Sauvegarder
                    </button>
                    <button type="button" class="btn btn-secondary close-modal">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/admin.js"></script>
</body>
</html>