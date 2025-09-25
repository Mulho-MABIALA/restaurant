// ===== ASSETS/JS/ADMIN.JS =====

const admin_js = `
document.addEventListener('DOMContentLoaded', function() {
    // Modal management
    const modal = document.getElementById('editModal');
    const editButtons = document.querySelectorAll('.edit-template');
    const closeButtons = document.querySelectorAll('.close, .close-modal');
    
    // Open edit modal
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const content = this.dataset.content;
            
            document.getElementById('edit_template_id').value = id;
            document.getElementById('edit_template_name').value = name;
            document.getElementById('edit_template_content').value = content;
            
            modal.style.display = 'block';
        });
    });
    
    // Close modal
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            modal.style.display = 'none';
        });
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
    
    // Form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = '#dc3545';
                    isValid = false;
                } else {
                    field.style.borderColor = '#e0e0e0';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs obligatoires.');
            }
        });
    });
    
    // Auto-resize textareas
    const textareas = document.querySelectorAll('textarea');
    textareas.forEach(textarea => {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    });
});`;

// ===== ASSETS/JS/NEWSLETTER.JS =====

const newsletter_js = `
function previewTemplate(templateId) {
    if (templateId) {
        const previewFrame = document.getElementById('preview_frame');
        previewFrame.src = 'preview_email.php?template_id=' + templateId;
        document.getElementById('template_preview').style.display = 'block';
    } else {
        document.getElementById('template_preview').style.display = 'none';
    }
}

function toggleSchedule(show) {
    const scheduleSection = document.getElementById('schedule_section');
    const sendImmediately = document.querySelector('input[name="send_immediately"]');
    
    if (show) {
        scheduleSection.style.display = 'block';
        if (sendImmediately) sendImmediately.checked = false;
    } else {
        scheduleSection.style.display = 'none';
        if (sendImmediately) sendImmediately.checked = true;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize template preview if template is pre-selected
    const templateSelect = document.querySelector('select[name="template_id"]');
    if (templateSelect && templateSelect.value) {
        previewTemplate(templateSelect.value);
    }
    
    // Form validation for newsletter sending
    const newsletterForm = document.querySelector('.newsletter-form');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            const templateId = document.querySelector('select[name="template_id"]').value;
            const subject = document.querySelector('input[name="subject"]').value;
            
            if (!templateId) {
                e.preventDefault();
                alert('Veuillez sélectionner un template.');
                return;
            }
            
            if (!subject.trim()) {
                e.preventDefault();
                alert('Veuillez saisir un sujet.');
                return;
            }
            
            // Confirmation before sending
            if (!confirm('Êtes-vous sûr de vouloir envoyer cette newsletter ?')) {
                e.preventDefault();
            }
        });
    }
});`;