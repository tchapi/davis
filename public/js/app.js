'use strict'

// Calendar share modal
const shareModal = document.getElementById('shareModal')
if (shareModal) {
    shareModal.addEventListener('show.bs.modal', event => {
        // Button that triggered the modal
        const button = event.relatedTarget

        // Grab calendar shares URL and share target.
        let shareesUrl = button.getAttribute('data-sharees-href');
        let targetUrl = button.getAttribute('data-href');
        document.getElementById('shareModal-addForm').setAttribute('action', targetUrl)

        const noneElement = document.getElementById('shareModal-none')

        // Shares list
        const shares = document.getElementById('shareModal-shares')
        shares.innerHTML = ''

        // Get calendar shares
        fetch(shareesUrl)
            .then((response) => response.json())
            .then((data) => {

                // No sharee
                if (data.length === 0) {
                    noneElement.classList.remove("d-none");
                    return
                }
    
                noneElement.classList.add('d-none')

                // Share list item template
                const template = document.getElementById("shareModal-shareeTemplate");

                data.forEach(element => {
                    const clone = template.content.cloneNode(true);
                    let name = clone.querySelectorAll("span.name");
                    name[0].textContent = element.displayName;
                    let badge = clone.querySelectorAll("span.badge");
                    badge[0].textContent = element.accessText;
                    if (element.isWriteAccess) {
                        badge[0].classList.add('bg-success')
                        badge[0].classList.remove('bg-info')
                    }
                    let revokeForm = clone.querySelectorAll("form.revoke");
                    revokeForm[0].setAttribute('action', element.revokeUrl);

                    shares.appendChild(clone);
                });
                
            });
    })
}


// Delete modals (all kind of entities, so we use the rel, not the id)
const deleteModals = document.querySelectorAll('[rel="deleteModal"]');
deleteModals.forEach(element => {
    element.addEventListener('show.bs.modal', event => {
        // Button that triggered the modal
        const button = event.relatedTarget

        // Grab the target URL for deletion.
        let targetUrl = button.getAttribute('data-href');
        let modalFlavour = button.getAttribute('data-flavour');

        // Put it into the modal's confirmation form.
        const deleteForm = document.getElementById(`deleteModal-${modalFlavour}-form`);
        deleteForm.setAttribute('action', targetUrl);
    })
})

// Color swatch: update it live (not working in IE ¯\_(ツ)_/¯ but it's just a nice to have)
const colorPicker = document.getElementById('calendar_instance_calendarColor');
if (colorPicker) {
    colorPicker.addEventListener('keyup', event => {
        document.body.style.setProperty('--calendar-color', event.target.value);
    })
    document.body.style.setProperty('--calendar-color', colorPicker.value);
}

// Bootstrap 5 popovers
const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]')
if (popoverTriggerList) {
    [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl))
}

// Bootstrap 5 toasts
const toastElList = document.querySelectorAll('.toast')
if (toastElList) {
    [...toastElList].map(toastEl => {
        const toast = new bootstrap.Toast(toastEl)
        toast.show()
    })
}
