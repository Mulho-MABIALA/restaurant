<?php
require_once '../config.php';
session_start();



$id = $_POST['id'];
$titre = trim($_POST['titre']);
$contenu = trim($_POST['contenu']);

// Vérifier s'il y a un nouveau fichier image
if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
  $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
  $filename = uniqid() . '.' . $ext;
  move_uploaded_file($_FILES['image']['tmp_name'], "../uploads/$filename");

  // Récupérer l'ancienne image pour suppression si besoin
  $stmt = $conn->prepare("SELECT image FROM pages WHERE id = ?");
  $stmt->execute([$id]);
  $old = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($old && $old['image'] && file_exists("../uploads/" . $old['image'])) {
    unlink("../uploads/" . $old['image']);
  }

  // Mise à jour avec nouvelle image
  $stmt = $conn->prepare("UPDATE pages SET titre = ?, contenu = ?, image = ? WHERE id = ?");
  $stmt->execute([$titre, $contenu, $filename, $id]);

} else {
  // Mise à jour sans changer l'image
  $stmt = $conn->prepare("UPDATE pages SET titre = ?, contenu = ? WHERE id = ?");
  $stmt->execute([$titre, $contenu, $id]);
}

header('Location: gestion_contenu.php');
exit;
