<?php
require_once '../../config.php'; // ⚠️ ton config.php doit créer $conn = new PDO(...)

$message = "";

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'ajouter':
                $titre = $_POST['titre'];
                $contenu = $_POST['contenu'];
                $type = $_POST['type_annonce'];
                $couleur = $_POST['couleur'];
                $date_debut = !empty($_POST['date_debut']) ? $_POST['date_debut'] : null;
                $date_fin = !empty($_POST['date_fin']) ? $_POST['date_fin'] : null;

                $query = "INSERT INTO annonce_public (titre, contenu, type_annonce, couleur, date_debut, date_fin) 
                          VALUES (:titre, :contenu, :type, :couleur, :date_debut, :date_fin)";
                $stmt = $conn->prepare($query);
                $success = $stmt->execute([
                    ':titre' => $titre,
                    ':contenu' => $contenu,
                    ':type' => $type,
                    ':couleur' => $couleur,
                    ':date_debut' => $date_debut,
                    ':date_fin' => $date_fin
                ]);

                $message = $success 
                    ? "<div class='alert alert-success'>Annonce ajoutée avec succès!</div>"
                    : "<div class='alert alert-danger'>Erreur lors de l'ajout</div>";
                break;

            case 'modifier':
                $id = intval($_POST['id']);
                $titre = $_POST['titre'];
                $contenu = $_POST['contenu'];
                $type = $_POST['type_annonce'];
                $couleur = $_POST['couleur'];
                $statut = $_POST['statut'];
                $date_debut = !empty($_POST['date_debut']) ? $_POST['date_debut'] : null;
                $date_fin = !empty($_POST['date_fin']) ? $_POST['date_fin'] : null;

                $query = "UPDATE annonce_public 
                          SET titre = :titre, contenu = :contenu, type_annonce = :type, 
                              couleur = :couleur, statut = :statut, 
                              date_debut = :date_debut, date_fin = :date_fin
                          WHERE id = :id";
                $stmt = $conn->prepare($query);
                $success = $stmt->execute([
                    ':titre' => $titre,
                    ':contenu' => $contenu,
                    ':type' => $type,
                    ':couleur' => $couleur,
                    ':statut' => $statut,
                    ':date_debut' => $date_debut,
                    ':date_fin' => $date_fin,
                    ':id' => $id
                ]);

                $message = $success 
                    ? "<div class='alert alert-success'>Annonce modifiée avec succès!</div>"
                    : "<div class='alert alert-danger'>Erreur lors de la modification</div>";
                break;

            case 'supprimer':
                $id = intval($_POST['id']);
                $query = "DELETE FROM annonce_public WHERE id = :id";
                $stmt = $conn->prepare($query);
                $success = $stmt->execute([':id' => $id]);

                $message = $success 
                    ? "<div class='alert alert-success'>Annonce supprimée avec succès!</div>"
                    : "<div class='alert alert-danger'>Erreur lors de la suppression</div>";
                break;
        }
    }
}

/**
 * Nettoie les annonces expirées et déjà inactives
 */
function nettoyerAnnoncesExpirees() {
    global $pdo;

    $date_aujourd_hui = date('Y-m-d');

    $sql = "DELETE FROM annonce_public 
            WHERE date_fin IS NOT NULL 
            AND date_fin < :date 
            AND statut = 'inactive'";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':date' => $date_aujourd_hui]);

    return $stmt->rowCount();
}

/**
 * Désactive automatiquement les annonces expirées
 */
function desactiverAnnoncesExpirees() {
    global $conn;

    $date_aujourd_hui = date('Y-m-d');

    $sql = "UPDATE annonce_public 
            SET statut = 'inactive' 
            WHERE date_fin IS NOT NULL 
            AND date_fin < :date 
            AND statut = 'active'";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':date' => $date_aujourd_hui]);

    return $stmt->rowCount();
}

/**
 * Obtient les statistiques des annonces
 */
