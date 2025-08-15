<?php
// ajax/update_employee.php
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    if (empty($_POST['id'])) {
        throw new Exception('ID employé requis');
    }
    
    $employee_id = $_POST['id'];
    
    // Vérifier si l'employé existe
    $stmt = $conn->prepare("SELECT * FROM employes WHERE id = ?");
    $stmt->execute([$employee_id]);
    $current_employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current_employee) {
        throw new Exception('Employé non trouvé');
    }
    
    // Vérifier l'email unique (sauf pour l'employé actuel)
    $stmt = $conn->prepare("SELECT id FROM employes WHERE email = ? AND id != ? AND statut != 'inactif'");
    $stmt->execute([$_POST['email'], $employee_id]);
    if ($stmt->fetch()) {
        throw new Exception('Cet email est déjà utilisé par un autre employé actif');
    }
    
    $photo_filename = $current_employee['photo'];
    
    // Gestion de l'upload de photo
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/photos/';
        
        $file_ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_ext, $allowed_exts) && $_FILES['photo']['size'] <= 5000000) {
            $photo_filename = uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $photo_filename;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                // Supprimer l'ancienne photo (sauf default)
                if ($current_employee['photo'] !== 'default-avatar.png') {
                    $old_photo = $upload_dir . $current_employee['photo'];
                    if (file_exists($old_photo)) {
                        unlink($old_photo);
                    }
                }
            } else {
                throw new Exception('Erreur lors de l\'upload de la photo');
            }
        } else {
            throw new Exception('Format de photo non valide ou taille trop importante');
        }
    }
    
    // Mise à jour de l'employé
    $stmt = $conn->prepare("
        UPDATE employes 
        SET nom = ?, prenom = ?, email = ?, telephone = ?, poste_id = ?, salaire = ?, 
            date_embauche = ?, heure_debut = ?, heure_fin = ?, photo = ?, 
            is_admin = ?, statut = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $_POST['nom'],
        $_POST['prenom'],
        $_POST['email'],
        $_POST['telephone'] ?? null,
        $_POST['poste_id'] ?? null,
        $_POST['salaire'] ?? null,
        $_POST['date_embauche'],
        $_POST['heure_debut'] ?? '08:00:00',
        $_POST['heure_fin'] ?? '17:00:00',
        $photo_filename,
        isset($_POST['is_admin']) ? 1 : 0,
        $_POST['statut'] ?? 'actif',
        $employee_id
    ]);
    
    // Log de l'activité
    $stmt = $conn->prepare("
        INSERT INTO logs_activite (action, table_concernee, id_enregistrement, details) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        'UPDATE_EMPLOYEE',
        'employes',
        $employee_id,
        json_encode(['nom' => $_POST['nom'], 'prenom' => $_POST['prenom']])
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Employé modifié avec succès'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

