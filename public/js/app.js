'use strict'

// State-changing admin actions are sent as POST requests carrying the CSRF token
// exposed in the <meta name="csrf-token"> tag of the layout.
function postTo(url, params = {}) {
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    const form = document.createElement('form');
    form.method = 'post';
    form.action = url;
    form.classList.add('d-none');

    const fields = Object.assign({ _token: token }, params);
    Object.keys(fields).forEach(name => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = fields[name];
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
}

// Any element with a data-post-href attribute triggers a POST to that URL when clicked
document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-post-href]');
    if (!trigger) {
        return;
    }

    event.preventDefault();
    postTo(trigger.getAttribute('data-post-href'));
});

// Calendar share modal
const shareModal = document.getElementById('shareModal')
if (shareModal) {
    const addShareeButton = document.getElementById('shareModal-addSharee');

    // Bound once: the target url is refreshed each time the modal is shown
    addShareeButton.addEventListener("click", function(e) {
        const writeAccess = document.getElementById('shareModal-writeAccess').checked ? 'true' : 'false';
        const principalId = document.getElementById('shareModal-member').value;

        e.preventDefault()
        postTo(addShareeButton.getAttribute('data-href'), { principalId: principalId, write: writeAccess })
    });

    shareModal.addEventListener('show.bs.modal', event => {
        // Button that triggered the modal
        const button = event.relatedTarget

        // Grab calendar shares url and add url
        let shareesUrl = button.getAttribute('data-sharees-href');
        addShareeButton.setAttribute('data-href', button.getAttribute('data-href'));

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
                    let revokeButton = clone.querySelectorAll(".revoke");
                    revokeButton[0].setAttribute('data-post-href', element.revokeUrl);

                    shares.appendChild(clone);
                });

            });
    })
}


// Delete modals (all kind of entities, so we use the rel, not the id)
document.querySelectorAll('[rel="deleteModal"]').forEach(element => {
    element.addEventListener('show.bs.modal', event => {
        // Button that triggered the modal
        const button = event.relatedTarget

        // Grab real target url for deletion
        let targetUrl = button.getAttribute('data-href');
        let modalFlavour = button.getAttribute('data-flavour');

        // Put it into the modal's OK button (which POSTs it on click)
        const deleteCTA = document.getElementById(`deleteModal-${modalFlavour}-cta`);
        deleteCTA.setAttribute('data-post-href', targetUrl);
    })
})


// Global account delegation modal
const addDelegateModal = document.getElementById('addDelegateModal')
if (addDelegateModal) {
    const addDelegateButton = document.getElementById('addDelegateModal-cta');
    addDelegateButton.addEventListener("click", function(e) {
        const targetUrl = addDelegateButton.getAttribute('data-href');
        const writeAccess = document.getElementById('addDelegateModal-writeAccess').checked ? 'true' : 'false';
        const principalId = document.getElementById('addDelegateModal-member').value;

        e.preventDefault()
        postTo(targetUrl, { principalId: principalId, write: writeAccess })
    });
}

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
