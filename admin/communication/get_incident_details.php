<?php
require_once '../../config.php';
session_start();

// Fonction pour afficher le nom de l'employé
function getEmployeDisplay($incident) {
    if (!empty($incident['employe_nom']) && trim($incident['employe_nom']) !== '') {
        return trim($incident['employe_nom']);
    }
    
    if (!empty($incident['email'])) {
        return explode('@', $incident['email'])[0];
    }
    
    return 'Utilisateur ' . ($incident['employe_id'] ?? 'inconnu');
}

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['error' => 'ID incident invalide']);
    exit;
}

$incident_id = (int)$_GET['id'];

try {
    // Vérifier les colonnes et tables disponibles
    $available_columns = $conn->query("SHOW COLUMNS FROM incidents")->fetchAll(PDO::FETCH_COLUMN);
    $available_tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    // Construire la requête SELECT selon les colonnes disponibles
    $select_fields = "i.id, i.employe_id, i.titre, i.description, i.gravite, i.created_at";
    
    // Ajouter les colonnes optionnelles si elles existent
    if (in_array('fichier_url', $available_columns)) {
        $select_fields .= ", i.fichier_url";
    }
    if (in_array('statut', $available_columns)) {
        $select_fields .= ", i.statut";
    }
    if (in_array('categorie', $available_columns)) {
        $select_fields .= ", i.categorie";
    }
    if (in_array('departement', $available_columns)) {
        $select_fields .= ", i.departement";
    }
    if (in_array('tags', $available_columns)) {
        $select_fields .= ", i.tags";
    }
    if (in_array('assigne_a', $available_columns)) {
        $select_fields .= ", i.assigne_a";
    }
    if (in_array('updated_at', $available_columns)) {
        $select_fields .= ", i.updated_at";
    }
    
    // Construire les jointures
    $joins = "FROM incidents i";
    
    // Jointure avec employes si la table existe
    if (in_array('employes', $available_tables)) {
        $joins .= " LEFT JOIN employes e ON i.employe_id = e.id";
        $select_fields .= ", TRIM(CONCAT(COALESCE(e.prenom, ''), ' ', COALESCE(e.nom, ''))) as employe_nom";
        $select_fields .= ", e.email";
        
        // Jointure pour l'assigné si la colonne existe
        if (in_array('assigne_a', $available_columns)) {
            $joins .= " LEFT JOIN employes e2 ON i.assigne_a = e2.id";
            $select_fields .= ", e2.nom as assigne_nom";
        }
    } else {
        // Si pas de table employes, utiliser l'ID
        $select_fields .= ", CONCAT('Utilisateur ', i.employe_id) as employe_nom";
    }
    
    // Récupérer l'incident
    $query = "SELECT $select_fields $joins WHERE i.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$incident_id]);
    $incident = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$incident) {
        echo json_encode(['error' => 'Incident non trouvé']);
        exit;
    }
    
    // Récupérer l'historique si la table existe
    $historique = [];
    if (in_array('incident_historique', $available_tables)) {
        $hist_query = "SELECT h.*, e.nom as employe_nom 
                       FROM incident_historique h 
                       LEFT JOIN employes e ON h.employe_id = e.id 
                       WHERE h.incident_id = ? 
                       ORDER BY h.created_at DESC";
        try {
            $hist_stmt = $conn->prepare($hist_query);
            $hist_stmt->execute([$incident_id]);
            $historique = $hist_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Si erreur avec la jointure, essayer sans
            $hist_query_simple = "SELECT * FROM incident_historique WHERE incident_id = ? ORDER BY created_at DESC";
            $hist_stmt = $conn->prepare($hist_query_simple);
            $hist_stmt->execute([$incident_id]);
            $historique = $hist_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Ajouter les noms d'employé manuellement
            foreach ($historique as &$hist) {
                if (in_array('employes', $available_tables)) {
                    try {
                        $emp_stmt = $conn->prepare("SELECT nom FROM employes WHERE id = ?");
                        $emp_stmt->execute([$hist['employe_id']]);
                        $emp = $emp_stmt->fetch();
                        $hist['employe_nom'] = $emp ? $emp['nom'] : 'Utilisateur ' . $hist['employe_id'];
                    } catch (Exception $e2) {
                        $hist['employe_nom'] = 'Utilisateur ' . $hist['employe_id'];
                    }
                } else {
                    $hist['employe_nom'] = 'Utilisateur ' . $hist['employe_id'];
                }
            }
        }
    }
    
    // Générer le HTML des détails
    ob_start();
    ?>
    <div class="space-y-6">
        <!-- En-tête -->
        <div class="border-b pb-4">
            <div class="flex items-center gap-3 mb-2">
                <h4 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($incident['titre']) ?></h4>
                
                <?php if (isset($incident['statut']) && $incident['statut']): ?>
                <span class="px-3 py-1 rounded-full text-sm font-medium <?php
                    echo match ($incident['statut']) {
                        'nouveau' => 'bg-blue-100 text-blue-800',
                        'en_cours' => 'bg-orange-100 text-orange-800',
                        'resolu' => 'bg-green-100 text-green-800',
                        'ferme' => 'bg-gray-100 text-gray-800',
                        default => 'bg-gray-100 text-gray-800',
                    };
                ?>">
                    <?= ucfirst(str_replace('_', ' ', $incident['statut'])) ?>
                </span>
                <?php endif; ?>
                
                <?php if (isset($incident['categorie']) && $incident['categorie']): ?>
                    <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded text-sm">
                        <?= ucfirst($incident['categorie']) ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
                <div>
                    <strong>Signalé par:</strong> <?= htmlspecialchars(getEmployeDisplay($incident)) ?>
                </div>
                <div>
                    <strong>Date:</strong> <?= date('d/m/Y à H:i', strtotime($incident['created_at'])) ?>
                </div>
                <div>
                    <strong>Gravité:</strong> 
                    <span class="<?php
                        echo match ($incident['gravite']) {
                            'élevée' => 'text-red-600 font-semibold',
                            'moyenne' => 'text-orange-600 font-semibold',
                            default => 'text-green-600 font-semibold',
                        };
                    ?>">
                        <?= ucfirst($incident['gravite']) ?>
                    </span>
                </div>
                <?php if (isset($incident['departement']) && $incident['departement']): ?>
                <div>
                    <strong>Département:</strong> <?= htmlspecialchars($incident['departement']) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($incident['assigne_nom'])): ?>
                <div>
                    <strong>Assigné à:</strong> <?= htmlspecialchars($incident['assigne_nom']) ?>
                </div>
                <?php endif; ?>
                <?php if (isset($incident['updated_at']) && $incident['updated_at']): ?>
                <div>
                    <strong>Dernière mise à jour:</strong> <?= date('d/m/Y à H:i', strtotime($incident['updated_at'])) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Description -->
        <div>
            <h5 class="text-lg font-semibold text-gray-900 mb-3">Description</h5>
            <div class="bg-gray-50 p-4 rounded-lg">
                <p class="text-gray-700 whitespace-pre-wrap"><?= htmlspecialchars($incident['description']) ?></p>
            </div>
        </div>
        
        <!-- Tags -->
        <?php if (isset($incident['tags']) && $incident['tags']): ?>
        <div>
            <h5 class="text-lg font-semibold text-gray-900 mb-3">Tags</h5>
            <div class="flex flex-wrap gap-2">
                <?php foreach (explode(',', $incident['tags']) as $tag): ?>
                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm"><?= htmlspecialchars(trim($tag)) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Fichier joint -->
        <?php if (isset($incident['fichier_url']) && $incident['fichier_url']): ?>
        <div>
            <h5 class="text-lg font-semibold text-gray-900 mb-3">Fichier joint</h5>
            <a href="<?= htmlspecialchars($incident['fichier_url']) ?>" target="_blank" 
               class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-800 bg-blue-50 px-4 py-2 rounded-lg hover:bg-blue-100 transition">
                📎 Télécharger le fichier
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Historique -->
        <?php if (!empty($historique)): ?>
        <div>
            <h5 class="text-lg font-semibold text-gray-900 mb-3">Historique</h5>
            <div class="space-y-3 max-h-60 overflow-y-auto">
                <?php foreach ($historique as $hist): ?>
                    <div class="bg-gray-50 p-3 rounded-lg border-l-4 border-blue-500">
                        <div class="flex justify-between items-start mb-1">
                            <span class="font-medium text-gray-900">
                                <?= htmlspecialchars($hist['employe_nom'] ?? 'Utilisateur ' . $hist['employe_id']) ?>
                            </span>
                            <span class="text-xs text-gray-500">
                                <?= date('d/m/Y H:i', strtotime($hist['created_at'])) ?>
                            </span>
                        </div>
                        <p class="text-sm text-gray-600 mb-1">
                            <strong><?= ucfirst(str_replace('_', ' ', $hist['action'])) ?></strong>
                        </p>
                        <?php if ($hist['commentaire']): ?>
                            <p class="text-sm text-gray-700"><?= htmlspecialchars($hist['commentaire']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Formulaire d'ajout de commentaire -->
        <div class="border-t pt-4">
            <h5 class="text-lg font-semibold text-gray-900 mb-3">Ajouter un commentaire</h5>
            <form method="post" class="space-y-3">
                <input type="hidden" name="action" value="add_comment">
                <input type="hidden" name="incident_id" value="<?= $incident['id'] ?>">
                <textarea name="commentaire" rows="3" placeholder="Votre commentaire..." required
                          class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                    Ajouter le commentaire
                </button>
            </form>
        </div>
    </div>
    
    <?php
    $html = ob_get_clean();
    
    echo json_encode(['html' => $html]);
    
} catch (Exception $e) {
    error_log("Erreur get_incident_details: " . $e->getMessage());
    echo json_encode(['error' => 'Erreur lors de la récupération des détails: ' . $e->getMessage()]);
}