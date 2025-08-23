<?php
// planification_horaires.php - Page de planification des horaires (tout-en-un)
require_once '../config.php';

// Gestion des requêtes AJAX
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_GET['action']) {
            case 'get_previous_schedule':
                if (!isset($_GET['employee_id']) || !isset($_GET['week'])) {
                    throw new Exception('Paramètres manquants');
                }
                
                $employee_id = (int)$_GET['employee_id'];
                $current_week = $_GET['week'];
                $previous_week = date('Y-m-d', strtotime($current_week . ' -1 week'));
                
                $stmt = $conn->prepare("
                    SELECT 
                        lundi_debut, lundi_fin,
                        mardi_debut, mardi_fin,
                        mercredi_debut, mercredi_fin,
                        jeudi_debut, jeudi_fin,
                        vendredi_debut, vendredi_fin,
                        samedi_debut, samedi_fin,
                        dimanche_debut, dimanche_fin
                    FROM horaires 
                    WHERE employe_id = ? AND semaine_debut = ?
                ");
                
                $stmt->execute([$employee_id, $previous_week]);
                $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($schedule) {
                    echo json_encode(['success' => true, 'schedule' => $schedule]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Aucun horaire trouvé']);
                }
                break;
                
            case 'copy_previous_week':
                if (!isset($_GET['week'])) {
                    throw new Exception('Semaine non spécifiée');
                }
                
                $current_week = $_GET['week'];
                $previous_week = date('Y-m-d', strtotime($current_week . ' -1 week'));
                
                $conn->beginTransaction();
                
                // Récupérer tous les horaires de la semaine précédente
                $stmt = $conn->prepare("SELECT * FROM horaires WHERE semaine_debut = ?");
                $stmt->execute([$previous_week]);
                $previous_schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($previous_schedules)) {
                    throw new Exception('Aucun horaire trouvé pour la semaine précédente');
                }
                
                $copied_count = 0;
                
                foreach ($previous_schedules as $schedule) {
                    // Vérifier si un horaire existe déjà pour cette semaine
                    $checkStmt = $conn->prepare("
                        SELECT id FROM horaires 
                        WHERE employe_id = ? AND semaine_debut = ?
                    ");
                    $checkStmt->execute([$schedule['employe_id'], $current_week]);
                    $existing = $checkStmt->fetch();
                    
                    if ($existing) {
                        // Mise à jour
                        $updateStmt = $conn->prepare("
                            UPDATE horaires SET 
                                lundi_debut = ?, lundi_fin = ?,
                                mardi_debut = ?, mardi_fin = ?,
                                mercredi_debut = ?, mercredi_fin = ?,
                                jeudi_debut = ?, jeudi_fin = ?,
                                vendredi_debut = ?, vendredi_fin = ?,
                                samedi_debut = ?, samedi_fin = ?,
                                dimanche_debut = ?, dimanche_fin = ?,
                                date_modification = NOW()
                            WHERE employe_id = ? AND semaine_debut = ?
                        ");
                        
                        $updateStmt->execute([
                            $schedule['lundi_debut'], $schedule['lundi_fin'],
                            $schedule['mardi_debut'], $schedule['mardi_fin'],
                            $schedule['mercredi_debut'], $schedule['mercredi_fin'],
                            $schedule['jeudi_debut'], $schedule['jeudi_fin'],
                            $schedule['vendredi_debut'], $schedule['vendredi_fin'],
                            $schedule['samedi_debut'], $schedule['samedi_fin'],
                            $schedule['dimanche_debut'], $schedule['dimanche_fin'],
                            $schedule['employe_id'], $current_week
                        ]);
                    } else {
                        // Insertion
                      $insertStmt = $conn->prepare("
    INSERT INTO horaires (
        employe_id, semaine_debut,
        lundi_debut, lundi_fin,
        mardi_debut, mardi_fin,
        mercredi_debut, mercredi_fin,
        jeudi_debut, jeudi_fin,
        vendredi_debut, vendredi_fin,
        samedi_debut, samedi_fin,
        dimanche_debut, dimanche_fin,
        date_creation, date_modification
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
    )
");
                        
$insertStmt->execute([
    $schedule['employe_id'], $current_week,
    $schedule['lundi_debut'], $schedule['lundi_fin'],
    $schedule['mardi_debut'], $schedule['mardi_fin'],
    $schedule['mercredi_debut'], $schedule['mercredi_fin'],
    $schedule['jeudi_debut'], $schedule['jeudi_fin'],
    $schedule['vendredi_debut'], $schedule['vendredi_fin'],
    $schedule['samedi_debut'], $schedule['samedi_fin'],
    $schedule['dimanche_debut'], $schedule['dimanche_fin']
]);
                    }
                    
                    $copied_count++;
                }   
                
                $conn->commit();
                echo json_encode([
                    'success' => true, 
                    'message' => "Horaires copiés pour $copied_count employé(s)"
                ]);
                break;
                
            default:
                throw new Exception('Action non reconnue');
        }
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        error_log("Erreur AJAX planification: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    
    exit;
}

// Gestion de la sauvegarde POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['schedules']) || !is_array($input['schedules'])) {
            throw new Exception('Données invalides');
        }
        
        $conn->beginTransaction();
        
        foreach ($input['schedules'] as $schedule) {
            // Validation des données
            if (!isset($schedule['employe_id']) || !isset($schedule['semaine_debut'])) {
                throw new Exception('Données manquantes pour un employé');
            }
            
            $employe_id = (int)$schedule['employe_id'];
            $semaine_debut = $schedule['semaine_debut'];
            
            // Vérifier si un horaire existe déjà pour cet employé et cette semaine
            $checkStmt = $conn->prepare("
                SELECT id FROM horaires 
                WHERE employe_id = ? AND semaine_debut = ?
            ");
            $checkStmt->execute([$employe_id, $semaine_debut]);
            $existing = $checkStmt->fetch();
            
            if ($existing) {
                // Mise à jour
                $updateStmt = $conn->prepare("
                    UPDATE horaires SET 
                        lundi_debut = ?, lundi_fin = ?,
                        mardi_debut = ?, mardi_fin = ?,
                        mercredi_debut = ?, mercredi_fin = ?,
                        jeudi_debut = ?, jeudi_fin = ?,
                        vendredi_debut = ?, vendredi_fin = ?,
                        samedi_debut = ?, samedi_fin = ?,
                        dimanche_debut = ?, dimanche_fin = ?,
                        date_modification = NOW()
                    WHERE employe_id = ? AND semaine_debut = ?
                ");
                
                $updateStmt->execute([
                    $schedule['lundi_debut'] ?: null, $schedule['lundi_fin'] ?: null,
                    $schedule['mardi_debut'] ?: null, $schedule['mardi_fin'] ?: null,
                    $schedule['mercredi_debut'] ?: null, $schedule['mercredi_fin'] ?: null,
                    $schedule['jeudi_debut'] ?: null, $schedule['jeudi_fin'] ?: null,
                    $schedule['vendredi_debut'] ?: null, $schedule['vendredi_fin'] ?: null,
                    $schedule['samedi_debut'] ?: null, $schedule['samedi_fin'] ?: null,
                    $schedule['dimanche_debut'] ?: null, $schedule['dimanche_fin'] ?: null,
                    $employe_id, $semaine_debut
                ]);
            } else {
                // Insertion
                $insertStmt = $conn->prepare("
                    INSERT INTO horaires (
                        employe_id, semaine_debut,
                        lundi_debut, lundi_fin,
                        mardi_debut, mardi_fin,
                        mercredi_debut, mercredi_fin,
                        jeudi_debut, jeudi_fin,
                        vendredi_debut, vendredi_fin,
                        samedi_debut, samedi_fin,
                        dimanche_debut, dimanche_fin,
                        date_creation, date_modification
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
                    )
                ");
                
                $insertStmt->execute([
                    $employe_id, $semaine_debut,
                    $schedule['lundi_debut'] ?: null, $schedule['lundi_fin'] ?: null,
                    $schedule['mardi_debut'] ?: null, $schedule['mardi_fin'] ?: null,
                    $schedule['mercredi_debut'] ?: null, $schedule['mercredi_fin'] ?: null,
                    $schedule['jeudi_debut'] ?: null, $schedule['jeudi_fin'] ?: null,
                    $schedule['vendredi_debut'] ?: null, $schedule['vendredi_fin'] ?: null,
                    $schedule['samedi_debut'] ?: null, $schedule['samedi_fin'] ?: null,
                    $schedule['dimanche_debut'] ?: null, $schedule['dimanche_fin'] ?: null
                ]);
            }
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Horaires sauvegardés avec succès']);
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        error_log("Erreur sauvegarde horaires: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    
    exit;
}

// Récupérer la liste des employés actifs
try {
    $stmt = $conn->query("
        SELECT e.id, e.nom, e.prenom, e.heure_debut, e.heure_fin, p.nom as poste_nom, p.couleur
        FROM employes e
        LEFT JOIN postes p ON e.poste_id = p.id
        WHERE e.statut = 'actif'
        ORDER BY e.nom, e.prenom
    ");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}

// Récupérer la semaine actuelle ou celle sélectionnée
$selected_week = $_GET['week'] ?? date('Y-m-d', strtotime('monday this week'));
$week_start = date('Y-m-d', strtotime('monday', strtotime($selected_week)));
$week_end = date('Y-m-d', strtotime('sunday', strtotime($selected_week)));

// Récupérer les horaires existants pour cette semaine
try {
    $stmt = $conn->prepare("
        SELECT h.*, e.nom, e.prenom, p.couleur as poste_couleur
        FROM horaires h
        JOIN employes e ON h.employe_id = e.id
        LEFT JOIN postes p ON e.poste_id = p.id
        WHERE h.semaine_debut = ?
        ORDER BY e.nom, e.prenom
    ");
    $stmt->execute([$week_start]);
    $horaires_semaine = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planification des Horaires - Restaurant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .time-slot { min-height: 40px; }
        .employee-row:hover { background-color: #f9fafb; }
        .day-header { position: sticky; top: 0; z-index: 10; }
        
        /* Animation de sauvegarde */
        .saving {
            opacity: 0.7;
            pointer-events: none;
        }
        
        /* Indicateur de changements non sauvegardés */
        .unsaved-changes {
            border-left: 4px solid #f59e0b;
            background-color: #fefbf3;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto p-6">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h1 class="text-3xl font-bold text-gray-900">
                    <i class="fas fa-calendar-alt mr-3 text-blue-600"></i>Planification des Horaires
                </h1>
                <div class="flex space-x-3">
                    <button onclick="copyPreviousWeek()" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-copy mr-2"></i>Copier semaine précédente
                    </button>
                    <button id="saveBtn" onclick="saveAllSchedules()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-save mr-2"></i>Enregistrer tout
                    </button>
                </div>
            </div>
            
            <!-- Sélection de semaine -->
            <div class="flex items-center space-x-4">
                <button onclick="previousWeek()" class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-2 rounded transition-colors">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <input type="week" id="weekSelector" value="<?php echo date('Y-\WW', strtotime($week_start)); ?>" 
                       onchange="changeWeek()" class="px-3 py-2 border rounded-lg">
                <button onclick="nextWeek()" class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-2 rounded transition-colors">
                    <i class="fas fa-chevron-right"></i>
                </button>
                <div class="ml-4 text-lg font-semibold text-gray-700">
                    Semaine du <?php echo date('d/m/Y', strtotime($week_start)); ?> au <?php echo date('d/m/Y', strtotime($week_end)); ?>
                </div>
            </div>
        </div>

        <!-- Alerte changements non sauvegardés -->
        <div id="unsavedAlert" class="hidden bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-yellow-700">
                        Vous avez des modifications non sauvegardées. N'oubliez pas de cliquer sur "Enregistrer tout".
                    </p>
                </div>
            </div>
        </div>

        <!-- Planning -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <!-- Header des jours -->
                    <thead class="bg-gray-50 day-header">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-700 w-48">Employé</th>
                            <?php
                            $jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
                            foreach ($jours as $i => $jour):
                                $date = date('d/m', strtotime($week_start . " +$i days"));
                            ?>
                                <th class="px-2 py-3 text-center text-sm font-medium text-gray-700 min-w-32">
                                    <?php echo $jour; ?><br>
                                    <span class="text-xs text-gray-500"><?php echo $date; ?></span>
                                </th>
                            <?php endforeach; ?>
                            <th class="px-4 py-3 text-center text-sm font-medium text-gray-700 w-24">Actions</th>
                        </tr>
                    </thead>
                    
                    <!-- Corps du tableau -->
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($employees as $employee): ?>
                            <?php
                            // Chercher les horaires existants pour cet employé
                            $employee_schedule = null;
                            foreach ($horaires_semaine as $horaire) {
                                if ($horaire['employe_id'] == $employee['id']) {
                                    $employee_schedule = $horaire;
                                    break;
                                }
                            }
                            ?>
                            <tr class="employee-row" data-employee-id="<?php echo $employee['id']; ?>">
                                <td class="px-4 py-3">
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 rounded-full mr-2" style="background-color: <?php echo $employee['couleur'] ?? '#3B82F6'; ?>"></div>
                                        <div>
                                            <div class="font-medium text-gray-900">
                                                <?php echo htmlspecialchars($employee['prenom'] . ' ' . $employee['nom']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($employee['poste_nom'] ?? 'N/A'); ?></div>
                                            <div class="text-xs text-gray-400">
                                                Habituel: <?php echo $employee['heure_debut'] . '-' . $employee['heure_fin']; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                
                                <?php
                                $jours_db = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
                                foreach ($jours_db as $jour):
                                    $debut = $employee_schedule ? $employee_schedule[$jour . '_debut'] : null;
                                    $fin = $employee_schedule ? $employee_schedule[$jour . '_fin'] : null;
                                ?>
                                    <td class="px-2 py-2 text-center time-slot">
                                        <div class="space-y-1">
                                            <input type="time" 
                                                   class="text-xs border rounded px-1 py-1 w-full schedule-input"
                                                   data-day="<?php echo $jour; ?>"
                                                   data-type="debut"
                                                   data-original="<?php echo $debut ?: ''; ?>"
                                                   value="<?php echo $debut ?: ''; ?>"
                                                   placeholder="Début">
                                            <input type="time" 
                                                   class="text-xs border rounded px-1 py-1 w-full schedule-input"
                                                   data-day="<?php echo $jour; ?>"
                                                   data-type="fin"
                                                   data-original="<?php echo $fin ?: ''; ?>"
                                                   value="<?php echo $fin ?: ''; ?>"
                                                   placeholder="Fin">
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                                
                                <td class="px-4 py-3 text-center">
                                    <div class="flex justify-center space-x-1">
                                        <button onclick="fillDefault(<?php echo $employee['id']; ?>)" 
                                                class="text-blue-600 hover:text-blue-800 text-sm transition-colors" title="Remplir horaires par défaut">
                                            <i class="fas fa-fill"></i>
                                        </button>
                                        <button onclick="clearSchedule(<?php echo $employee['id']; ?>)" 
                                                class="text-red-600 hover:text-red-800 text-sm transition-colors" title="Effacer">
                                            <i class="fas fa-eraser"></i>
                                        </button>
                                        <button onclick="copyFromPrevious(<?php echo $employee['id']; ?>)" 
                                                class="text-green-600 hover:text-green-800 text-sm transition-colors" title="Copier semaine précédente">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Résumé des heures -->
        <div class="mt-6 bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Résumé de la semaine</h2>
            <div id="weekSummary" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-7 gap-4">
                <!-- Le résumé sera généré en JavaScript -->
            </div>
        </div>
    </div>

    <script>
        let currentWeek = '<?php echo $week_start; ?>';
        let employees = <?php echo json_encode($employees); ?>;
        let hasUnsavedChanges = false;
        
        // Event listeners pour les changements d'horaires
        document.addEventListener('DOMContentLoaded', function() {
            // Écouter les changements sur tous les inputs d'horaire
            document.querySelectorAll('.schedule-input').forEach(input => {
                input.addEventListener('change', function() {
                    markAsChanged(this);
                    updateWeekSummary();
                    showUnsavedChanges();
                });
            });
            
            updateWeekSummary();
        });
        
        function markAsChanged(input) {
            const row = input.closest('tr');
            const original = input.dataset.original || '';
            const current = input.value;
            
            // Vérifier si il y a des changements dans cette ligne
            const inputs = row.querySelectorAll('.schedule-input');
            let hasRowChanges = false;
            
            inputs.forEach(inp => {
                const orig = inp.dataset.original || '';
                const curr = inp.value;
                if (orig !== curr) {
                    hasRowChanges = true;
                }
            });
            
            if (hasRowChanges) {
                row.classList.add('unsaved-changes');
            } else {
                row.classList.remove('unsaved-changes');
            }
        }
        
        function showUnsavedChanges() {
            hasUnsavedChanges = document.querySelectorAll('.unsaved-changes').length > 0;
            const alert = document.getElementById('unsavedAlert');
            
            if (hasUnsavedChanges) {
                alert.classList.remove('hidden');
            } else {
                alert.classList.add('hidden');
            }
        }
        
        function previousWeek() {
            if (hasUnsavedChanges && !confirm('Vous avez des modifications non sauvegardées. Continuer ?')) {
                return;
            }
            
            const newWeek = new Date(currentWeek);
            newWeek.setDate(newWeek.getDate() - 7);
            const weekString = newWeek.toISOString().substr(0, 10);
            window.location.href = `?week=${weekString}`;
        }
        
        function nextWeek() {
            if (hasUnsavedChanges && !confirm('Vous avez des modifications non sauvegardées. Continuer ?')) {
                return;
            }
            
            const newWeek = new Date(currentWeek);
            newWeek.setDate(newWeek.getDate() + 7);
            const weekString = newWeek.toISOString().substr(0, 10);
            window.location.href = `?week=${weekString}`;
        }
        
        function changeWeek() {
            if (hasUnsavedChanges && !confirm('Vous avez des modifications non sauvegardées. Continuer ?')) {
                return;
            }
            
            const weekInput = document.getElementById('weekSelector');
            const [year, week] = weekInput.value.split('-W');
            
            // Calculer la date du lundi de cette semaine
            const jan4 = new Date(year, 0, 4);
            const weekStart = new Date(jan4.getTime() + (week - 1) * 7 * 24 * 60 * 60 * 1000);
            weekStart.setDate(weekStart.getDate() - weekStart.getDay() + 1);
            
            const weekString = weekStart.toISOString().substr(0, 10);
            window.location.href = `?week=${weekString}`;
        }
        
        function fillDefault(employeeId) {
            const employee = employees.find(e => e.id == employeeId);
            if (!employee) return;
            
            const row = document.querySelector(`tr[data-employee-id="${employeeId}"]`);
            const jours = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi'];
            
            jours.forEach(jour => {
                const debutInput = row.querySelector(`input[data-day="${jour}"][data-type="debut"]`);
                const finInput = row.querySelector(`input[data-day="${jour}"][data-type="fin"]`);
                
                if (debutInput && finInput) {
                    debutInput.value = employee.heure_debut;
                    finInput.value = employee.heure_fin;
                    
                    // Marquer comme modifié
                    markAsChanged(debutInput);
                    markAsChanged(finInput);
                }
            });
            
            updateWeekSummary();
            showUnsavedChanges();
        }
        
        function clearSchedule(employeeId) {
            if (!confirm('Effacer tous les horaires de cet employé pour cette semaine ?')) return;
            
            const row = document.querySelector(`tr[data-employee-id="${employeeId}"]`);
            const inputs = row.querySelectorAll('.schedule-input');
            
            inputs.forEach(input => {
                input.value = '';
                markAsChanged(input);
            });
            
            updateWeekSummary();
            showUnsavedChanges();
        }
        
        function copyFromPrevious(employeeId) {
            fetch(`?action=get_previous_schedule&employee_id=${employeeId}&week=${currentWeek}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.schedule) {
                        const row = document.querySelector(`tr[data-employee-id="${employeeId}"]`);
                        const jours = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
                        
                        jours.forEach(jour => {
                            const debutInput = row.querySelector(`input[data-day="${jour}"][data-type="debut"]`);
                            const finInput = row.querySelector(`input[data-day="${jour}"][data-type="fin"]`);
                            
                            if (debutInput && finInput) {
                                debutInput.value = data.schedule[jour + '_debut'] || '';
                                finInput.value = data.schedule[jour + '_fin'] || '';
                                markAsChanged(debutInput);
                                markAsChanged(finInput);
                            }
                        });
                        
                        updateWeekSummary();
                        showUnsavedChanges();
                    } else {
                        alert('Aucun horaire trouvé pour la semaine précédente');
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur lors du chargement des données');
                });
        }
        
        function copyPreviousWeek() {
            if (!confirm('Copier les horaires de la semaine précédente pour tous les employés ?')) return;
            
            const saveBtn = document.getElementById('saveBtn');
            const originalContent = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Copie en cours...';
            saveBtn.disabled = true;
            
            fetch(`?action=copy_previous_week&week=${currentWeek}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Horaires copiés avec succès !');
                        location.reload();
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur lors de la copie des données');
                })
                .finally(() => {
                    saveBtn.innerHTML = originalContent;
                    saveBtn.disabled = false;
                });
        }
        
        function saveAllSchedules() {
            const saveBtn = document.getElementById('saveBtn');
            const originalContent = saveBtn.innerHTML;
            
            // Animation de chargement
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sauvegarde...';
            saveBtn.disabled = true;
            
            const schedules = [];
            
            document.querySelectorAll('tr[data-employee-id]').forEach(row => {
                const employeeId = row.dataset.employeeId;
                const schedule = {
                    employe_id: employeeId,
                    semaine_debut: currentWeek
                };
                
                const jours = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
                jours.forEach(jour => {
                    const debutInput = row.querySelector(`input[data-day="${jour}"][data-type="debut"]`);
                    const finInput = row.querySelector(`input[data-day="${jour}"][data-type="fin"]`);
                    
                    schedule[jour + '_debut'] = debutInput.value || null;
                    schedule[jour + '_fin'] = finInput.value || null;
                });
                
                schedules.push(schedule);
            });
            
            fetch('?', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({schedules: schedules})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Succès - mettre à jour les valeurs originales
                    document.querySelectorAll('.schedule-input').forEach(input => {
                        input.dataset.original = input.value;
                    });
                    
                    // Enlever les marqueurs de changements
                    document.querySelectorAll('.unsaved-changes').forEach(row => {
                        row.classList.remove('unsaved-changes');
                    });
                    
                    hasUnsavedChanges = false;
                    showUnsavedChanges();
                    
                    // Animation de succès
                    saveBtn.innerHTML = '<i class="fas fa-check mr-2 text-green-400"></i>Sauvegardé !';
                    saveBtn.classList.add('bg-green-700');
                    
                    setTimeout(() => {
                        saveBtn.innerHTML = originalContent;
                        saveBtn.classList.remove('bg-green-700');
                        saveBtn.disabled = false;
                    }, 2000);
                    
                } else {
                    alert('Erreur lors de la sauvegarde: ' + data.message);
                    saveBtn.innerHTML = originalContent;
                    saveBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur de connexion lors de la sauvegarde');
                saveBtn.innerHTML = originalContent;
                saveBtn.disabled = false;
            });
        }
        
        function updateWeekSummary() {
            const summary = {
                lundi: { employees: 0, hours: 0 },
                mardi: { employees: 0, hours: 0 },
                mercredi: { employees: 0, hours: 0 },
                jeudi: { employees: 0, hours: 0 },
                vendredi: { employees: 0, hours: 0 },
                samedi: { employees: 0, hours: 0 },
                dimanche: { employees: 0, hours: 0 }
            };
            
            document.querySelectorAll('tr[data-employee-id]').forEach(row => {
                const jours = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
                
                jours.forEach(jour => {
                    const debutInput = row.querySelector(`input[data-day="${jour}"][data-type="debut"]`);
                    const finInput = row.querySelector(`input[data-day="${jour}"][data-type="fin"]`);
                    
                    if (debutInput.value && finInput.value) {
                        summary[jour].employees++;
                        
                        const debut = new Date(`1970-01-01T${debutInput.value}`);
                        const fin = new Date(`1970-01-01T${finInput.value}`);
                        const hours = (fin - debut) / (1000 * 60 * 60);
                        
                        if (hours > 0) {
                            summary[jour].hours += hours;
                        }
                    }
                });
            });
            
            // Afficher le résumé
            const summaryDiv = document.getElementById('weekSummary');
            summaryDiv.innerHTML = '';
            
            Object.keys(summary).forEach(jour => {
                const data = summary[jour];
                summaryDiv.innerHTML += `
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <div class="text-sm font-medium text-gray-700 capitalize">${jour}</div>
                        <div class="text-lg font-bold text-blue-600">${data.employees}</div>
                        <div class="text-xs text-gray-500">employés</div>
                        <div class="text-sm text-gray-600">${data.hours.toFixed(1)}h</div>
                    </div>
                `;
            });
        }
        
        // Alerte avant de quitter la page avec des changements non sauvegardés
        window.addEventListener('beforeunload', function(e) {
            if (hasUnsavedChanges) {
                const confirmationMessage = 'Vous avez des modifications non sauvegardées. Êtes-vous sûr de vouloir quitter cette page ?';
                e.returnValue = confirmationMessage;
                return confirmationMessage;
            }
        });
    </script>
</body>
</html>