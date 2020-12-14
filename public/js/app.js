'use strict'

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

    // Color swatch : update it live (not working in IE ¯\_(ツ)_/¯ but it's just a nice to have)
    $('#calendar_instance_calendarColor').keyup(function() {
        document.body.style.setProperty('--calendar-color', $(this).val());
    })
    document.body.style.setProperty('--calendar-color', $('#calendar_instance_calendarColor').val());

    // Modal to add delegate, catch the click to add the query parameter
    $('a.add-delegate').click(function(e) {
        e.preventDefault()
        window.location = $(this).attr('data-href') + "?principalId=" + $("#member").val() + "&write=" + ($("#write").is(':checked') ? 'true' : 'false')
    })
})