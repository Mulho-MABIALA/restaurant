<?php

$receiving_email_address = 'mulhomabiala29@gmail.com';

if (file_exists($php_email_form = '../assets/vendor/php-email-form/php-email-form.php')) {
  include($php_email_form);
} else {
  die('Unable to load the "PHP Email Form" Library!');
}

$contact = new PHP_Email_Form;
$contact->ajax = true;

$contact->to = $receiving_email_address;
$contact->from_name = $_POST['name'];
$contact->from_email = $_POST['email'];
$contact->subject = $_POST['subject'];

// ✅ Correction ici
$contact->smtp = array(
  'host' => 'smtp.gmail.com',
  'username' => 'mulhomabiala29@gmail.com',
  'password' => 'khli pyzj ihte qdgu', // mot de passe d'application Gmail
  'port' => '587',
  'encryption' => 'tls'
);

$contact->add_message($_POST['name'], 'From');
$contact->add_message($_POST['email'], 'Email');
$contact->add_message($_POST['message'], 'Message', 10);

echo $contact->send();
?>
