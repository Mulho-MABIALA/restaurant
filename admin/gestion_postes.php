<?php
// gestion_postes.php - Gestion complète des postes (fusionnée)
require_once '../config.php';

// ====================================================================
// TRAITEMENT DES REQUÊTES AJAX
// ====================================================================

// Fonction pour envoyer une réponse JSON et arrêter l'exécution
function sendJsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Fonction pour logger une activité
function logActivity($conn, $action, $table, $id, $details) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO logs_activite (action, table_concernee, id_enregistrement, details) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$action, $table, $id, json_encode($details)]);
    } catch (Exception $e) {
        // Log silencieux en cas d'erreur
    }
}

// Traitement des requêtes AJAX
if (isset($_GET['action']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'];
    
    switch ($action) {
        // ============================================================
        // AJOUTER UN POSTE
        // ============================================================
        case 'add_poste':
            try {
                if (empty($_POST['nom'])) {
                    throw new Exception('Le nom du poste est requis');
                }
                
                // Vérifier si le nom existe déjà
                $stmt = $conn->prepare("SELECT id FROM postes WHERE nom = ? AND actif = TRUE");
                $stmt->execute([$_POST['nom']]);
                if ($stmt->fetch()) {
                    throw new Exception('Un poste avec ce nom existe déjà');
                }
                
                $stmt = $conn->prepare("
                    INSERT INTO postes (nom, description, salaire_min, salaire_max, couleur) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $_POST['nom'],
                    $_POST['description'] ?? null,
                    $_POST['salaire_min'] ?? 0,
                    $_POST['salaire_max'] ?? 0,
                    $_POST['couleur'] ?? '#3B82F6'
                ]);
                
                $poste_id = $conn->lastInsertId();
                
                // Log de l'activité
                logActivity($conn, 'CREATE_POSTE', 'postes', $poste_id, ['nom' => $_POST['nom']]);
                
                sendJsonResponse([
                    'success' => true,
                    'message' => 'Poste ajouté avec succès',
                    'poste_id' => $poste_id
                ]);
                
            } catch (Exception $e) {
                sendJsonResponse([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            break;
            
        // ============================================================
        // MODIFIER UN POSTE
        // ============================================================
        case 'update_poste':
            try {
                if (empty($_POST['id']) || empty($_POST['nom'])) {
                    throw new Exception('ID et nom du poste requis');
                }
                
                $poste_id = $_POST['id'];
                
                // Vérifier si le nom existe déjà (pour un autre poste)
                $stmt = $conn->prepare("SELECT id FROM postes WHERE nom = ? AND id != ? AND actif = TRUE");
                $stmt->execute([$_POST['nom'], $poste_id]);
                if ($stmt->fetch()) {
                    throw new Exception('Un poste avec ce nom existe déjà');
                }
                
                $stmt = $conn->prepare("
                    UPDATE postes 
                    SET nom = ?, description = ?, salaire_min = ?, salaire_max = ?, couleur = ?
                    WHERE id = ? AND actif = TRUE
                ");
                
                $stmt->execute([
                    $_POST['nom'],
                    $_POST['description'] ?? null,
                    $_POST['salaire_min'] ?? 0,
                    $_POST['salaire_max'] ?? 0,
                    $_POST['couleur'] ?? '#3B82F6',
                    $poste_id
                ]);
                
                if ($stmt->rowCount() === 0) {
                    throw new Exception('Poste non trouvé ou non modifiable');
                }
                
                // Log de l'activité
                logActivity($conn, 'UPDATE_POSTE', 'postes', $poste_id, ['nom' => $_POST['nom']]);
                
                sendJsonResponse([
                    'success' => true,
                    'message' => 'Poste modifié avec succès'
                ]);
                
            } catch (Exception $e) {
                sendJsonResponse([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            break;
            
        // ============================================================
        // SUPPRIMER UN POSTE
        // ============================================================
        case 'delete_poste':
            try {
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (empty($input['id'])) {
                    throw new Exception('ID poste requis');
                }
                
                $poste_id = $input['id'];
                
                // Vérifier si des employés sont associés à ce poste
                $stmt = $conn->prepare("SELECT COUNT(*) FROM employes WHERE poste_id = ? AND statut != 'inactif'");
                $stmt->execute([$poste_id]);
                $nb_employees = $stmt->fetchColumn();
                
                if ($nb_employees > 0) {
                    throw new Exception("Impossible de supprimer ce poste car $nb_employees employé(s) y sont associé(s)");
                }
                
                // Désactivation logique du poste
                $stmt = $conn->prepare("UPDATE postes SET actif = FALSE WHERE id = ?");
                $stmt->execute([$poste_id]);
                
                if ($stmt->rowCount() === 0) {
                    throw new Exception('Poste non trouvé');
                }
                
                // Log de l'activité
                logActivity($conn, 'DELETE_POSTE', 'postes', $poste_id, ['actif' => false]);
                
                sendJsonResponse([
                    'success' => true,
                    'message' => 'Poste supprimé avec succès'
                ]);
                
            } catch (Exception $e) {
                sendJsonResponse([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            break;
            
        // ============================================================
        // RÉCUPÉRER LES POSTES
        // ============================================================
        case 'get_postes':
            try {
                $stmt = $conn->query("SELECT * FROM postes WHERE actif = TRUE ORDER BY nom");
                $postes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                sendJsonResponse([
                    'success' => true,
                    'postes' => $postes
                ]);
            } catch (Exception $e) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Erreur lors du chargement des postes'
                ]);
            }
            break;
            
        // ============================================================
        // UPLOAD/IMPORT DE POSTES
        // ============================================================
        case 'upload_postes':
            try {
                if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Fichier requis');
                }
                
                $file = $_FILES['file'];
                $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if (!in_array($fileType, ['csv', 'xlsx'])) {
                    throw new Exception('Format de fichier non supporté. Utilisez CSV ou XLSX.');
                }
                
                $importedCount = 0;
                $errors = [];
                
                if ($fileType === 'csv') {
                    // Traitement CSV
                    if (($handle = fopen($file['tmp_name'], "r")) !== FALSE) {
                        $headers = fgetcsv($handle, 1000, ",");
                        
                        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                            try {
                                if (empty($data[0])) continue; // Ignorer les lignes vides
                                
                                $stmt = $conn->prepare("
                                    INSERT INTO postes (nom, description, salaire_min, salaire_max, couleur) 
                                    VALUES (?, ?, ?, ?, ?)
                                ");
                                
                                $stmt->execute([
                                    $data[0], // nom
                                    $data[1] ?? null, // description
                                    $data[2] ?? 0, // salaire_min
                                    $data[3] ?? 0, // salaire_max
                                    $data[4] ?? '#3B82F6' // couleur
                                ]);
                                
                                $importedCount++;
                            } catch (Exception $e) {
                                $errors[] = "Ligne " . ($importedCount + 2) . ": " . $e->getMessage();
                            }
                        }
                        fclose($handle);
                    }
                }
                
                // Log de l'activité
                logActivity($conn, 'IMPORT_POSTES', 'postes', null, [
                    'imported_count' => $importedCount,
                    'errors_count' => count($errors)
                ]);
                
                $message = "$importedCount poste(s) importé(s) avec succès";
                if (!empty($errors)) {
                    $message .= ". " . count($errors) . " erreur(s) rencontrée(s).";
                }
                
                sendJsonResponse([
                    'success' => true,
                    'message' => $message,
                    'imported_count' => $importedCount,
                    'errors' => $errors
                ]);
                
            } catch (Exception $e) {
                sendJsonResponse([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            break;
            
        default:
            sendJsonResponse([
                'success' => false,
                'message' => 'Action non reconnue'
            ]);
    }
}

// ====================================================================
// CHARGEMENT DES DONNÉES POUR L'AFFICHAGE
// ====================================================================

try {
    $stmt = $conn->query("SELECT * FROM postes WHERE actif = TRUE ORDER BY nom");
    $postes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Postes - Restaurant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="max-w-6xl mx-auto p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-900">
                <i class="fas fa-briefcase mr-3 text-blue-600"></i>Gestion des Postes
            </h1>
            <div class="flex space-x-3">
                <button onclick="openUploadModal()" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-upload mr-2"></i>Importer
                </button>
                <button onclick="openAddModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-plus mr-2"></i>Nouveau Poste
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($postes as $poste): ?>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center">
                            <div class="w-4 h-4 rounded-full mr-3" style="background-color: <?php echo $poste['couleur']; ?>"></div>
                            <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($poste['nom']); ?></h3>
                        </div>
                        <div class="flex space-x-2">
                            <button onclick="editPoste(<?php echo $poste['id']; ?>)" class="text-blue-600 hover:text-blue-800">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deletePoste(<?php echo $poste['id']; ?>)" class="text-red-600 hover:text-red-800">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    
                    <p class="text-gray-600 text-sm mb-4"><?php echo htmlspecialchars($poste['description'] ?? 'Aucune description'); ?></p>
                    
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div>
                            <span class="text-gray-500">Salaire min:</span>
                            <div class="font-medium"><?php echo number_format($poste['salaire_min'], 0, ',', ' '); ?> €</div>
                        </div>
                        <div>
                            <span class="text-gray-500">Salaire max:</span>
                            <div class="font-medium"><?php echo number_format($poste['salaire_max'], 0, ',', ' '); ?> €</div>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-gray-500">Employés:</span>
                            <span class="font-medium">
                                <?php
                                $stmt = $conn->prepare("SELECT COUNT(*) FROM employes WHERE poste_id = ? AND statut = 'actif'");
                                $stmt->execute([$poste['id']]);
                                echo $stmt->fetchColumn();
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Modal pour ajouter/modifier un poste -->
    <div id="posteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-md w-full">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 id="modalTitle" class="text-lg font-semibold text-gray-900">Ajouter un poste</h3>
                </div>
                
                <form id="posteForm" class="p-6">
                    <input type="hidden" id="posteId" name="id">
                    
                    <div class="space-y-4">
                        <div>
                            <label for="nom" class="block text-sm font-medium text-gray-700 mb-2">Nom du poste *</label>
                            <input type="text" id="nom" name="nom" required class="w-full px-3 py-2 border rounded-md">
                        </div>
                        
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <textarea id="description" name="description" rows="3" class="w-full px-3 py-2 border rounded-md"></textarea>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="salaireMin" class="block text-sm font-medium text-gray-700 mb-2">Salaire min (€)</label>
                                <input type="number" id="salaireMin" name="salaire_min" step="0.01" class="w-full px-3 py-2 border rounded-md">
                            </div>
                            <div>
                                <label for="salaireMax" class="block text-sm font-medium text-gray-700 mb-2">Salaire max (€)</label>
                                <input type="number" id="salaireMax" name="salaire_max" step="0.01" class="w-full px-3 py-2 border rounded-md">
                            </div>
                        </div>
                        
                        <div>
                            <label for="couleur" class="block text-sm font-medium text-gray-700 mb-2">Couleur</label>
                            <input type="color" id="couleur" name="couleur" value="#3B82F6" class="w-full h-10 border rounded-md">
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Annuler
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal d'upload -->
    <div id="uploadModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-md w-full">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Importer des postes</h3>
                </div>
                
                <form id="uploadForm" enctype="multipart/form-data" class="p-6">
                    <div class="space-y-4">
                        <div>
                            <label for="file" class="block text-sm font-medium text-gray-700 mb-2">Fichier CSV ou XLSX</label>
                            <input type="file" id="file" name="file" accept=".csv,.xlsx" required class="w-full px-3 py-2 border rounded-md">
                        </div>
                        
                        <div class="bg-blue-50 p-4 rounded-md">
                            <h4 class="text-sm font-medium text-blue-900 mb-2">Format attendu:</h4>
                            <p class="text-xs text-blue-700">
                                Colonnes: Nom, Description, Salaire Min, Salaire Max, Couleur<br>
                                Seul le nom est obligatoire.
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" onclick="closeUploadModal()" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Annuler
                        </button>
                        <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
                            Importer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let postes = <?php echo json_encode($postes); ?>;
        
        // ============================================================
        // FONCTIONS MODAL PRINCIPAL
        // ============================================================
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Ajouter un poste';
            document.getElementById('posteForm').reset();
            document.getElementById('posteId').value = '';
            document.getElementById('posteModal').classList.remove('hidden');
        }
        
        function editPoste(id) {
            const poste = postes.find(p => p.id == id);
            if (!poste) return;
            
            document.getElementById('modalTitle').textContent = 'Modifier le poste';
            document.getElementById('posteId').value = poste.id;
            document.getElementById('nom').value = poste.nom;
            document.getElementById('description').value = poste.description || '';
            document.getElementById('salaireMin').value = poste.salaire_min;
            document.getElementById('salaireMax').value = poste.salaire_max;
            document.getElementById('couleur').value = poste.couleur;
            document.getElementById('posteModal').classList.remove('hidden');
        }
        
        function closeModal() {
            document.getElementById('posteModal').classList.add('hidden');
        }
        
        // ============================================================
        // FONCTIONS MODAL UPLOAD
        // ============================================================
        function openUploadModal() {
            document.getElementById('uploadForm').reset();
            document.getElementById('uploadModal').classList.remove('hidden');
        }
        
        function closeUploadModal() {
            document.getElementById('uploadModal').classList.add('hidden');
        }
        
        // ============================================================
        // FONCTION SUPPRESSION
        // ============================================================
        function deletePoste(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce poste ?')) {
                fetch('?action=delete_poste', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: id})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                });
            }
        }
        
        // ============================================================
        // GESTIONNAIRE FORMULAIRE PRINCIPAL
        // ============================================================
        document.getElementById('posteForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const isEdit = formData.get('id') !== '';
            const action = isEdit ? 'update_poste' : 'add_poste';
            
            fetch('?action=' + action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                alert('Erreur de connexion: ' + error.message);
            });
        });
        
        // ============================================================
        // GESTIONNAIRE FORMULAIRE UPLOAD
        // ============================================================
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            
            fetch('?action=upload_postes', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                alert('Erreur de connexion: ' + error.message);
            });
        });
    </script>
</body>
</html>