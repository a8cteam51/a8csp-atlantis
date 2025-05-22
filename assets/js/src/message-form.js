import domReady from "@wordpress/dom-ready";

class MessageForm {
	constructor() {
		this.init();
	}

	init() {
		this.setupAddButtons();
		this.setupRemoveButtons();
	}

	setupAddButtons() {
		domReady(() => {
			jQuery(".atlantis-location-dropdown")
				.select2({
					allowClear: true,
				})
				.on("select2:select", (e) => {
					const dropdown = e.params.data.element.parentElement;

					if (!dropdown?.classList.contains("atlantis-location-dropdown")) {
						return;
					}

					const target = dropdown.dataset.target;
					const value = e.params.data.id;

					if (!target || !value) {
						return;
					}

					const containerId = target === "include" ? "included" : "excluded";
					const container = document.getElementById(
						`atlantis-${containerId}-locations`,
					);

					if (!container) {
						return;
					}

					const selectedOption = dropdown.options[dropdown.selectedIndex];
					if (!selectedOption) {
						return;
					}

					const label = selectedOption.textContent;

					// Add the new location
					const item = document.createElement("div");
					item.className = "atlantis-location-item";
					item.innerHTML = `
                    <span>${label}</span>
                    <button type="button" class="button-link delete-location" data-location="${value}">Remove</button>
                    <input type="hidden" name="message_location_${target}[]" value="${value}">
                `;
					container.appendChild(item);

					// Remove the option from both dropdowns
					document
						.querySelectorAll(".atlantis-location-dropdown")
						.forEach((dropdown) => {
							const option = dropdown.querySelector(`option[value="${value}"]`);
							option?.remove();
						});
					dropdown.value = "";

					// Setup remove button for the new item
					if (window.atlantisMessageForm) {
						window.atlantisMessageForm.setupRemoveButtons();
					}
				});
		});
	}

	setupRemoveButtons() {
		const removeButtons = document.querySelectorAll(".delete-location");

		removeButtons.forEach((button) => {
			// Remove existing listeners
			const newButton = button.cloneNode(true);
			button.parentNode.replaceChild(newButton, button);
		});

		// Add new listeners
		document.querySelectorAll(".delete-location").forEach((button) => {
			button.addEventListener("click", (e) => {
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
				const item = button.closest(".atlantis-location-item");
				if (!item) {
					return;
				}

				// Remove the item
				item.remove();

				// Add the option back to both dropdowns
				document
					.querySelectorAll(".atlantis-location-dropdown")
					.forEach((dropdown) => {
						if (!dropdown.querySelector(`option[value="${value}"]`)) {
							const option = document.createElement("option");
							option.value = value;
							option.textContent = label;
							dropdown.appendChild(option);
						}
					});
			});
		});
	}
}

// Initialize when document is ready
domReady(() => {
	const container = document.getElementById("atlantis-included-locations");
	if (container) {
		window.atlantisMessageForm = new MessageForm();
	}
});
