// Variables globales
let darkMode = localStorage.getItem('darkMode') === 'true';
let lastMessageCount = 0;
let currentContact = null;
let messagePollingInterval = null;
let typingTimeout = null;
let isTyping = false;

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

function initializeApp() {
    // Mode sombre
    if (darkMode) {
        document.getElementById('body').classList.add('dark-mode');
    }
    
    // URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    currentContact = urlParams.get('contact');
    
    // Initialiser les fonctionnalités
    scrollToBottom();
    checkNewMessages();
    initializeFileUpload();
    initializeKeyboardShortcuts();
    
    // Polling pour nouveaux messages
    startMessagePolling();
    
    // Marquer l'utilisateur comme actif
    updateUserActivity();
    setInterval(updateUserActivity, 60000); // Toutes les minutes
    
    // Gérer la visibilité de la page
    document.addEventListener('visibilitychange', handleVisibilityChange);
    
    // Initialiser les notifications
    requestNotificationPermission();
}

// === GESTION DES MESSAGES ===

function startMessagePolling() {
    // Vérifier immédiatement
    checkNewMessages();
    
    // Puis toutes les 5 secondes si la page est visible
    messagePollingInterval = setInterval(() => {
        if (!document.hidden) {
            checkNewMessages();
        }
    }, 5000);
}

function checkNewMessages() {
    const url = currentContact ? 
        `check_new_messages.php?contact=${currentContact}` : 
        'check_new_messages.php';
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Erreur:', data.error);
                return;
            }
            
            if (currentContact) {
                handleConversationUpdate(data);
            } else {
                handleGlobalUpdate(data);
            }
        })
        .catch(error => console.error('Erreur polling:', error));
}

function handleConversationUpdate(data) {
    if (data.new_messages && data.new_messages > 0) {
        // Recharger uniquement les nouveaux messages
        loadNewMessages();
        showNotification(`${data.new_messages} nouveau(x) message(s)`);
        playNotificationSound();
    }
}

function handleGlobalUpdate(data) {
    const totalUnread = data.total_unread || 0;
    
    if (totalUnread > lastMessageCount) {
        updateUnreadBadge(totalUnread);
        showNotification('Nouveau message reçu!');
        playNotificationSound();
    }
    
    lastMessageCount = totalUnread;
}

function loadNewMessages() {
    // Recharger seulement si nécessaire
    const messagesContainer = document.getElementById('messages-container');
    if (messagesContainer) {
        // Animation de chargement subtile
        messagesContainer.style.opacity = '0.8';
        
        setTimeout(() => {
            location.reload(); // Pour simplifier, on recharge
        }, 500);
    }
}

// === GESTION DE L'INTERFACE ===

function toggleDarkMode() {
    darkMode = !darkMode;
    localStorage.setItem('darkMode', darkMode);
    document.getElementById('body').classList.toggle('dark-mode');
    
    // Animer la transition
    document.body.style.transition = 'all 0.3s ease';
    setTimeout(() => {
        document.body.style.transition = '';
    }, 300);
}

function scrollToBottom(smooth = true) {
    const container = document.getElementById('messages-container');
    if (container) {
        container.scrollTo({
            top: container.scrollHeight,
            behavior: smooth ? 'smooth' : 'auto'
        });
    }
}

function updateUnreadBadge(count) {
    const badges = document.querySelectorAll('.unread-badge');
    badges.forEach(badge => {
        if (count > 0) {
            badge.textContent = count;
            badge.classList.remove('hidden');
            badge.classList.add('new-message-notification');
        } else {
            badge.classList.add('hidden');
            badge.classList.remove('new-message-notification');
        }
    });
}

// === GESTION DES MODALES ===

function openNewMessageModal() {
    const modal = document.getElementById('new-message-modal');
    modal.classList.remove('hidden');
    modal.style.animation = 'fadeIn 0.3s ease-out';
    
    // Focus sur le premier champ
    setTimeout(() => {
        const firstInput = modal.querySelector('select, input, textarea');
        if (firstInput) firstInput.focus();
    }, 100);
}

