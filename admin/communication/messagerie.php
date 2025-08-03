<?php
require_once '../../config.php';
session_start();

$employe_id = $_SESSION['admin_id'];
$selected_contact = $_GET['contact'] ?? null;
$search = $_GET['search'] ?? '';

// Mettre à jour le statut en ligne
$stmt = $conn->prepare("INSERT INTO user_status (user_id, last_activity, is_online) VALUES (?, NOW(), TRUE) ON DUPLICATE KEY UPDATE last_activity = NOW(), is_online = TRUE");
$stmt->execute([$employe_id]);

// Marquer les utilisateurs hors ligne (pas d'activité depuis 5 minutes)
$conn->query("UPDATE user_status SET is_online = FALSE WHERE last_activity < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");

// Récupérer les conversations avec compteur de messages non lus
$conversations_query = "
    SELECT DISTINCT 
        CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END as contact_id,
        e.nom as contact_name,
        MAX(m.created_at) as last_message,
        (SELECT message FROM messages WHERE 
            (sender_id = ? AND receiver_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END) OR
            (receiver_id = ? AND sender_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END)
            ORDER BY created_at DESC LIMIT 1) as last_message_text,
        COUNT(CASE WHEN m.receiver_id = ? AND m.is_read = FALSE THEN 1 END) as unread_count,
        us.is_online
    FROM messages m 
    JOIN employes e ON e.id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END
    LEFT JOIN user_status us ON us.user_id = e.id
    WHERE m.sender_id = ? OR m.receiver_id = ?
    GROUP BY contact_id, contact_name, us.is_online
    ORDER BY last_message DESC
";

$conversations = $conn->prepare($conversations_query);
$conversations->execute([$employe_id, $employe_id, $employe_id, $employe_id, $employe_id, $employe_id, $employe_id, $employe_id, $employe_id]);
$conversations = $conversations->fetchAll(PDO::FETCH_ASSOC);

// Messages de la conversation sélectionnée
$messages = [];
if ($selected_contact) {
    // Marquer les messages comme lus
    $mark_read = $conn->prepare("UPDATE messages SET is_read = TRUE WHERE sender_id = ? AND receiver_id = ? AND is_read = FALSE");
    $mark_read->execute([$selected_contact, $employe_id]);
    
    $messages_query = "
        SELECT m.*, 
               e.nom as sender_name,
               m.sender_id = ? as is_mine
        FROM messages m 
        JOIN employes e ON m.sender_id = e.id 
        WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
    ";
    
    if ($search) {
        $messages_query .= " AND m.message LIKE ?";
    }
    
    $messages_query .= " ORDER BY m.created_at ASC";
    
    $messages = $conn->prepare($messages_query);
    $params = [$employe_id, $employe_id, $selected_contact, $selected_contact, $employe_id];
    if ($search) {
        $params[] = "%$search%";
    }
    $messages->execute($params);
    $messages = $messages->fetchAll(PDO::FETCH_ASSOC);
}

// Liste des employés pour nouveau message
$employes = $conn->prepare("SELECT e.id, e.nom, us.is_online FROM employes e LEFT JOIN user_status us ON e.id = us.user_id WHERE e.id != ?");
$employes->execute([$employe_id]);
$employes = $employes->fetchAll(PDO::FETCH_ASSOC);

