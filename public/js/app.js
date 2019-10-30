'use strict'

$(document).ready(function() {
    // For messages
    $('.toast').toast('show');

    // "Delete" modals
    $('a.delete-modal').click(function() {
        // Grab real target url for deletion
        let targetUrl = $(this).attr('data-href');

        // Put it into the modal's OK button
        $('#delete .target-url').attr('href', targetUrl);

        // Show the modal
        $('#delete').modal('show');
    })

    // Color swatch : update it live (not working in IE ¯\_(ツ)_/¯ but it's just a nice to have)
    $('#calendar_instance_calendarColor').keyup(function() {
        document.body.style.setProperty('--calendar-color', $(this).val());
    })
    document.body.style.setProperty('--calendar-color', $('#calendar_instance_calendarColor').val());
})