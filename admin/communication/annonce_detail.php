// === annonce_detail.php - Détail d'une annonce ===
<?php
require_once '../../config.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    exit('Non autorisé');
}

$user_id = $_SESSION['admin_id'];
$annonce_id = $_GET['id'] ?? 0;
$section = $_GET['section'] ?? '';

if (!$annonce_id) {
    exit('ID manquant');
}

try {
    // Récupérer l'annonce complète
    $stmt = $conn->prepare("
        SELECT a.*, 
               e.nom as auteur_nom,
               c.nom as categorie_nom,
               c.couleur as categorie_couleur,
               c.icone as categorie_icone
        FROM annonces a
        LEFT JOIN employes e ON a.auteur_id = e.id
        LEFT JOIN annonce_categories c ON a.categorie_id = c.id
        WHERE a.id = ?
    ");
    $stmt->execute([$annonce_id]);
    $annonce = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$annonce) {
        exit('Annonce introuvable');
    }
    
    // Incrémenter le compteur de vues
    $conn->prepare("UPDATE annonces SET vues = vues + 1 WHERE id = ?")->execute([$annonce_id]);
    
    // Récupérer les réactions
    $reactions = $conn->prepare("
        SELECT ar.reaction_type, COUNT(*) as count, 
               GROUP_CONCAT(e.nom SEPARATOR ', ') as users
        FROM annonce_reactions ar
        JOIN employes e ON ar.user_id = e.id
        WHERE ar.annonce_id = ?
        GROUP BY ar.reaction_type
    ");
    $reactions->execute([$annonce_id]);
    $reactions_data = $reactions->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les commentaires
    $commentaires = $conn->prepare("
        SELECT ac.*, e.nom as auteur_nom
        FROM annonce_commentaires ac
        JOIN employes e ON ac.user_id = e.id
        WHERE ac.annonce_id = ?
        ORDER BY ac.created_at ASC
    ");
    $commentaires->execute([$annonce_id]);
    $commentaires_data = $commentaires->fetchAll(PDO::FETCH_ASSOC);
    
    // Marquer comme lu
    $conn->prepare("
        INSERT IGNORE INTO annonce_lectures (annonce_id, user_id) 
        VALUES (?, ?)
    ")->execute([$annonce_id, $user_id]);
    
    ?>
    
    <!-- Header -->
    <div class="flex justify-between items-start p-6 border-b">
        <div class="flex-1">
            <div class="flex items-center space-x-3 mb-3">
                <?php if ($annonce['categorie_nom']): ?>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium text-white" 
                          style="background-color: <?= $annonce['categorie_couleur'] ?>">
                        <i class="<?= $annonce['categorie_icone'] ?> mr-1"></i>
                        <?= htmlspecialchars($annonce['categorie_nom']) ?>
                    </span>
                <?php endif; ?>
                
                <span class="px-2 py-1 rounded-full text-xs font-medium
                    <?= $annonce['importance'] === 'haute' ? 'bg-red-100 text-red-800' : 
                        ($annonce['importance'] === 'moyenne' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') ?>">
                    <?= ucfirst($annonce['importance']) ?>
                </span>
                
                <?php if ($annonce['epinglee']): ?>
                    <i class="fas fa-thumbtack text-blue-600"></i>
                <?php endif; ?>
            </div>
            
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                <?= htmlspecialchars($annonce['titre']) ?>
            </h1>
            
            <div class="flex items-center text-sm text-gray-500 space-x-4">
                <span><i class="fas fa-user mr-1"></i><?= htmlspecialchars($annonce['auteur_nom'] ?? 'Système') ?></span>
                <span><i class="fas fa-clock mr-1"></i><?= date('d/m/Y H:i', strtotime($annonce['created_at'])) ?></span>
                <span><i class="fas fa-eye mr-1"></i><?= $annonce['vues'] ?> vues</span>
                <?php if ($annonce['date_expiration']): ?>
                    <span class="text-orange-600"><i class="fas fa-calendar-times mr-1"></i>Expire le <?= date('d/m/Y', strtotime($annonce['date_expiration'])) ?></span>
                <?php endif; ?>
            </div>
        </div>
        
        <button onclick="closeDetailModal()" class="text-gray-500 hover:text-gray-700 p-2">
            <i class="fas fa-times text-xl"></i>
        </button>
    </div>
    
    <!-- Contenu -->
    <div class="p-6">
        <div class="prose max-w-none text-gray-700 dark:text-gray-300 mb-6">
            <?= nl2br(htmlspecialchars($annonce['contenu'])) ?>
        </div>
        
        <!-- Pièce jointe -->
        <?php if ($annonce['piece_jointe']): ?>
            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-6">
                <h3 class="font-semibold mb-2 flex items-center">
                    <i class="fas fa-paperclip mr-2"></i>Pièce jointe
                </h3>
                <a href="<?= htmlspecialchars($annonce['piece_jointe']) ?>" 
                   download="<?= htmlspecialchars($annonce['nom_fichier']) ?>"
                   class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-download mr-2"></i>
                    <?= htmlspecialchars($annonce['nom_fichier']) ?>
                </a>
            </div>
        <?php endif; ?>
        
        <!-- Réactions -->
        <div class="border-t pt-6 mb-6">
            <h3 class="font-semibold mb-4">Réactions</h3>
            <div class="flex items-center space-x-4 mb-4">
                <button onclick="toggleReactionModal(<?= $annonce_id ?>, 'like')" 
                        class="flex items-center space-x-2 px-4 py-2 border rounded-lg hover:bg-gray-50">
                    <i class="fas fa-thumbs-up text-blue-600"></i>
                    <span>J'aime</span>
                </button>
                <button onclick="toggleReactionModal(<?= $annonce_id ?>, 'love')" 
                        class="flex items-center space-x-2 px-4 py-2 border rounded-lg hover:bg-gray-50">
                    <i class="fas fa-heart text-red-600"></i>
                    <span>J'adore</span>
                </button>
                <button onclick="toggleReactionModal(<?= $annonce_id ?>, 'important')" 
                        class="flex items-center space-x-2 px-4 py-2 border rounded-lg hover:bg-gray-50">
                    <i class="fas fa-star text-yellow-600"></i>
                    <span>Important</span>
                </button>
            </div>
            
            <?php if (!empty($reactions_data)): ?>
                <div class="space-y-2">
                    <?php foreach ($reactions_data as $reaction): ?>
                        <div class="text-sm text-gray-600">
                            <strong><?= $reaction['count'] ?></strong> 
                            <?= $reaction['reaction_type'] === 'like' ? '👍' : ($reaction['reaction_type'] === 'love' ? '❤️' : '⭐') ?>
                            <span class="ml-2"><?= htmlspecialchars($reaction['users']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Commentaires -->
        <div class="border-t pt-6">
            <h3 class="font-semibold mb-4">Commentaires (<?= count($commentaires_data) ?>)</h3>
            
            <!-- Formulaire nouveau commentaire -->
            <form onsubmit="addComment(event, <?= $annonce_id ?>)" class="mb-6">
                <div class="flex space-x-3">
                    <div class="flex-1">
                        <textarea name="commentaire" rows="3" placeholder="Ajouter un commentaire..." 
                                  class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                  required></textarea>
                    </div>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg h-fit">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </form>
            
            <!-- Liste des commentaires -->
            <div id="comments-list" class="space-y-4">
                <?php foreach ($commentaires_data as $comment): ?>
                    <div class="flex space-x-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white text-sm font-bold">
                            <?= strtoupper(substr($comment['auteur_nom'], 0, 2)) ?>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center space-x-2 mb-1">
                                <span class="font-semibold text-sm"><?= htmlspecialchars($comment['auteur_nom']) ?></span>
                                <span class="text-xs text-gray-500"><?= date('d/m/Y H:i', strtotime($comment['created_at'])) ?></span>
                            </div>
                            <p class="text-sm text-gray-700 dark:text-gray-300"><?= nl2br(htmlspecialchars($comment['commentaire'])) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <script>
    function addComment(event, announcementId) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        formData.append('annonce_id', announcementId);
        
        fetch('annonce_comment.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                form.reset();
                // Recharger les commentaires
                location.reload();
            } else {
                alert(data.message || 'Erreur lors de l\'ajout du commentaire');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur de connexion');
        });
    }
    
    function toggleReactionModal(announcementId, reactionType) {
        toggleReaction(announcementId, reactionType);
    }
    </script>
    
    <?php
    
} catch (PDOException $e) {
    error_log("Erreur annonce_detail: " . $e->getMessage());
    exit('Erreur lors du chargement');
}