<?php
// Adresse email de réception
$receiving_email_address = 'mulhomabiala29@gmail.com';

// Vérifier si la librairie existe
if (file_exists($php_email_form = '../assets/vendor/php-email-form/php-email-form.php')) {
  include($php_email_form);
} else {
  header('Content-Type: application/json');
  echo json_encode(['status' => 'error', 'message' => 'Impossible de charger la librairie PHP Email Form']);
  exit;
}

$contact = new PHP_Email_Form;
$contact->ajax = true;

// Infos du formulaire
$contact->to = $receiving_email_address;
$contact->from_name = $_POST['name'];
$contact->from_email = $_POST['email'];
$contact->subject = $_POST['subject'];

// Config SMTP (Gmail)
$contact->smtp = array(
  'host' => 'smtp.gmail.com',
  'username' => 'mulhomabiala29@gmail.com',
  'password' => 'khli pyzj ihte qdgu', // mot de passe d’application Gmail
  'port' => '587',
  'encryption' => 'tls'
);

// Ajout du contenu
$contact->add_message($_POST['name'], 'Nom');
$contact->add_message($_POST['email'], 'Email');
$contact->add_message($_POST['message'], 'Message', 10);

// Envoi
$result = $contact->send();

// Réponse JSON propre
header('Content-Type: application/json');

// ✅ Détection succès ou erreur
if ($result === true || stripos($result, 'succès') !== false) {
  echo json_encode([
    'status' => 'success',
    'message' => 'Votre message a été envoyé avec succès !'
  ]);
} else {
  echo json_encode([
    'status' => 'error',
    'message' => 'Une erreur est survenue : ' . $result
  ]);
}
?>
