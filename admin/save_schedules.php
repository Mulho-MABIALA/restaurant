<?php
// ajax/save_schedules.php
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

try {
    if (empty($input['schedules'])) {
        throw new Exception('Aucun horaire à sauvegarder');
    }
    
    $pdo->beginTransaction();
    
    foreach ($input['schedules'] as $schedule) {
        if (empty($schedule['employe_id']) || empty($schedule['semaine_debut'])) {
            continue;
        }
        
        // Vérifier si un horaire existe déjà
        $stmt = $conn->prepare("
            SELECT id FROM horaires 
            WHERE employe_id = ? AND semaine_debut = ?
        ");
        $stmt->execute([$schedule['employe_id'], $schedule['semaine_debut']]);
        
        if ($stmt->fetch()) {
            // Mise à jour
            $stmt = $conn->prepare("
                UPDATE horaires SET
                    lundi_debut = ?, lundi_fin = ?,
                    mardi_debut = ?, mardi_fin = ?,
                    mercredi_debut = ?, mercredi_fin = ?,
                    jeudi_debut = ?, jeudi_fin = ?,
                    vendredi_debut = ?, vendredi_fin = ?,
                    samedi_debut = ?, samedi_fin = ?,
                    dimanche_debut = ?, dimanche_fin = ?
                WHERE employe_id = ? AND semaine_debut = ?
            ");
            
            $stmt->execute([
                $schedule['lundi_debut'], $schedule['lundi_fin'],
                $schedule['mardi_debut'], $schedule['mardi_fin'],
                $schedule['mercredi_debut'], $schedule['mercredi_fin'],
                $schedule['jeudi_debut'], $schedule['jeudi_fin'],
                $schedule['vendredi_debut'], $schedule['vendredi_fin'],
                $schedule['samedi_debut'], $schedule['samedi_fin'],
                $schedule['dimanche_debut'], $schedule['dimanche_fin'],
                $schedule['employe_id'], $schedule['semaine_debut']
            ]);
        } else {
            // Insertion
            $stmt = $conn->prepare("
                INSERT INTO horaires (
                    employe_id, semaine_debut,
                    lundi_debut, lundi_fin,
                    mardi_debut, mardi_fin,
                    mercredi_debut, mercredi_fin,
                    jeudi_debut, jeudi_fin,
                    vendredi_debut, vendredi_fin,
                    samedi_debut, samedi_fin,
                    dimanche_debut, dimanche_fin
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $schedule['employe_id'], $schedule['semaine_debut'],
                $schedule['lundi_debut'], $schedule['lundi_fin'],
                $schedule['mardi_debut'], $schedule['mardi_fin'],
                $schedule['mercredi_debut'], $schedule['mercredi_fin'],
                $schedule['jeudi_debut'], $schedule['jeudi_fin'],
                $schedule['vendredi_debut'], $schedule['vendredi_fin'],
                $schedule['samedi_debut'], $schedule['samedi_fin'],
                $schedule['dimanche_debut'], $schedule['dimanche_fin']
            ]);
        }
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Horaires sauvegardés avec succès'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
$_POST['nom']]);
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
    $stmt = $pdo->prepare("
        INSERT INTO logs_activite (action, table_concernee, id_enregistrement, details) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        'CREATE_POSTE',
        'postes',
        $poste_id,
        json_encode(['nom' => $_POST['nom']])
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Poste ajouté avec succès',
        'poste_id' => $poste_id
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>