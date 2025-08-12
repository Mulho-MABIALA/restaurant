<?php
require_once '../config.php';

// Configuration pour réponse JSON
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée');
    }

    // Validation des données
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $nom = trim($_POST['nom'] ?? '');
    $prix = filter_input(INPUT_POST, 'prix', FILTER_VALIDATE_FLOAT);
    $description = trim($_POST['description'] ?? '');
    $categorie_id = filter_input(INPUT_POST, 'categorie_id', FILTER_VALIDATE_INT);

    // Vérifications
    if (!$id || $id <= 0) {
        throw new Exception('ID du plat invalide');
    }

    if (empty($nom)) {
        throw new Exception('Le nom du plat est obligatoire');
    }

    if ($prix === false || $prix < 0) {
        throw new Exception('Le prix doit être un nombre positif');
    }

    // Vérifier si le plat existe
    $checkStmt = $conn->prepare("SELECT id, image FROM plats WHERE id = :id");
    $checkStmt->execute(['id' => $id]);
    $existingPlat = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingPlat) {
        throw new Exception('Plat non trouvé');
    }

    // Gestion de l'image
    $imageName = $existingPlat['image']; // Conserver l'image actuelle par défaut
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/';
        
        // Créer le dossier s'il n'existe pas
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileInfo = pathinfo($_FILES['image']['name']);
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $fileExtension = strtolower($fileInfo['extension']);

        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception('Format d\'image non autorisé. Utilisez: ' . implode(', ', $allowedExtensions));
        }

        // Vérifier la taille du fichier (5MB max)
        if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            throw new Exception('L\'image est trop volumineuse (5MB maximum)');
        }

        // Générer un nom unique
        $imageName = uniqid() . '_' . time() . '.' . $fileExtension;
        $uploadPath = $uploadDir . $imageName;

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
            throw new Exception('Erreur lors du téléchargement de l\'image');
        }

        // Supprimer l'ancienne image si elle existe
        if (!empty($existingPlat['image']) && file_exists($uploadDir . $existingPlat['image'])) {
            unlink($uploadDir . $existingPlat['image']);
        }
    }

    // Mise à jour en base de données
    $updateQuery = "UPDATE plats SET nom = :nom, prix = :prix, description = :description, image = :image";
    $params = [
        'nom' => $nom,
        'prix' => $prix,
        'description' => $description,
        'image' => $imageName,
        'id' => $id
    ];

    if ($categorie_id) {
        $updateQuery .= ", categorie_id = :categorie_id";
        $params['categorie_id'] = $categorie_id;
    } else {
        $updateQuery .= ", categorie_id = NULL";
    }

    $updateQuery .= " WHERE id = :id";

    $stmt = $conn->prepare($updateQuery);
    $result = $stmt->execute($params);

    if (!$result) {
        throw new Exception('Erreur lors de la mise à jour du plat');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Plat modifié avec succès !',
        'data' => [
            'id' => $id,
            'nom' => $nom,
            'prix' => $prix,
            'image' => $imageName
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de base de données : ' . $e->getMessage()
    ]);
}
?>