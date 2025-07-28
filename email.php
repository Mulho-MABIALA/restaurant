<?php
require_once 'config.php';
require 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use PHPMailer\PHPMailer\PHPMailer;

$commande_id = $_GET['commande_id'] ?? null;
if (!$commande_id) {
    die("ID de commande manquant");
}

// Récupérer les infos commande
$stmt = $conn->prepare("SELECT * FROM commandes WHERE id = ?");
$stmt->execute([$commande_id]);
$commande = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$commande) {
    die("Commande introuvable");
}

// Récupérer les items
$stmt_items = $conn->prepare("SELECT * FROM commande_items WHERE commande_id = ?");
$stmt_items->execute([$commande_id]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

// Générer le PDF comme avant (adapté)
$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);

ob_start();
?>
<h1>Reçu de commande</h1>
<p>Commande n° <?= str_pad($commande_id, 6, '0', STR_PAD_LEFT) ?></p>
<p>Date : <?= date('d/m/Y', strtotime($commande['date_commande'])) ?></p>
<p>Client : <?= htmlspecialchars($commande['nom_client']) ?></p>
<p>Téléphone : <?= htmlspecialchars($commande['telephone']) ?></p>
<p>Adresse : <?= nl2br(htmlspecialchars($commande['adresse'])) ?></p>
<p>Email : <?= htmlspecialchars($commande['email']) ?></p>
<p>Total : <strong><?= number_format($commande['total'], 2) ?> FCFA</strong></p>
<h3>Détails :</h3>
<ul>
<?php foreach ($items as $item): ?>
    <li><?= $item['quantite'] ?> x <?= htmlspecialchars($item['nom_plat']) ?> (<?= number_format($item['prix_unitaire'], 2) ?> FCFA)</li>
<?php endforeach; ?>
</ul>
<?php
$html = ob_get_clean();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$pdfPath = __DIR__ . "recu_commande_{$commande_id}.pdf";
file_put_contents($pdfPath, $dompdf->output());

// Envoi mail
$mail = new PHPMailer(true);
$mail->isSMTP();
$mail->Host = 'smtp.gmail.com';
$mail->SMTPAuth = true;
$mail->Username = 'mulhomabiala29@gmail.com'; // adapte
$mail->Password = 'khli pyzj ihte qdgu';              // adapte
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port = 587;

$mail->setFrom('mulhomabiala29@gmail.com', 'Mulho');
$mail->addAddress($commande['email'], $commande['nom_client']);
$mail->addAttachment($pdfPath);

$mail->isHTML(true);
$mail->Subject = 'Confirmation de votre commande #' . $commande_id;
$mail->Body = "<h2>Merci {$commande['nom_client']} !</h2><p>Votre commande n° {$commande_id} a bien été enregistrée.</p><p>Vous trouverez votre reçu en pièce jointe.</p>";

$mail->send();

echo "Mail envoyé avec succès";
