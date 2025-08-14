 <?php
// ajax/add_employee.php
require_once '../config.php';
require_once 'phpqrcode/qrlib.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    // Validation des champs requis
    $required_fields = ['nom', 'prenom', 'email', 'date_embauche'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Le champ $field est requis");
        }
    }
    
    // Vérifier si l'email existe déjà
    $stmt = $conn->prepare("SELECT id FROM employes WHERE email = ? AND statut != 'inactif'");
    $stmt->execute([$_POST['email']]);
    if ($stmt->fetch()) {
        throw new Exception('Cet email est déjà utilisé par un autre employé actif');
    }
    
    // Gestion de l'upload de photo
    $photo_filename = 'default-avatar.png';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/photos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_ext, $allowed_exts) && $_FILES['photo']['size'] <= 5000000) {
            $photo_filename = uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $photo_filename;
            
            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $upload_path)) {
                throw new Exception('Erreur lors de l\'upload de la photo');
            }
        } else {
            throw new Exception('Format de photo non valide ou taille trop importante');
        }
    }
    
    // Insertion de l'employé
    $stmt = $conn->prepare("
        INSERT INTO employes (nom, prenom, email, telephone, poste_id, salaire, date_embauche, 
                              heure_debut, heure_fin, photo, is_admin, statut) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
        $_POST['statut'] ?? 'actif'
    ]);
    
    $employee_id = $conn->lastInsertId();
    
    // Génération du QR Code
    $qr_data = json_encode([
        'id' => $employee_id,
        'nom' => $_POST['nom'],
        'prenom' => $_POST['prenom'],
        'email' => $_POST['email'],
        'generated' => date('Y-m-d H:i:s')
    ]);
    
    $qr_dir = '../qrcodes/';
    if (!is_dir($qr_dir)) {
        mkdir($qr_dir, 0755, true);
    }
    
    $qr_filename = 'qr_employee_' . $employee_id . '.png';
    $qr_path = $qr_dir . $qr_filename;
    
    QRcode::png($qr_data, $qr_path, QR_ECLEVEL_L, 4);
    
    // Mise à jour avec le QR Code
    $stmt = $conn->prepare("UPDATE employes SET qr_code = ?, qr_data = ? WHERE id = ?");
    $stmt->execute([$qr_filename, $qr_data, $employee_id]);
    
    // Log de l'activité
    $stmt = $conn->prepare("
        INSERT INTO logs_activite (action, table_concernee, id_enregistrement, details) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        'CREATE_EMPLOYEE',
        'employes',
        $employee_id,
        json_encode(['nom' => $_POST['nom'], 'prenom' => $_POST['prenom']])
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Employé ajouté avec succès',
        'employee_id' => $employee_id
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

<?php