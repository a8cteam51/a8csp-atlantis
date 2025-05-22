(function() {
    'use strict';

    function MessageForm() {
        this.init();
    }

    MessageForm.prototype.init = function() {
        this.setupAddButtons();
        this.setupRemoveButtons();
    };

    MessageForm.prototype.setupAddButtons = function() {
        const addButtons = document.querySelectorAll('#atlantis-add-include-location, #atlantis-add-exclude-location');
        
        addButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                
                const select = button.previousElementSibling;
                
                if (!select || !select.classList.contains('atlantis-location-dropdown')) {
                    return;
                }

                const target = select.dataset.target;
                
                if (!target) {
                    return;
                }

                const value = select.value;
                
                if (!value) {
                    return;
                }

                const containerId = target === 'include' ? 'included' : 'excluded';
                const container = document.getElementById(`atlantis-${containerId}-locations`);
                
                if (!container) {
                    return;
                }

                const selectedOption = select.options[select.selectedIndex];
                if (!selectedOption) {
                    return;
                }

                const label = selectedOption.textContent;
                
                // Add the new location
                const item = document.createElement('div');
                item.className = 'atlantis-location-item';
                item.innerHTML = `
                    <span>${label}</span>
                    <button type="button" class="button-link delete-location" data-location="${value}">Remove</button>
                    <input type="hidden" name="message_location_${target}[]" value="${value}">
                `;
                container.appendChild(item);

                // Remove the option from both dropdowns
                document.querySelectorAll('.atlantis-location-dropdown').forEach(dropdown => {
                    const option = dropdown.querySelector(`option[value="${value}"]`);
                    if (option) {
                        option.remove();
                    }
                });
                select.value = '';

                // Setup remove button for the new item
                this.setupRemoveButtons();
            });
        });
    };

    MessageForm.prototype.setupRemoveButtons = function() {
        const removeButtons = document.querySelectorAll('.delete-location');
        
        removeButtons.forEach(button => {
            // Remove existing listeners
            const newButton = button.cloneNode(true);
            button.parentNode.replaceChild(newButton, button);
        });

        // Add new listeners
        document.querySelectorAll('.delete-location').forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                
                const value = button.dataset.location;
                if (!value) {
                    return;
                }

                const labelSpan = button.previousElementSibling;
                if (!labelSpan) {
                    return;
                }

                const label = labelSpan.textContent;
                const item = button.closest('.atlantis-location-item');
                if (!item) {
                    return;
                }
                
                // Remove the item
                item.remove();

                // Add the option back to both dropdowns
                document.querySelectorAll('.atlantis-location-dropdown').forEach(dropdown => {
                    if (!dropdown.querySelector(`option[value="${value}"]`)) {
                        const option = document.createElement('option');
                        option.value = value;
                        option.textContent = label;
                        dropdown.appendChild(option);
                    }
                });
            });
        });
    };

    // Initialize when document is ready
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('atlantis-included-locations');
        if (container) {
            new MessageForm();
        }
    });

})(); 