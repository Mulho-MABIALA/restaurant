
const subscribers_js = `
function toggleSubscriber(id, status) {
    if (confirm('Êtes-vous sûr de vouloir modifier le statut de cet abonné ?')) {
        fetch('ajax/toggle_subscriber.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: id,
                status: status
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erreur lors de la modification du statut.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Erreur lors de la modification du statut.');
        });
    }
}

function deleteSubscriber(id) {
    if (confirm('Êtes-vous sûr de vouloir supprimer définitivement cet abonné ?')) {
        fetch('ajax/delete_subscriber.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: id
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erreur lors de la suppression.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Erreur lors de la suppression.');
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // CSV file validation
    const csvInput = document.querySelector('input[name="csv_file"]');
    if (csvInput) {
        csvInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const fileName = file.name.toLowerCase();
                if (!fileName.endsWith('.csv')) {
                    alert('Veuillez sélectionner un fichier CSV valide.');
                    this.value = '';
                }
            }
        });
    }
    
    // Email validation for manual add
    const emailInput = document.querySelector('input[name="email"]');
    if (emailInput) {
        emailInput.addEventListener('blur', function() {
            const email = this.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                this.style.borderColor = '#dc3545';
                alert('Veuillez saisir une adresse email valide.');
            } else {
                this.style.borderColor = '#e0e0e0';
            }
        });
    }
});`;
