/**
 * Status Sentry Admin JavaScript
 */

(function($) {
    'use strict';

    /**
     * Initialize the admin functionality.
     */
    function init() {
        // Initialize event viewer
        initEventViewer();
    }

    /**
     * Initialize the event viewer.
     */
    function initEventViewer() {
        $('.status-sentry-view-event').on('click', function(e) {
            e.preventDefault();
            
            var eventId = $(this).data('id');
            
            // Show loading message
            showEventViewerDialog('Loading event data...');
            
            // Fetch event data
            $.ajax({
                url: statusSentry.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'status_sentry_get_event',
                    nonce: statusSentry.nonce,
                    event_id: eventId
                },
                success: function(response) {
                    if (response.success) {
                        showEventViewerDialog(formatEventData(response.data));
                    } else {
                        showEventViewerDialog('Error: ' + response.data);
                    }
                },
                error: function() {
                    showEventViewerDialog('Error: Failed to fetch event data.');
                }
            });
        });
    }

    /**
     * Show the event viewer dialog.
     *
     * @param {string} content The dialog content.
     */
    function showEventViewerDialog(content) {
        // Check if the dialog exists
        var $dialog = $('#status-sentry-event-viewer');
        
        if ($dialog.length === 0) {
            // Create the dialog
            $dialog = $('<div id="status-sentry-event-viewer" title="Event Details"></div>');
            $('body').append($dialog);
            
            // Initialize the dialog
            $dialog.dialog({
                autoOpen: false,
                modal: true,
                width: 600,
                height: 400,
                buttons: {
                    Close: function() {
                        $(this).dialog('close');
                    }
                }
            });
        }
        
        // Set the dialog content
        $dialog.html(content);
        
        // Open the dialog
        $dialog.dialog('open');
    }

    /**
     * Format event data for display.
     *
     * @param {Object} data The event data.
     * @return {string} The formatted event data.
     */
    function formatEventData(data) {
        if (!data) {
            return 'No data available.';
        }
        
        var html = '<div class="status-sentry-event-data">';
        
        // Add event metadata
        html += '<div class="status-sentry-event-metadata">';
        html += '<h3>Event Metadata</h3>';
        html += '<table>';
        html += '<tr><th>ID</th><td>' + data.id + '</td></tr>';
        html += '<tr><th>Feature</th><td>' + data.feature + '</td></tr>';
        html += '<tr><th>Hook</th><td>' + data.hook + '</td></tr>';
        html += '<tr><th>Time</th><td>' + data.event_time + '</td></tr>';
        html += '</table>';
        html += '</div>';
        
        // Add event data
        if (data.data) {
            html += '<div class="status-sentry-event-details">';
            html += '<h3>Event Data</h3>';
            html += '<pre>' + JSON.stringify(data.data, null, 2) + '</pre>';
            html += '</div>';
        }
        
        html += '</div>';
        
        return html;
    }

    // Initialize when the DOM is ready
    $(document).ready(init);

})(jQuery);
