/**
 * Charity Event Calendar - Client-side Utilities
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Auto-dismiss flash alerts after 6 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) {
                bsAlert.close();
            }
        }, 6000);
    });

    // 2. Validate start & end datetime on event forms
    const startInput = document.getElementById('start_datetime');
    const endInput = document.getElementById('end_datetime');
    const deadlineInput = document.getElementById('registration_deadline');

    if (startInput && endInput) {
        const validateEventDates = function () {
            if (startInput.value && endInput.value) {
                if (new Date(endInput.value) <= new Date(startInput.value)) {
                    endInput.setCustomValidity('End date and time must be after start date and time.');
                } else {
                    endInput.setCustomValidity('');
                }
            }
            if (deadlineInput && startInput.value && deadlineInput.value) {
                if (new Date(deadlineInput.value) > new Date(startInput.value)) {
                    deadlineInput.setCustomValidity('Registration deadline must be before or equal to event start time.');
                } else {
                    deadlineInput.setCustomValidity('');
                }
            }
        };

        startInput.addEventListener('change', validateEventDates);
        endInput.addEventListener('change', validateEventDates);
        if (deadlineInput) {
            deadlineInput.addEventListener('change', validateEventDates);
        }
    }

    // 3. Confirm Delete / Cancel actions
    const confirmButtons = document.querySelectorAll('[data-confirm]');
    confirmButtons.forEach(function (button) {
        button.addEventListener('click', function (e) {
            const msg = button.getAttribute('data-confirm') || 'Are you sure you want to perform this action?';
            if (!confirm(msg)) {
                e.preventDefault();
            }
        });
    });
});
