jQuery(document).ready(function ($) {
    // Handle the Pull Database button click
    $('#db-migrate-pull').on('click', function (event) {
        event.preventDefault(); // Prevent default form submission
        // Prepare data for the AJAX request
        var formData = {
            remote_host: $('#remote_host').val(),
            remote_dbname: $('#remote_dbname').val(),
            remote_username: $('#remote_username').val(),
            remote_password: $('#remote_password').val(),
            remote_url: $('#remote_url').val(), // Add remote URL
            local_url: $('#local_url').val(), // Add local URL
            security: $('#security').val(),
        };

        $.ajax({
            type: 'POST',
            url: dbMigrate.ajax_url,
            data: {
                action: 'db_migrate_pull',
                formData: formData,
            },
            success: function (response) {
                if (response.success) {
                    $('#response').html('<div class="updated"><p>' + response.data + '</p></div>');
                } else {
                    $('#response').html('<div class="error"><p>' + response.data + '</p></div>');
                }
            },
            error: function () {
                $('#response').html('<div class="error"><p>An error occurred while processing the request.</p></div>');
            },
        });
    });

    // Handle the Push Database button click
    $('#db-migrate-push').on('click', function (event) {
        event.preventDefault(); // Prevent default form submission
        // Prepare data for the AJAX request
        var formData = {
            remote_host: $('#remote_host').val(),
            remote_dbname: $('#remote_dbname').val(),
            remote_username: $('#remote_username').val(),
            remote_password: $('#remote_password').val(),
            remote_url: $('#remote_url').val(), // Add remote URL
            local_url: $('#local_url').val(), // Add local URL
            security: $('#security').val(),
        };

        $.ajax({
            type: 'POST',
            url: dbMigrate.ajax_url,
            data: {
                action: 'db_migrate_push',
                formData: formData,
            },
            success: function (response) {
                if (response.success) {
                    $('#response').html('<div class="updated"><p>' + response.data + '</p></div>');
                } else {
                    $('#response').html('<div class="error"><p>' + response.data + '</p></div>');
                }
            },
            error: function () {
                $('#response').html('<div class="error"><p>An error occurred while processing the request.</p></div>');
            },
        });
    });
});
