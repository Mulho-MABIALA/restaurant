<?php

header('Content-Type: application/json');
require_once('../config.php');

// 1. Connexion PDO
try {
    $conn = new PDO("mysql:host=localhost;dbname=restaurant;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    error_log("DB connection error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Service temporairement indisponible']);
    exit;
}

// 2. Nettoyage des données
$inputs = [
    'name' => trim($_POST['name'] ?? ''),
    'email' => filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL),
    'phone' => preg_replace('/[^0-9+]/', '', $_POST['phone'] ?? ''),
    'date' => $_POST['date'] ?? '',
    'time' => $_POST['time'] ?? '',
    'people' => max(1, min((int)($_POST['people'] ?? 1), 20)), // ✅ Corrigé
    'message' => trim($_POST['message'] ?? '')
];

// 3. Validation
$errors = [];
if (empty($inputs['name'])) $errors[] = "Le nom est requis";
if (!filter_var($inputs['email'], FILTER_VALIDATE_EMAIL)) $errors[] = "Email invalide";
if (strlen($inputs['phone']) < 8) $errors[] = "Numéro de téléphone invalide";
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $inputs['date'])) $errors[] = "Date invalide";
if (!preg_match('/^\d{2}:\d{2}$/', $inputs['time'])) $errors[] = "Heure invalide";

if ($errors) {
    echo json_encode(['status' => 'error', 'message' => implode(" • ", $errors)]);
    exit;
}

// 4. Vérification horaires ouverture
function estOuvert($date, $heure, $conn) {
    $jours = [
        'Monday' => 'Lundi', 'Tuesday' => 'Mardi', 'Wednesday' => 'Mercredi',
        'Thursday' => 'Jeudi', 'Friday' => 'Vendredi', 'Saturday' => 'Samedi', 'Sunday' => 'Dimanche'
    ];
    $jourFr = $jours[date('l', strtotime($date))];

    $stmt = $conn->prepare("SELECT heure_ouverture, heure_fermeture, ferme FROM horaires_ouverture WHERE jour = ?");
    $stmt->execute([$jourFr]);
    $h = $stmt->fetch();

    if (!$h || $h['ferme']) return false;

    $ts = strtotime($heure);
    $open = strtotime($h['heure_ouverture']);
    $close = strtotime($h['heure_fermeture']);

    if ($close < $open) return ($ts >= $open || $ts <= $close);
    return ($ts >= $open && $ts <= $close);
}

if (!estOuvert($inputs['date'], $inputs['time'], $conn)) {
    echo json_encode(['status' => 'error', 'message' => 'Le restaurant est fermé à cette heure']);
    exit;
}

// 5. Vérifier doublons
$stmt = $conn->prepare("SELECT id FROM reservations WHERE date_reservation = ? AND heure_reservation = ? AND email = ?");
$stmt->execute([$inputs['date'], $inputs['time'], $inputs['email']]);
if ($stmt->fetch()) {
    echo json_encode(['status' => 'error', 'message' => 'Vous avez déjà réservé à cette heure']);
    exit;
}

// 6. Vérifier capacité
$stmt = $conn->prepare("SELECT SUM(personnes) FROM reservations WHERE date_reservation = ? AND heure_reservation = ?");
$stmt->execute([$inputs['date'], $inputs['time']]);
$total = (int)$stmt->fetchColumn();
if ($total + $inputs['people'] > 50) {
    echo json_encode(['status' => 'error', 'message' => 'Capacité maximale atteinte pour ce créneau']);
    exit;
}

// 7. Insertion
$stmt = $conn->prepare("INSERT INTO reservations (nom, email, telephone, date_reservation, heure_reservation, personnes, message, statut) VALUES (?, ?, ?, ?, ?, ?, ?, 'en_attente')");
$stmt->execute([
    $inputs['name'], $inputs['email'], $inputs['phone'],
    $inputs['date'], $inputs['time'], $inputs['people'], $inputs['message']
]);

// 8. Envoi email (si librairie chargée)
$php_email_form_path = '../assets/vendor/php-email-form/php-email-form.php';
if (file_exists($php_email_form_path)) {
    require_once($php_email_form_path);
    if (class_exists('PHP_Email_Form')) {
        $mail = new PHP_Email_Form;
        $mail->ajax = true;
        $mail->to = 'mulhomabiala29@gmail.com';
        $mail->from_name = $inputs['name'];
        $mail->from_email = $inputs['email'];
        $mail->subject = 'Nouvelle réservation';

        $mail->smtp = [
            'host' => 'smtp.gmail.com',
            'username' => 'mulhomabiala29@gmail.com',
            'password' => 'khli pyzj ihte qdgu', // mot de passe d’application
            'port' => '587',
            'encryption' => 'tls'
        ];

        $mail->add_message($inputs['name'], 'Nom');
        $mail->add_message($inputs['email'], 'Email');
        $mail->add_message($inputs['phone'], 'Téléphone');
        $mail->add_message($inputs['date'] . ' à ' . $inputs['time'], 'Date et heure');
        $mail->add_message($inputs['people'], 'Nombre de personnes');
        $mail->add_message($inputs['message'], 'Message');

        @$mail->send();
    }
}

// 9. Réponse
echo json_encode([
    'status' => 'success',
    'message' => 'Votre réservation a bien été enregistrée. Vous recevrez une confirmation par email.'
]);
