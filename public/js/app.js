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
})