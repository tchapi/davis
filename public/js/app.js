'use strict'

// Calendar share modal behaviour
const shareModal = document.getElementById('shareModal')
shareModal.addEventListener('show.bs.modal', event => {
    // Button that triggered the modal
    const button = event.relatedTarget

    // Grab calendar shares url and add url
    let shareesUrl = button.getAttribute('data-sharees-href');
    let targetUrl = button.getAttribute('data-href');

    // Put it into the modal's OK button
    const addShareeButton = document.getElementById('shareModal-addSharee');

    // Modal to add a sharee on a calendar, catch the click to add the query parameter
    addShareeButton.addEventListener("click", function(e) {
        const writeAccess = document.getElementById('shareModal-writeAccess').checked ? 'true' : 'false';
        const principalId = document.getElementById('shareModal-member').value;

        e.preventDefault()
        window.location = targetUrl + "?principalId=" + principalId + "&write=" + writeAccess
    });

    const noneElement = document.getElementById('shareModal-none')
    
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

            // Shares list
            const shares = document.getElementById('shareModal-shares')

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
                let revokeButton = clone.querySelectorAll("a.revoke");
                revokeButton[0].href = element.revokeUrl;

                shares.appendChild(clone);
            });
            
        });
})
$(document).ready(function() {
    // For messages
    $('.toast').toast('show');

    // "Delete" modals
    $('a.delete-modal').click(function() {
        // Grab real target url for deletion
        let targetUrl = $(this).attr('data-href');
        let modalFlavour = $(this).attr('data-flavour');

        // Put it into the modal's OK button
        $('#delete-' + modalFlavour + ' .target-url').attr('href', targetUrl);

        // Show the modal
        $('#delete-' + modalFlavour).modal('show');
    })

    // "Sharing settings" modals
    $('a.share-modal').click(function() {
        // Grab calendar shares url and add url
        let shareesUrl = $(this).attr('data-sharees-href');
        let targetUrl = $(this).attr('data-href');

        // Put it into the modal's OK button
        $('#share .add-sharee').attr('data-href', targetUrl);

        // Get calendar shares
        $.get(shareesUrl, function(data) {
            // Catch error TODO
            $('#shares').empty()
            if (data.length === 0) {
                $('.none').removeClass('d-none')
            } else {
                $('.none').addClass('d-none')
                data.forEach(element => {
                    const newShare = $($('#template-share').html())
                    newShare.find('span.name').text(element.displayName)
                    newShare.find('span.badge').text(element.accessText)
                    newShare.find('a.revoke').attr('href', element.revokeUrl)
                    if (element.isWriteAccess) {
                        newShare.find('span.badge').addClass('bg-success').removeClass('bg-info')
                    }
                    newShare.appendTo($('#shares'));
                });
            }
        })

        // Show the modal
        $('#share').modal('show');
    })

    // Color swatch : update it live (not working in IE ¯\_(ツ)_/¯ but it's just a nice to have)
    $('#calendar_instance_calendarColor').keyup(function() {
        document.body.style.setProperty('--calendar-color', $(this).val());
    })
    document.body.style.setProperty('--calendar-color', $('#calendar_instance_calendarColor').val());

    // Initialize popovers
    $('.popover-dismiss').popover()

    // Modal to add a sharee on a calendar, catch the click to add the query parameter
    $('a.add-sharee').click(function(e) {
        e.preventDefault()
        window.location = $(this).attr('data-href') + "?principalId=" + $("#member").val() + "&write=" + ($("#write").is(':checked') ? 'true' : 'false')
    })

    // Modal to add delegate, catch the click to add the query parameter
    $('a.add-delegate').click(function(e) {
        e.preventDefault()
        window.location = $(this).attr('data-href') + "?principalId=" + $("#member").val() + "&write=" + ($("#write").is(':checked') ? 'true' : 'false')
    })
})