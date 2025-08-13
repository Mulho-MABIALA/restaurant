<?php
session_start();
require_once '../../config.php';

// Vérification de l'authentification
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$employe_id = $_SESSION['admin_id'];
$selected_contact = $_GET['contact'] ?? null;
$search = $_GET['search'] ?? '';

// Mettre à jour le statut en ligne
$stmt = $conn->prepare("INSERT INTO user_status (user_id, last_activity, is_online) VALUES (?, NOW(), TRUE) ON DUPLICATE KEY UPDATE last_activity = NOW(), is_online = TRUE");
$stmt->execute([$employe_id]);

// Marquer les utilisateurs hors ligne (pas d'activité depuis 5 minutes)
$conn->query("UPDATE user_status SET is_online = FALSE WHERE last_activity < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");

// SÉCURITÉ : Récupérer UNIQUEMENT les conversations où l'utilisateur connecté est DESTINATAIRE
$conversations_query = "
    SELECT DISTINCT 
        m.sender_id as contact_id,
        e.nom as contact_name,
        e.prenom as contact_prenom,
        MAX(m.created_at) as last_message,
        (SELECT message FROM messages WHERE 
            sender_id = m.sender_id AND receiver_id = ?
            ORDER BY created_at DESC LIMIT 1) as last_message_text,
        COUNT(CASE WHEN m.receiver_id = ? AND m.is_read = FALSE THEN 1 END) as unread_count,
        us.is_online
    FROM messages m 
    JOIN employes e ON e.id = m.sender_id
    LEFT JOIN user_status us ON us.user_id = e.id
    WHERE m.receiver_id = ?
    GROUP BY m.sender_id, e.nom, e.prenom, us.is_online
    ORDER BY last_message DESC
";

$conversations = $conn->prepare($conversations_query);
$conversations->execute([$employe_id, $employe_id, $employe_id]);
$conversations = $conversations->fetchAll(PDO::FETCH_ASSOC);

