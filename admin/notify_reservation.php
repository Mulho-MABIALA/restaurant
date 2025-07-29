<?php
// notify_reservation.php
// Ce fichier doit être appelé chaque fois qu'une nouvelle réservation est créée

function addReservationNotification($conn, $reservation_data) {
    try {
        // Préparer le message de notification
        $client_nom = $reservation_data['nom'] ?? 'Client inconnu';
        $date_reservation = $reservation_data['date_reservation'] ?? date('Y-m-d H:i:s');
        $nb_personnes = $reservation_data['nb_personnes'] ?? 1;
        $telephone = $reservation_data['telephone'] ?? '';
        
        // Formater la date pour l'affichage
        $date_formatted = date('d/m/Y à H:i', strtotime($date_reservation));
        
        // Créer le message de notification
        $message = "Nouvelle réservation de {$client_nom} pour {$nb_personnes} personne(s) le {$date_formatted}";
        
        if (!empty($telephone)) {
            $message .= " - Tél: {$telephone}";
        }
        
        // Insérer la notification dans la base de données
        $stmt = $conn->prepare("
            INSERT INTO notifications (
                message, 
                type, 
                titre, 
                date_creation, 
                vue
            ) VALUES (?, ?, ?, NOW(), 0)
        ");
        
        $titre = "📅 Nouvelle Réservation";
        $type = "reservation"; // Nouveau type pour les réservations
        
        $stmt->execute([$message, $type, $titre]);
        
        // Optionnel : Envoyer une notification push ou email
        // sendPushNotification($message);
        // sendEmailNotification($message);
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout de la notification de réservation : " . $e->getMessage());
        return false;
    }
}

// Fonction pour envoyer une notification push (Pusher)
function sendPushNotification($message, $reservation_data = []) {
    // Configuration Pusher (remplacez par vos vraies clés)
    $app_id = 'YOUR_APP_ID';
    $key = 'YOUR_APP_KEY';
    $secret = 'YOUR_APP_SECRET';
    $cluster = 'eu'; // ou votre cluster
    
    try {
        $pusher = new Pusher\Pusher($key, $secret, $app_id, [
            'cluster' => $cluster,
            'useTLS' => true
        ]);
        
        $data = [
            'message' => $message,
            'type' => 'reservation',
            'client' => $reservation_data['nom'] ?? 'Client',
            'date' => $reservation_data['date_reservation'] ?? date('Y-m-d H:i:s'),
            'nb_personnes' => $reservation_data['nb_personnes'] ?? 1,
            'heure' => date('H:i'),
            'timestamp' => time()
        ];
        
        $pusher->trigger('admin-channel', 'new-reservation', $data);
        
        return true;
    } catch (Exception $e) {
        error_log("Erreur Pusher pour réservation : " . $e->getMessage());
        return false;
    }
}

// Fonction d'exemple pour intégrer dans votre système de réservation existant
function handleNewReservation($conn, $reservation_data) {
    // 1. Insérer la réservation dans la base de données (votre code existant)
    // ... votre code de création de réservation ...
    
    // 2. Ajouter la notification
    addReservationNotification($conn, $reservation_data);
    
    // 3. Envoyer la notification push
    sendPushNotification("Nouvelle réservation de " . $reservation_data['nom'], $reservation_data);
    
    return true;
}

// Exemple d'utilisation dans votre formulaire de réservation
/*
if ($_POST['action'] === 'create_reservation') {
    $reservation_data = [
        'nom' => $_POST['nom'],
        'email' => $_POST['email'],
        'telephone' => $_POST['telephone'],
        'date_reservation' => $_POST['date_reservation'],
        'nb_personnes' => $_POST['nb_personnes'],
        'message' => $_POST['message'] ?? ''
    ];
    
    // Insérer la réservation
    $stmt = $conn->prepare("
        INSERT INTO reservations (nom, email, telephone, date_reservation, nb_personnes, message, statut, date_creation) 
        VALUES (?, ?, ?, ?, ?, ?, 'non_lu', NOW())
    ");
    
    if ($stmt->execute([
        $reservation_data['nom'],
        $reservation_data['email'],
        $reservation_data['telephone'],
        $reservation_data['date_reservation'],
        $reservation_data['nb_personnes'],
        $reservation_data['message']
    ])) {
        // Traiter la notification
        handleNewReservation($conn, $reservation_data);
        
        echo json_encode(['success' => true, 'message' => 'Réservation créée avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la création']);
    }
}
*/
?>