// Compter total messages non lus
$unread_total = $conn->prepare("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND is_read = FALSE");
$unread_total->execute([$employe_id]);
$unread_total = $unread_total->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messagerie - Système Complet</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .conversation-item:hover { background-color: #f3f4f6; }
        .message-bubble { animation: slideIn 0.3s ease-out; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .online-indicator { width: 10px; height: 10px; background-color: #10b981; border-radius: 50%; position: absolute; bottom: 0; right: 0; border: 2px solid white; }
        .unread-badge { background: linear-gradient(45deg, #ef4444, #dc2626); }
        .dark-mode { background-color: #1f2937; color: #f9fafb; }
        .dark-mode .bg-white { background-color: #374151; }
        .dark-mode .bg-gray-100 { background-color: #4b5563; }
        .dark-mode .text-gray-500 { color: #9ca3af; }
        .dark-mode .border { border-color: #4b5563; }
    </style>
</head>
<body class="bg-gray-50 transition-colors duration-300" id="body">



<div class="min-h-screen">
    <!-- Header -->
    <div class="bg-white dark:bg-gray-800 shadow-sm border-b p-4 flex justify-between items-center">
        <div class="flex items-center space-x-4">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center">
                <i class="fas fa-comments mr-2 text-blue-600"></i>
                Messagerie
                <?php if ($unread_total > 0): ?>
                    <span class="ml-2 bg-red-500 text-white rounded-full px-2 py-1 text-xs unread-badge">
                        <?= $unread_total ?>
                    </span>
                <?php endif; ?>
            </h1>
        </div>
        
        <div class="flex items-center space-x-4">
            <!-- Recherche -->
            <form method="get" class="relative">
                <input type="hidden" name="contact" value="<?= htmlspecialchars($selected_contact ?? '') ?>">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Rechercher..." 
                       class="pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            </form>
            
            <!-- Mode sombre -->
            <button onclick="toggleDarkMode()" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                <i class="fas fa-moon dark:hidden"></i>
                <i class="fas fa-sun hidden dark:inline"></i>
            </button>
            
            <!-- Notifications -->
            <button onclick="requestNotificationPermission()" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700" title="Activer les notifications">
                <i class="fas fa-bell"></i>
            </button>
        </div>
    </div>

    <div class="flex h-screen">
        <!-- Liste des conversations -->
        <div class="w-1/3 bg-white dark:bg-gray-800 border-r overflow-y-auto">
            <div class="p-4 border-b">
                <button onclick="openNewMessageModal()" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center justify-center">
                    <i class="fas fa-plus mr-2"></i>
                    Nouveau message
                </button>
            </div>
            
            <div class="divide-y dark:divide-gray-700">
                <?php foreach ($conversations as $conv): ?>
                    <a href="?contact=<?= $conv['contact_id'] ?>" 
                       class="conversation-item block p-4 hover:bg-gray-50 dark:hover:bg-gray-700 <?= $selected_contact == $conv['contact_id'] ? 'bg-blue-50 dark:bg-blue-900' : '' ?>">
                        <div class="flex items-center space-x-3">
                            <div class="relative">
                                <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white font-bold">
                                    <?= strtoupper(substr($conv['contact_name'], 0, 2)) ?>
                                </div>
                                <?php if ($conv['is_online']): ?>
                                    <div class="online-indicator"></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-center">
                                    <h3 class="font-semibold text-gray-900 dark:text-white truncate">
                                        <?= htmlspecialchars($conv['contact_name']) ?>
                                    </h3>
                                    <?php if ($conv['unread_count'] > 0): ?>
                                        <span class="bg-red-500 text-white rounded-full px-2 py-1 text-xs unread-badge">
                                            <?= $conv['unread_count'] ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                                    <?= htmlspecialchars(substr($conv['last_message_text'] ?? '', 0, 50)) ?>
                                    <?= strlen($conv['last_message_text'] ?? '') > 50 ? '...' : '' ?>
                                </p>
                                
                                <p class="text-xs text-gray-400 mt-1">
                                    <?= date('d/m/Y H:i', strtotime($conv['last_message'])) ?>
                                </p>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Zone de messages -->
        <div class="flex-1 flex flex-col">
            <?php if ($selected_contact): ?>
                <?php
                $contact_info = $conn->prepare("SELECT nom FROM employes WHERE id = ?");
                $contact_info->execute([$selected_contact]);
                $contact_name = $contact_info->fetch()['nom'];
                ?>
                
                <!-- Header de conversation -->
                <div class="bg-white dark:bg-gray-800 border-b p-4 flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-gradient-to-r from-green-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold">
                            <?= strtoupper(substr($contact_name, 0, 2)) ?>
                        </div>
                        <div>
                            <h2 class="font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($contact_name) ?></h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                <i class="fas fa-circle text-green-500 mr-1"></i>En ligne
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <?php if ($search): ?>
                            <a href="?contact=<?= $selected_contact ?>" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                                <i class="fas fa-times"></i> Effacer recherche
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Messages -->
                <div id="messages-container" class="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50 dark:bg-gray-900">
                    <?php foreach ($messages as $msg): ?>
                        <div class="message-bubble flex <?= $msg['is_mine'] ? 'justify-end' : 'justify-start' ?>">
                            <div class="max-w-xs lg:max-w-md px-4 py-2 rounded-lg <?= $msg['is_mine'] ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white' ?>">
                                <?php if (!$msg['is_mine']): ?>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1"><?= htmlspecialchars($msg['sender_name']) ?></p>
                                <?php endif; ?>
                                
                                <p class="text-sm"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                                
                                <?php if ($msg['attachment_path']): ?>
                                    <div class="mt-2 p-2 bg-black bg-opacity-10 rounded">
                                        <a href="<?= htmlspecialchars($msg['attachment_path']) ?>" 
                                           download="<?= htmlspecialchars($msg['attachment_name']) ?>"
                                           class="flex items-center text-sm hover:underline">
                                            <i class="fas fa-paperclip mr-2"></i>
                                            <?= htmlspecialchars($msg['attachment_name']) ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <p class="text-xs mt-1 opacity-70">
                                    <?= date('H:i', strtotime($msg['created_at'])) ?>
                                    <?php if ($msg['is_mine']): ?>
                                        <i class="fas fa-check ml-1"></i>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Formulaire d'envoi -->
                <div class="bg-white dark:bg-gray-800 border-t p-4">
                    <form id="message-form" method="post" action="messagerie_send.php" enctype="multipart/form-data" class="flex items-end space-x-3">
                        <input type="hidden" name="receiver_id" value="<?= $selected_contact ?>">
                        
                        <div class="flex-1">
                            <div class="flex items-center space-x-2 mb-2">
                                <label for="attachment" class="cursor-pointer text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                                    <i class="fas fa-paperclip"></i>
                                </label>
                                <input type="file" id="attachment" name="attachment" class="hidden" onchange="showAttachment(this)">
                                <div id="attachment-preview" class="hidden text-sm text-gray-600 dark:text-gray-400"></div>
                            </div>
                            
                            <textarea name="message" 
                                      id="message-input"
                                      rows="2" 
                                      placeholder="Tapez votre message..." 
                                      class="w-full border rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                      onkeypress="handleKeyPress(event)"
                                      required></textarea>
                        </div>
                        
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg flex items-center">
                            <i class="fas fa-paper-plane mr-2"></i>
                            Envoyer
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <!-- État vide -->
                <div class="flex-1 flex items-center justify-center bg-gray-50 dark:bg-gray-900">
                    <div class="text-center">
                        <i class="fas fa-comments text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-600 dark:text-gray-400 mb-2">Sélectionnez une conversation</h3>
                        <p class="text-gray-500 dark:text-gray-500">Choisissez une conversation dans la liste pour commencer à discuter</p>
                        <button onclick="openNewMessageModal()" class="mt-4 bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                            <i class="fas fa-plus mr-2"></i>Nouveau message
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal nouveau message -->
<div id="new-message-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-md mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Nouveau message</h3>
            <button onclick="closeNewMessageModal()" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="post" action="messagerie_send.php" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Destinataire</label>
                <select name="receiver_id" required class="w-full border rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="">Choisir un destinataire</option>
                    <?php foreach ($employes as $e): ?>
                        <option value="<?= $e['id'] ?>">
                            <?= htmlspecialchars($e['nom']) ?>
                            <?php if ($e['is_online']): ?>
                                <span class="text-green-500">●</span>
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Message</label>
                <textarea name="message" rows="4" placeholder="Votre message..." 
                          class="w-full border rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white" 
                          required></textarea>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Pièce jointe (optionnel)</label>
                <input type="file" name="attachment" class="w-full border rounded-lg p-3 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeNewMessageModal()" class="px-4 py-2 text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                    Annuler
                </button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                    <i class="fas fa-paper-plane mr-2"></i>Envoyer
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Variables globales
let darkMode = localStorage.getItem('darkMode') === 'true';
let lastMessageCount = 0;

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    if (darkMode) {
        document.getElementById('body').classList.add('dark-mode');
    }
    
    scrollToBottom();
    checkNewMessages();
    setInterval(checkNewMessages, 10000); // Vérifier toutes les 10 secondes
    
    // Marquer l'utilisateur comme actif
    setInterval(updateUserActivity, 60000); // Toutes les minutes
});

// Mode sombre
function toggleDarkMode() {
    darkMode = !darkMode;
    localStorage.setItem('darkMode', darkMode);
    document.getElementById('body').classList.toggle('dark-mode');
}

// Gestion des modales
function openNewMessageModal() {
    document.getElementById('new-message-modal').classList.remove('hidden');
}

function closeNewMessageModal() {
    document.getElementById('new-message-modal').classList.add('hidden');
}

// Gestion des pièces jointes
function showAttachment(input) {
    const preview = document.getElementById('attachment-preview');
    if (input.files && input.files[0]) {
        preview.textContent = '📎 ' + input.files[0].name;
        preview.classList.remove('hidden');
    } else {
        preview.classList.add('hidden');
    }
}

// Envoi avec Entrée
function handleKeyPress(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        document.getElementById('message-form').submit();
    }
}

// Scroll automatique
function scrollToBottom() {
    const container = document.getElementById('messages-container');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

// Vérification de nouveaux messages
function checkNewMessages() {
    const currentContact = new URLSearchParams(window.location.search).get('contact');
    
    fetch('check_new_messages.php?contact=' + (currentContact || ''))
        .then(response => response.json())
        .then(data => {
            if (data.new_messages && data.new_messages > lastMessageCount) {
                if (currentContact) {
                    // Recharger les messages de la conversation
                    location.reload();
                } else {
                    // Afficher une notification
                    showNotification('Nouveau message reçu!');
                }
                lastMessageCount = data.new_messages;
            }
        })
        .catch(error => console.error('Erreur:', error));
}

// Notifications
function requestNotificationPermission() {
    if ('Notification' in window) {
        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                showNotification('Notifications activées!');
            }
        });
    }
}

function showNotification(message) {
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification('Messagerie', {
            body: message,
            icon: '/favicon.ico'
        });
    }
}

// Activité utilisateur
function updateUserActivity() {
    fetch('update_activity.php', { method: 'POST' });
}

// Fermer modal en cliquant à l'extérieur
document.addEventListener('click', function(event) {
    const modal = document.getElementById('new-message-modal');
    if (event.target === modal) {
        closeNewMessageModal();
    }
});
</script>

</body>
</html>