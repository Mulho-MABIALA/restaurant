<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php'; // si tu utilises Composer
// sinon, inclure directement class.phpmailer.php et class.smtp.php

$mail = new PHPMailer(true);

try {
    // Config serveur SMTP
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';   // Serveur SMTP
    $mail->SMTPAuth   = true;
    $mail->Username   = 'mulhomabiala29@gmail.com';   // Ton email
    $mail->Password   = 'khli pyzj ihte qdgu'; // Mot de passe d’application (pas le vrai mot de passe Gmail)
    $mail->SMTPSecure = 'tls'; 
    $mail->Port       = 587;

    // Destinataire
    $mail->setFrom('tonemail@gmail.com', 'Test PHPMailer');
    $mail->addAddress('destinataire@example.com'); // Email de test

    // Contenu
    $mail->isHTML(true);
    $mail->Subject = 'Test PHPMailer';
    $mail->Body    = 'Bravo 🎉, ton SMTP fonctionne !';
    $mail->AltBody = 'Bravo, ton SMTP fonctionne ! (version texte)';

    $mail->send();
    echo "✅ Message envoyé avec succès";
} catch (Exception $e) {
    echo "❌ Erreur : {$mail->ErrorInfo}";
}
