<?php
// planification_horaires.php - Page de planification des horaires
require_once '../config.php';

// Récupérer la liste des employés actifs
try {
    $stmt = $pdo->query("
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
    $stmt = $pdo->prepare("
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
                    <button onclick="copyPreviousWeek()" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-copy mr-2"></i>Copier semaine précédente
                    </button>
                    <button onclick="saveAllSchedules()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-save mr-2"></i>Enregistrer tout
                    </button>
                </div>
            </div>
            
            <!-- Sélection de semaine -->
            <div class="flex items-center space-x-4">
                <button onclick="previousWeek()" class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-2 rounded">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <input type="week" id="weekSelector" value="<?php echo date('Y-\WW', strtotime($week_start)); ?>" 
                       onchange="changeWeek()" class="px-3 py-2 border rounded-lg">
                <button onclick="nextWeek()" class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-2 rounded">
                    <i class="fas fa-chevron-right"></i>
                </button>
                <div class="ml-4 text-lg font-semibold text-gray-700">
                    Semaine du <?php echo date('d/m/Y', strtotime($week_start)); ?> au <?php echo date('d/m/Y', strtotime($week_end)); ?>
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
                                                   value="<?php echo $debut ?: ''; ?>"
                                                   placeholder="Début">
                                            <input type="time" 
                                                   class="text-xs border rounded px-1 py-1 w-full schedule-input"
                                                   data-day="<?php echo $jour; ?>"
                                                   data-type="fin"
                                                   value="<?php echo $fin ?: ''; ?>"
                                                   placeholder="Fin">
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                                
                                <td class="px-4 py-3 text-center">
                                    <div class="flex justify-center space-x-1">
                                        <button onclick="fillDefault(<?php echo $employee['id']; ?>)" 
                                                class="text-blue-600 hover:text-blue-800 text-sm" title="Remplir horaires par défaut">
                                            <i class="fas fa-fill"></i>
                                        </button>
                                        <button onclick="clearSchedule(<?php echo $employee['id']; ?>)" 
                                                class="text-red-600 hover:text-red-800 text-sm" title="Effacer">
                                            <i class="fas fa-eraser"></i>
                                        </button>
                                        <button onclick="copyFromPrevious(<?php echo $employee['id']; ?>)" 
                                                class="text-green-600 hover:text-green-800 text-sm" title="Copier semaine précédente">
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
        
        // Event listeners pour les changements d'horaires
        document.addEventListener('DOMContentLoaded', function() {
            // Écouter les changements sur tous les inputs d'horaire
            document.querySelectorAll('.schedule-input').forEach(input => {
                input.addEventListener('change', updateWeekSummary);
            });
            
            updateWeekSummary();
        });
        
        function previousWeek() {
            const newWeek = new Date(currentWeek);
            newWeek.setDate(newWeek.getDate() - 7);
            const weekString = newWeek.toISOString().substr(0, 10);
            window.location.href = `?week=${weekString}`;
        }
        
        function nextWeek() {
            const newWeek = new Date(currentWeek);
            newWeek.setDate(newWeek.getDate() + 7);
            const weekString = newWeek.toISOString().substr(0, 10);
            window.location.href = `?week=${weekString}`;
        }
        
        function changeWeek() {
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
                }
            });
            
            updateWeekSummary();
        }
        
        function clearSchedule(employeeId) {
            if (!confirm('Effacer tous les horaires de cet employé pour cette semaine ?')) return;
            
            const row = document.querySelector(`tr[data-employee-id="${employeeId}"]`);
            row.querySelectorAll('.schedule-input').forEach(input => {
                input.value = '';
            });
            
            updateWeekSummary();
        }
        
        function copyFromPrevious(employeeId) {
            fetch(`ajax/get_previous_schedule.php?employee_id=${employeeId}&week=${currentWeek}`)
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
                            }
                        });
                        
                        updateWeekSummary();
                    } else {
                        alert('Aucun horaire trouvé pour la semaine précédente');
                    }
                });
        }
        
        function copyPreviousWeek() {
            if (!confirm('Copier les horaires de la semaine précédente pour tous les employés ?')) return;
            
            fetch(`ajax/copy_previous_week.php?week=${currentWeek}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                });
        }
        
        function saveAllSchedules() {
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
            
            fetch('ajax/save_schedules.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({schedules: schedules})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Horaires sauvegardés avec succès!');
                } else {
                    alert('Erreur: ' + data.message);
                }
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
    </script>
</body>
</html>