<?php
// gestion_postes.php - Page de gestion des postes (pour les admins)
require_once '../config.php';

try {
    $stmt = $conn->query("SELECT * FROM postes ORDER BY nom");
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
            <button onclick="openAddModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                <i class="fas fa-plus mr-2"></i>Nouveau Poste
            </button>
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

    <script>
        let postes = <?php echo json_encode($postes); ?>;
        
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
        
        function deletePoste(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce poste ?')) {
                fetch('ajax/delete_poste.php', {
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
        
        document.getElementById('posteForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const url = formData.get('id') ? 'ajax/update_poste.php' : 'ajax/add_poste.php';
            
            fetch(url, {
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
            });
        });
    </script>
</body>
</html>