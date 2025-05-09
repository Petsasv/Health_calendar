// Calendar functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Handle calendar event clicks
    const calendarEvents = document.querySelectorAll('.calendar-event');
    calendarEvents.forEach(event => {
        event.addEventListener('click', function() {
            const infoLink = this.getAttribute('data-info-link');
            if (infoLink) {
                window.open(infoLink, '_blank');
            }
        });
    });

    // Handle profile form validation
    const profileForm = document.getElementById('profile-form');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            const age = document.getElementById('age').value;
            if (age < 1 || age > 120) {
                e.preventDefault();
                alert('Please enter a valid age between 1 and 120');
            }
        });
    }

    // Handle password confirmation
    const registerForm = document.getElementById('register-form');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match');
            }
        });
    }
});

// Function to format date for display
function formatDate(date) {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(date).toLocaleDateString(undefined, options);
}

// Function to calculate next due date based on frequency
function calculateNextDueDate(lastCheck, frequency) {
    const date = new Date(lastCheck);
    
    switch(frequency.toLowerCase()) {
        case 'daily':
            date.setDate(date.getDate() + 1);
            break;
        case 'weekly':
            date.setDate(date.getDate() + 7);
            break;
        case 'monthly':
            date.setMonth(date.getMonth() + 1);
            break;
        case 'yearly':
        case 'every year':
            date.setFullYear(date.getFullYear() + 1);
            break;
        case 'every 2 years':
            date.setFullYear(date.getFullYear() + 2);
            break;
        case 'every 3 years':
            date.setFullYear(date.getFullYear() + 3);
            break;
        case 'every 5 years':
            date.setFullYear(date.getFullYear() + 5);
            break;
        default:
            return null;
    }
    
    return date;
}

// Function to show a notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    notification.setAttribute('role', 'alert');
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        notification.remove();
    }, 5000);
} 