// SÉCURITÉ : Messages de la conversation sélectionnée - UNIQUEMENT si l'utilisateur est participant
$messages = [];
if ($selected_contact) {
    // Vérifier que l'utilisateur a reçu au moins un message de ce contact
    $access_check = $conn->prepare("
        SELECT COUNT(*) as count FROM messages 
        WHERE sender_id = ? AND receiver_id = ?
    ");
    $access_check->execute([$selected_contact, $employe_id]);
    $has_access = $access_check->fetch()['count'] > 0;
    
    if (!$has_access) {
        // Rediriger si l'utilisateur tente d'accéder à une conversation qui ne lui appartient pas
        header('Location: messagerie.php');
        exit();
    }
    
    // Marquer les messages comme lus UNIQUEMENT ceux destinés à l'utilisateur connecté
    $mark_read = $conn->prepare("UPDATE messages SET is_read = TRUE WHERE sender_id = ? AND receiver_id = ? AND is_read = FALSE");
    $mark_read->execute([$selected_contact, $employe_id]);
    
    // SÉCURITÉ : Récupérer UNIQUEMENT les messages entre ces 2 utilisateurs
    $messages_query = "
        SELECT m.*, 
               e.nom as sender_name,
               m.sender_id = ? as is_mine
        FROM messages m 
        JOIN employes e ON m.sender_id = e.id 
        WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
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

// Liste des employés pour nouveau message (exclure l'utilisateur actuel)
$employes = $conn->prepare("SELECT e.id, e.nom, e.prenom, us.is_online FROM employes e LEFT JOIN user_status us ON e.id = us.user_id WHERE e.id != ?");
$employes->execute([$employe_id]);
$employes = $employes->fetchAll(PDO::FETCH_ASSOC);

// Compter total messages non lus UNIQUEMENT pour l'utilisateur connecté
$unread_total = $conn->prepare("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND is_read = FALSE");
$unread_total->execute([$employe_id]);
$unread_total = $unread_total->fetch()['total'];

// Récupérer les informations de l'utilisateur connecté
$current_user = $conn->prepare("SELECT nom, prenom FROM employes WHERE id = ?");
$current_user->execute([$employe_id]);
$current_user_info = $current_user->fetch();
$current_user_name = $current_user_info['nom'];
$current_user_prenom = $current_user_info['prenom'] ?? '';
$display_name = !empty($current_user_prenom) ? $current_user_prenom . ' ' . $current_user_name : $current_user_name;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messagerie Privée - <?= htmlspecialchars($display_name) ?></title>
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
        .image-message { max-width: 300px; max-height: 200px; border-radius: 8px; cursor: pointer; }
        .security-notice { background: linear-gradient(45deg, #10b981, #059669); }
    </style>
</head>
<body class="bg-gray-50 transition-colors duration-300" id="body">

<div class="min-h-screen">
    <!-- Header avec indication de sécurité -->
    <div class="bg-white dark:bg-gray-800 shadow-sm border-b p-4 flex justify-between items-center">
        <div class="flex items-center space-x-4">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center">
                <i class="fas fa-shield-alt mr-2 text-green-600"></i>
                Messagerie Privée
                <?php if ($unread_total > 0): ?>
                    <span class="ml-2 bg-red-500 text-white rounded-full px-2 py-1 text-xs unread-badge">
                        <?= $unread_total ?>
                    </span>
                <?php endif; ?>
            </h1>
            <div class="security-notice text-white px-3 py-1 rounded-full text-sm">
                <i class="fas fa-lock mr-1"></i>
                Connecté : <?= htmlspecialchars($display_name) ?>
            </div>
        </div>
        
        <div class="flex items-center space-x-4">
            <!-- Recherche -->
            <form method="get" class="relative">
                <input type="hidden" name="contact" value="<?= htmlspecialchars($selected_contact ?? '') ?>">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Rechercher dans vos messages..." 
                       class="pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            </form>
            
            <!-- Mode sombre -->
            <button onclick="toggleDarkMode()" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                <i class="fas fa-moon dark:hidden"></i>
                <i class="fas fa-sun hidden dark:inline"></i>
            </button>
            
            <!-- Déconnexion -->
            <a href="logout.php" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-red-600" title="Se déconnecter">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>

    <div class="flex h-screen">
        <!-- Liste des conversations (uniquement celles où l'utilisateur participe) -->
        <div class="w-1/3 bg-white dark:bg-gray-800 border-r overflow-y-auto">
            <div class="p-4 border-b">
                <button onclick="openNewMessageModal()" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center justify-center">
                    <i class="fas fa-plus mr-2"></i>
                    Nouveau message privé
                </button>
            </div>
            
            <?php if (empty($conversations)): ?>
                <div class="p-4 text-center text-gray-500 dark:text-gray-400">
                    <i class="fas fa-inbox text-4xl mb-2"></i>
                    <p>Aucune conversation</p>
                    <p class="text-sm">Commencez une nouvelle conversation</p>
                </div>
            <?php else: ?>
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
            <?php endif; ?>
        </div>

        <!-- Zone de messages -->
        <div class="flex-1 flex flex-col">
            <?php if ($selected_contact): ?>
                <?php
                $contact_info = $conn->prepare("SELECT nom, prenom FROM employes WHERE id = ?");
                $contact_info->execute([$selected_contact]);
                $contact_data = $contact_info->fetch();
                $contact_name = $contact_data['nom'];
                $contact_prenom = $contact_data['prenom'] ?? '';
                $contact_display_name = !empty($contact_prenom) ? $contact_prenom . ' ' . $contact_name : $contact_name;
                ?>
                
                <!-- Header de conversation -->
                <div class="bg-white dark:bg-gray-800 border-b p-4 flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-gradient-to-r from-green-500 to-blue-600 rounded-full flex items-center justify-center text-white font-bold">
                            <?= strtoupper(substr($contact_display_name, 0, 2)) ?>
                        </div>
                        <div>
                            <h2 class="font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($contact_display_name) ?></h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                <i class="fas fa-lock mr-1 text-green-500"></i>Conversation privée
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
                            <div class="max-w-xs lg:max-w-md">
                                <!-- Nom de l'expéditeur TOUJOURS visible -->
                                <div class="text-xs <?= $msg['is_mine'] ? 'text-right text-blue-600' : 'text-left text-gray-600 dark:text-gray-400' ?> mb-1">
                                    <strong><?= htmlspecialchars($msg['sender_name']) ?></strong>
                                    <span class="ml-1"><?= date('H:i', strtotime($msg['created_at'])) ?></span>
                                </div>
                                
                                <div class="px-4 py-2 rounded-lg <?= $msg['is_mine'] ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white' ?>">
                                    <!-- Message texte -->
                                    <?php if ($msg['message']): ?>
                                        <p class="text-sm"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                                    <?php endif; ?>
                                    
                                    <!-- Image attachée -->
                                    <?php if ($msg['attachment_path'] && $msg['attachment_name']): ?>
                                        <?php
                                        $file_ext = strtolower(pathinfo($msg['attachment_name'], PATHINFO_EXTENSION));
                                        $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                        ?>
                                        
                                        <?php if ($is_image): ?>
                                            <div class="mt-2">
                                                <img src="<?= htmlspecialchars($msg['attachment_path']) ?>" 
                                                     alt="<?= htmlspecialchars($msg['attachment_name']) ?>"
                                                     class="image-message"
                                                     onclick="openImageModal('<?= htmlspecialchars($msg['attachment_path']) ?>', '<?= htmlspecialchars($msg['attachment_name']) ?>')">
                                                <p class="text-xs mt-1 opacity-70">
                                                    <?= htmlspecialchars($msg['attachment_name']) ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <!-- Indicateur de lecture -->
                                    <p class="text-xs mt-1 opacity-70 <?= $msg['is_mine'] ? 'text-right' : '' ?>">
                                        <?php if ($msg['is_mine']): ?>
                                            <i class="fas fa-check ml-1"></i>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Formulaire d'envoi (IMAGES UNIQUEMENT) -->
                <div class="bg-white dark:bg-gray-800 border-t p-4">
                    <form id="message-form" method="post" action="messagerie_send.php" enctype="multipart/form-data" class="flex items-end space-x-3">
                        <input type="hidden" name="receiver_id" value="<?= $selected_contact ?>">
                        
                        <div class="flex-1">
                            <div class="flex items-center space-x-2 mb-2">
                                <label for="attachment" class="cursor-pointer bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded-lg text-sm">
                                    <i class="fas fa-image mr-1"></i>Image uniquement
                                </label>
                                <input type="file" id="attachment" name="attachment" class="hidden" 
                                       accept="image/*" onchange="showImagePreview(this)">
                                <div id="attachment-preview" class="hidden text-sm text-gray-600 dark:text-gray-400"></div>
                            </div>
                            
                            <textarea name="message" 
                                      id="message-input"
                                      rows="2" 
                                      placeholder="Tapez votre message privé..." 
                                      class="w-full border rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                      onkeypress="handleKeyPress(event)"></textarea>
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
                        <i class="fas fa-shield-alt text-6xl text-green-300 dark:text-green-600 mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-600 dark:text-gray-400 mb-2">Messagerie Privée et Sécurisée</h3>
                        <p class="text-gray-500 dark:text-gray-500 mb-2">Vos conversations sont privées et chiffrées</p>
                        <p class="text-sm text-gray-400 dark:text-gray-600 mb-4">Seules les images sont autorisées en pièce jointe</p>
                        <button onclick="openNewMessageModal()" class="mt-4 bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                            <i class="fas fa-plus mr-2"></i>Nouveau message privé
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
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                <i class="fas fa-lock mr-2 text-green-600"></i>Nouveau message privé
            </h3>
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
                        <?php 
                        $emp_display_name = !empty($e['prenom']) ? $e['prenom'] . ' ' . $e['nom'] : $e['nom']; 
                        ?>
                        <option value="<?= $e['id'] ?>">
                            <?= htmlspecialchars($emp_display_name) ?>
                            <?php if ($e['is_online']): ?>
                                🟢
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Message</label>
                <textarea name="message" rows="4" placeholder="Votre message privé..." 
                          class="w-full border rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"></textarea>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    <i class="fas fa-image mr-1 text-green-600"></i>Image uniquement
                </label>
                <input type="file" name="attachment" accept="image/*" 
                       class="w-full border rounded-lg p-3 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                <p class="text-xs text-gray-500 mt-1">Formats acceptés : JPG, PNG, GIF, WebP</p>
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

<!-- Modal affichage image -->
<div id="image-modal" class="hidden fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50" onclick="closeImageModal()">
    <div class="max-w-4xl max-h-4xl p-4">
        <img id="modal-image" src="" alt="" class="max-w-full max-h-full rounded-lg">
        <p id="modal-image-name" class="text-white text-center mt-2"></p>
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
    setInterval(checkNewMessages, 10000);
    setInterval(updateUserActivity, 60000);
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

// Prévisualisation d'image
function showImagePreview(input) {
    const preview = document.getElementById('attachment-preview');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.type.startsWith('image/')) {
            preview.innerHTML = `<i class="fas fa-image text-green-600"></i> ${file.name}`;
            preview.classList.remove('hidden');
        } else {
            alert('Seules les images sont autorisées !');
            input.value = '';
            preview.classList.add('hidden');
        }
    } else {
        preview.classList.add('hidden');
    }
}

// Modal d'affichage d'image
function openImageModal(src, name) {
    document.getElementById('modal-image').src = src;
    document.getElementById('modal-image-name').textContent = name;
    document.getElementById('image-modal').classList.remove('hidden');
}

function closeImageModal() {
    document.getElementById('image-modal').classList.add('hidden');
}

// Envoi avec Entrée
function handleKeyPress(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        const messageInput = document.getElementById('message-input');
        if (messageInput.value.trim() !== '') {
            document.getElementById('message-form').submit();
        }
    }
}

// Scroll automatique
function scrollToBottom() {
    const container = document.getElementById('messages-container');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

// Vérification de nouveaux messages (sécurisée)
function checkNewMessages() {
    const currentContact = new URLSearchParams(window.location.search).get('contact');
    
    fetch('check_new_messages.php?contact=' + (currentContact || ''))
        .then(response => response.json())
        .then(data => {
            if (data.new_messages && data.new_messages > lastMessageCount) {
                if (currentContact) {
                    location.reload();
                } else {
                    showNotification('Nouveau message privé reçu!');
                }
                lastMessageCount = data.new_messages;
            }
        })
        .catch(error => console.error('Erreur:', error));
}

// Notifications
function showNotification(message) {
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification('Messagerie Privée', {
            body: message,
            icon: '/favicon.ico'
        });
    }
}

// Activité utilisateur
function updateUserActivity() {
    fetch('update_activity.php', { method: 'POST' });
}

// Fermer modales en cliquant à l'extérieur
document.addEventListener('click', function(event) {
    const newMessageModal = document.getElementById('new-message-modal');
    if (event.target === newMessageModal) {
        closeNewMessageModal();
    }
});

// Validation côté client pour les images
document.addEventListener('change', function(event) {
    if (event.target.type === 'file' && event.target.accept === 'image/*') {
        const file = event.target.files[0];
        if (file) {
            // Vérifier la taille (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('L\'image est trop volumineuse. Maximum 5MB autorisé.');
                event.target.value = '';
                return;
            }
            
            // Vérifier le type
            if (!file.type.startsWith('image/')) {
                alert('Seules les images sont autorisées !');
                event.target.value = '';
                return;
            }
        }
    }
});

// Prévention des tentatives d'accès non autorisé
window.addEventListener('beforeunload', function() {
    // Nettoyer les données sensibles du cache
    if (typeof Storage !== "undefined") {
        sessionStorage.clear();
    }
});
</script>

</body>
</html>