function getStatistiquesAnnonces() {
    global $conn;

    $stats = [];

    // Total des annonces
    $stmt = $conn->query("SELECT COUNT(*) as total FROM annonce_public");
    $stats['total'] = $stmt->fetchColumn();

    // Annonces actives
    $stmt = $conn->query("SELECT COUNT(*) as actives FROM annonce_public WHERE statut = 'active'");
    $stats['actives'] = $stmt->fetchColumn();

    // Annonces par type
    $stmt = $conn->query("SELECT type_annonce, COUNT(*) as count 
                         FROM annonce_public 
                         WHERE statut = 'active' 
                         GROUP BY type_annonce");
    $stats['par_type'] = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats['par_type'][$row['type_annonce']] = $row['count'];
    }

    // Annonces expirées aujourd'hui
    $date_aujourd_hui = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as expirees 
                           FROM annonce_public 
                           WHERE date_fin = :date");
    $stmt->execute([':date' => $date_aujourd_hui]);
    $stats['expire_aujourdhui'] = $stmt->fetchColumn();

    return $stats;
}
// Récupération des annonces
$query = "SELECT * FROM annonce_public ORDER BY date_creation DESC";
$stmt = $conn->query($query);
$annonces = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Annonces Publiques</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
   

    <div class="container mt-4">
        <h1 class="mb-4"><i class="fas fa-bullhorn"></i> Gestion des Annonces Publiques</h1>
        
        <?php if (isset($message)) echo $message; ?>

        <!-- Formulaire d'ajout -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-plus"></i> Nouvelle Annonce</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="ajouter">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="titre" class="form-label">Titre *</label>
                                <input type="text" class="form-control" id="titre" name="titre" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="type_annonce" class="form-label">Type d'annonce *</label>
                                <select class="form-select" id="type_annonce" name="type_annonce" required>
                                    <option value="site">Site général</option>
                                    <option value="menu">Menu restaurant</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="couleur" class="form-label">Couleur</label>
                                <input type="color" class="form-control form-control-color" id="couleur" name="couleur" value="#007bff">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="contenu" class="form-label">Contenu *</label>
                        <textarea class="form-control" id="contenu" name="contenu" rows="3" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_debut" class="form-label">Date de début</label>
                                <input type="date" class="form-control" id="date_debut" name="date_debut">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_fin" class="form-label">Date de fin</label>
                                <input type="date" class="form-control" id="date_fin" name="date_fin">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Ajouter l'annonce
                    </button>
                </form>
            </div>
        </div>

        <!-- Liste des annonces -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> Annonces existantes</h5>
            </div>
            <div class="card-body">
                <?php if (empty($annonces)): ?>
                    <p class="text-muted">Aucune annonce trouvée.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Titre</th>
                                    <th>Type</th>
                                    <th>Statut</th>
                                    <th>Période</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($annonces as $annonce): ?>
                                <tr>
                                    <td><?= $annonce['id'] ?></td>
                                    <td>
                                        <div style="color: <?= $annonce['couleur'] ?>; font-weight: bold;">
                                            <?= htmlspecialchars($annonce['titre']) ?>
                                        </div>
                                        <small class="text-muted"><?= htmlspecialchars(substr($annonce['contenu'], 0, 50)) ?>...</small>
                                    </td>
                                    <td>
                                        <span class="badge <?= $annonce['type_annonce'] == 'menu' ? 'bg-info' : 'bg-secondary' ?>">
                                            <?= ucfirst($annonce['type_annonce']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $annonce['statut'] == 'active' ? 'bg-success' : 'bg-danger' ?>">
                                            <?= ucfirst($annonce['statut']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small>
                                            Du: <?= $annonce['date_debut'] ?: 'Immédiat' ?><br>
                                            Au: <?= $annonce['date_fin'] ?: 'Illimité' ?>
                                        </small>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="modifierAnnonce(<?= htmlspecialchars(json_encode($annonce)) ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="supprimerAnnonce(<?= $annonce['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal de modification -->
    <div class="modal fade" id="modalModifier" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier l'annonce</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="formModifier">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="modifier">
                        <input type="hidden" name="id" id="mod_id">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="mod_titre" class="form-label">Titre *</label>
                                    <input type="text" class="form-control" id="mod_titre" name="titre" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="mod_type_annonce" class="form-label">Type *</label>
                                    <select class="form-select" id="mod_type_annonce" name="type_annonce" required>
                                        <option value="site">Site général</option>
                                        <option value="menu">Menu restaurant</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="mod_couleur" class="form-label">Couleur</label>
                                    <input type="color" class="form-control form-control-color" id="mod_couleur" name="couleur">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="mod_contenu" class="form-label">Contenu *</label>
                            <textarea class="form-control" id="mod_contenu" name="contenu" rows="3" required></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="mod_statut" class="form-label">Statut</label>
                                    <select class="form-select" id="mod_statut" name="statut">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="mod_date_debut" class="form-label">Date de début</label>
                                    <input type="date" class="form-control" id="mod_date_debut" name="date_debut">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="mod_date_fin" class="form-label">Date de fin</label>
                                    <input type="date" class="form-control" id="mod_date_fin" name="date_fin">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function modifierAnnonce(annonce) {
            document.getElementById('mod_id').value = annonce.id;
            document.getElementById('mod_titre').value = annonce.titre;
            document.getElementById('mod_contenu').value = annonce.contenu;
            document.getElementById('mod_type_annonce').value = annonce.type_annonce;
            document.getElementById('mod_couleur').value = annonce.couleur;
            document.getElementById('mod_statut').value = annonce.statut;
            document.getElementById('mod_date_debut').value = annonce.date_debut;
            document.getElementById('mod_date_fin').value = annonce.date_fin;
            
            new bootstrap.Modal(document.getElementById('modalModifier')).show();
        }

        function supprimerAnnonce(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette annonce ?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="supprimer">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>