function closeNewMessageModal() {
    const modal = document.getElementById('new-message-modal');
    modal.style.animation = 'fadeOut 0.3s ease-out';
    
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}

// === GESTION DES FICHIERS ===

function initializeFileUpload() {
    const dropZones = document.querySelectorAll('[data-drop-zone]');
    
    dropZones.forEach(zone => {
        zone.addEventListener('dragover', handleDragOver);
        zone.addEventListener('dragleave', handleDragLeave);
        zone.addEventListener('drop', handleFileDrop);
    });
}

function handleDragOver(e) {
    e.preventDefault();
    e.currentTarget.classList.add('dragover');
}

function handleDragLeave(e) {
    e.preventDefault();
    e.currentTarget.classList.remove('dragover');
}

function handleFileDrop(e) {
    e.preventDefault();
    e.currentTarget.classList.remove('dragover');
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        const fileInput = document.getElementById('attachment');
        if (fileInput) {
            fileInput.files = files;
            showAttachment(fileInput);
        }
    }
}

function showAttachment(input) {
    const preview = document.getElementById('attachment-preview');
    if (!preview) return;
    
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const fileName = file.name;
        const fileSize = formatFileSize(file.size);
        
        preview.innerHTML = `
            <div class="flex items-center space-x-2 p-2 bg-blue-50 dark:bg-blue-900 rounded">
                <i class="fas fa-paperclip text-blue-600"></i>
                <span class="text-sm">${fileName}</span>
                <span class="text-xs text-gray-500">(${fileSize})</span>
                <button type="button" onclick="removeAttachment()" class="text-red-500 hover:text-red-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        preview.classList.remove('hidden');
    } else {
        preview.classList.add('hidden');
    }
}

function removeAttachment() {
    const fileInput = document.getElementById('attachment');
    const preview = document.getElementById('attachment-preview');
    
    if (fileInput) fileInput.value = '';
    if (preview) preview.classList.add('hidden');
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// === GESTION DU CLAVIER ===

function initializeKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + Enter pour envoyer
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            const form = document.getElementById('message-form');
            if (form) form.submit();
        }
        
        // Échap pour fermer les modales
        if (e.key === 'Escape') {
            closeNewMessageModal();
        }
        
        // Ctrl/Cmd + D pour mode sombre
        if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
            e.preventDefault();
            toggleDarkMode();
        }
    });
}

function handleKeyPress(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        document.getElementById('message-form').submit();
    }
    
    // Indicateur de frappe
    handleTypingIndicator();
}

function handleTypingIndicator() {
    if (!isTyping && currentContact) {
        isTyping = true;
        // Ici vous pourriez envoyer un signal "typing" au serveur
        console.log('User started typing...');
    }
    
    // Reset du timeout
    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(() => {
        isTyping = false;
        console.log('User stopped typing...');
    }, 2000);
}

// === NOTIFICATIONS ===

function requestNotificationPermission() {
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                showNotification('Notifications activées!', false);
            }
        });
    }
}

function showNotification(message, persistent = true) {
    // Notification browser
    if ('Notification' in window && Notification.permission === 'granted') {
        const notification = new Notification('Messagerie', {
            body: message,
            icon: '/favicon.ico',
            badge: '/favicon.ico',
            requireInteraction: persistent
        });
        
        notification.onclick = () => {
            window.focus();
            notification.close();
        };
        
        // Auto-fermer après 5 secondes
        if (!persistent) {
            setTimeout(() => notification.close(), 5000);
        }
    }
    
    // Notification dans l'app
    showInAppNotification(message);
}

function showInAppNotification(message) {
    const notification = document.createElement('div');
    notification.className = 'fixed top-4 right-4 bg-blue-600 text-white px-4 py-2 rounded-lg shadow-lg z-50';
    notification.textContent = message;
    notification.style.animation = 'slideIn 0.3s ease-out';
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

function playNotificationSound() {
    // Son discret de notification
    const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmEaOY3R8dOeSy0FIXPDNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmEaOY3R8dOeSy0FIXPDNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmEaOY3R8dOeSy0FIXPDNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmEaOY3R8dOeSy0FIXPDNjVgodDbq2EcBj+a2/LDciUF