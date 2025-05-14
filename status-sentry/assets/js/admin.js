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

        // Initialize clear events functionality
        initClearEvents();
    }

    /**
     * Initialize the event viewer.
     */
    function initEventViewer() {
        $('.status-sentry-view-event').on('click', function(e) {
            e.preventDefault();

            var eventId = $(this).data('id');
            var eventType = $(this).data('type') || 'legacy';

            // Show loading message
            showEventViewerDialog('Loading event data...');

            // Fetch event data
            $.ajax({
                url: statusSentry.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'status_sentry_get_event',
                    nonce: statusSentry.nonce,
                    event_id: eventId,
                    event_type: eventType
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

        // Check if jQuery UI dialog is available
        if (typeof $.fn.dialog === 'function') {
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
        } else {
            // Fallback for when jQuery UI dialog is not available
            if ($dialog.length === 0) {
                // Create a simple modal
                $dialog = $(
                    '<div id="status-sentry-event-viewer" class="status-sentry-modal">' +
                    '<div class="status-sentry-modal-content">' +
                    '<div class="status-sentry-modal-header">' +
                    '<span class="status-sentry-modal-close">&times;</span>' +
                    '<h2>Event Details</h2>' +
                    '</div>' +
                    '<div class="status-sentry-modal-body"></div>' +
                    '</div>' +
                    '</div>'
                );
                $('body').append($dialog);

                // Add styles for the modal
                var style =
                    '<style>' +
                    '.status-sentry-modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }' +
                    '.status-sentry-modal-content { background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px; }' +
                    '.status-sentry-modal-close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }' +
                    '.status-sentry-modal-close:hover, .status-sentry-modal-close:focus { color: black; text-decoration: none; cursor: pointer; }' +
                    '.status-sentry-modal-header { padding: 10px 16px; background-color: #f8f8f8; border-bottom: 1px solid #ddd; }' +
                    '.status-sentry-modal-body { padding: 16px; max-height: 400px; overflow: auto; }' +
                    '</style>';
                $('head').append(style);

                // Add close functionality
                $('.status-sentry-modal-close').on('click', function() {
                    $dialog.hide();
                });

                // Close when clicking outside the modal
                $(window).on('click', function(event) {
                    if ($(event.target).is($dialog)) {
                        $dialog.hide();
                    }
                });
            }

            // Set the content
            $('.status-sentry-modal-body', $dialog).html(content);

            // Show the modal
            $dialog.show();
        }
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
        html += '<table class="status-sentry-metadata-table">';

        if (data.is_monitoring_event) {
            // Format monitoring event metadata
            html += '<tr><th>ID</th><td>' + data.id + '</td></tr>';
            html += '<tr><th>Event ID</th><td>' + data.event_id + '</td></tr>';
            html += '<tr><th>Type</th><td>' + formatEventType(data.type) + '</td></tr>';
            html += '<tr><th>Priority</th><td>' + formatPriority(data.priority) + '</td></tr>';
            html += '<tr><th>Source</th><td>' + data.source + '</td></tr>';
            html += '<tr><th>Context</th><td>' + data.context + '</td></tr>';
            html += '<tr><th>Message</th><td>' + data.message + '</td></tr>';
            html += '<tr><th>Time</th><td>' + data.event_time + '</td></tr>';
            html += '<tr><th>Created</th><td>' + data.created_at + '</td></tr>';
        } else {
            // Format legacy event metadata
            html += '<tr><th>ID</th><td>' + data.id + '</td></tr>';
            html += '<tr><th>Feature</th><td>' + data.feature + '</td></tr>';
            html += '<tr><th>Hook</th><td>' + data.hook + '</td></tr>';
            html += '<tr><th>Time</th><td>' + data.event_time + '</td></tr>';
        }

        html += '</table>';
        html += '</div>';

        // Add performance metrics if available
        if (data.is_monitoring_event && data.type === 'performance' && data.performance_metrics) {
            html += '<div class="status-sentry-performance-metrics">';
            html += '<h3>Performance Metrics</h3>';
            html += '<table class="status-sentry-metrics-table">';

            var metrics = data.performance_metrics;

            if (metrics.memory_usage) {
                html += '<tr><th>Memory Usage</th><td>' + formatBytes(metrics.memory_usage) + '</td></tr>';
            }

            if (metrics.memory_peak) {
                html += '<tr><th>Peak Memory</th><td>' + formatBytes(metrics.memory_peak) + '</td></tr>';
            }

            if (metrics.memory_limit) {
                html += '<tr><th>Memory Limit</th><td>' + formatBytes(metrics.memory_limit) + '</td></tr>';
            }

            if (metrics.memory_usage_percent) {
                html += '<tr><th>Memory Usage %</th><td>' + metrics.memory_usage_percent.toFixed(2) + '%</td></tr>';
            }

            if (metrics.cpu_load) {
                html += '<tr><th>CPU Load</th><td>' + metrics.cpu_load.toFixed(2) + '%</td></tr>';
            }

            if (metrics.execution_time) {
                html += '<tr><th>Execution Time</th><td>' + metrics.execution_time.toFixed(4) + ' seconds</td></tr>';
            }

            if (metrics.query_count) {
                html += '<tr><th>Query Count</th><td>' + metrics.query_count + '</td></tr>';
            }

            if (metrics.query_time) {
                html += '<tr><th>Query Time</th><td>' + metrics.query_time.toFixed(4) + ' seconds</td></tr>';
            }

            if (metrics.http_requests) {
                html += '<tr><th>HTTP Requests</th><td>' + metrics.http_requests + '</td></tr>';
            }

            if (metrics.http_time) {
                html += '<tr><th>HTTP Time</th><td>' + metrics.http_time.toFixed(4) + ' seconds</td></tr>';
            }

            if (metrics.cache_hits) {
                html += '<tr><th>Cache Hits</th><td>' + metrics.cache_hits + '</td></tr>';
            }

            if (metrics.cache_misses) {
                html += '<tr><th>Cache Misses</th><td>' + metrics.cache_misses + '</td></tr>';
            }

            html += '</table>';
            html += '</div>';
        }

        // Add event data with toggle
        if (data.data) {
            html += '<div class="status-sentry-event-details">';
            html += '<h3>Event Data <button type="button" class="status-sentry-toggle-raw">Toggle Raw JSON</button></h3>';
            html += '<div class="status-sentry-data-formatted">';

            // Format data based on content
            if (typeof data.data === 'object') {
                html += formatObjectData(data.data);
            } else {
                html += '<pre>' + data.data + '</pre>';
            }

            html += '</div>';

            // Add raw JSON (hidden by default)
            html += '<div class="status-sentry-data-raw" style="display: none;">';
            html += '<pre>' + JSON.stringify(data.data, null, 2) + '</pre>';
            html += '</div>';

            html += '</div>';
        }

        // Add styles
        html += '<style>' +
            '.status-sentry-metadata-table, .status-sentry-metrics-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }' +
            '.status-sentry-metadata-table th, .status-sentry-metrics-table th { text-align: left; padding: 8px; width: 30%; background-color: #f5f5f5; }' +
            '.status-sentry-metadata-table td, .status-sentry-metrics-table td { padding: 8px; }' +
            '.status-sentry-metadata-table tr, .status-sentry-metrics-table tr { border-bottom: 1px solid #ddd; }' +
            '.status-sentry-toggle-raw { float: right; font-size: 12px; padding: 2px 8px; cursor: pointer; }' +
            '.status-sentry-data-formatted { margin-bottom: 20px; }' +
            '.status-sentry-data-formatted ul { list-style-type: none; padding-left: 20px; margin: 0; }' +
            '.status-sentry-data-formatted li { margin-bottom: 5px; }' +
            '.status-sentry-data-formatted .key { font-weight: bold; color: #0073aa; }' +
            '.status-sentry-data-formatted .value { color: #333; }' +
            '.status-sentry-data-formatted .object-value { cursor: pointer; }' +
            '.status-sentry-data-formatted .collapsed { display: none; }' +
            '.status-sentry-event-type-info { color: #0073aa; }' +
            '.status-sentry-event-type-warning { color: #ffb900; }' +
            '.status-sentry-event-type-error { color: #dc3232; }' +
            '.status-sentry-event-type-critical { color: #dc3232; font-weight: bold; }' +
            '.status-sentry-event-type-performance { color: #46b450; }' +
            '.status-sentry-event-type-security { color: #826eb4; }' +
            '.status-sentry-event-type-conflict { color: #00a0d2; }' +
            '.status-sentry-event-type-health { color: #00a0d2; }' +
            '.status-sentry-priority-low { color: #0073aa; }' +
            '.status-sentry-priority-normal { color: #46b450; }' +
            '.status-sentry-priority-high { color: #ffb900; }' +
            '.status-sentry-priority-critical { color: #dc3232; font-weight: bold; }' +
            '</style>';

        html += '</div>';

        // Add toggle functionality
        setTimeout(function() {
            $('.status-sentry-toggle-raw').on('click', function() {
                $('.status-sentry-data-formatted, .status-sentry-data-raw').toggle();
            });

            // Add collapsible functionality for nested objects
            $('.status-sentry-data-formatted .object-value').on('click', function() {
                $(this).next('ul').toggleClass('collapsed');
                $(this).toggleClass('expanded');
            });
        }, 100);

        return html;
    }

    /**
     * Format event type with appropriate styling.
     *
     * @param {string} type The event type.
     * @return {string} The formatted event type.
     */
    function formatEventType(type) {
        var typeClass = 'status-sentry-event-type-' + type;
        return '<span class="' + typeClass + '">' + type.charAt(0).toUpperCase() + type.slice(1) + '</span>';
    }

    /**
     * Format priority with appropriate styling.
     *
     * @param {number} priority The priority value.
     * @return {string} The formatted priority.
     */
    function formatPriority(priority) {
        var priorityText, priorityClass;

        if (priority <= 10) {
            priorityText = 'Low';
            priorityClass = 'status-sentry-priority-low';
        } else if (priority <= 50) {
            priorityText = 'Normal';
            priorityClass = 'status-sentry-priority-normal';
        } else if (priority <= 80) {
            priorityText = 'High';
            priorityClass = 'status-sentry-priority-high';
        } else {
            priorityText = 'Critical';
            priorityClass = 'status-sentry-priority-critical';
        }

        return '<span class="' + priorityClass + '">' + priorityText + ' (' + priority + ')</span>';
    }

    /**
     * Format bytes to human-readable format.
     *
     * @param {number} bytes The number of bytes.
     * @return {string} The formatted bytes.
     */
    function formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';

        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    /**
     * Format object data in a more readable way.
     *
     * @param {Object} data The object data.
     * @param {number} depth The current depth level.
     * @return {string} The formatted object data.
     */
    function formatObjectData(data, depth) {
        depth = depth || 0;
        var html = '<ul' + (depth > 0 ? ' class="collapsed"' : '') + '>';

        for (var key in data) {
            if (data.hasOwnProperty(key)) {
                var value = data[key];
                html += '<li>';

                if (typeof value === 'object' && value !== null) {
                    html += '<span class="key">' + key + ':</span> ';
                    html += '<span class="value object-value">[Object]</span>';
                    html += formatObjectData(value, depth + 1);
                } else {
                    html += '<span class="key">' + key + ':</span> ';
                    html += '<span class="value">' + formatValue(value) + '</span>';
                }

                html += '</li>';
            }
        }

        html += '</ul>';
        return html;
    }

    /**
     * Format a value based on its type.
     *
     * @param {*} value The value to format.
     * @return {string} The formatted value.
     */
    function formatValue(value) {
        if (value === null) {
            return '<em>null</em>';
        } else if (value === undefined) {
            return '<em>undefined</em>';
        } else if (typeof value === 'boolean') {
            return value ? '<span style="color: #46b450;">true</span>' : '<span style="color: #dc3232;">false</span>';
        } else if (typeof value === 'number') {
            return '<span style="color: #826eb4;">' + value + '</span>';
        } else if (typeof value === 'string') {
            if (value.length > 100) {
                return '"' + value.substring(0, 100) + '..."';
            }
            return '"' + value + '"';
        } else {
            return String(value);
        }
    }

    /**
     * Initialize the clear events functionality.
     */
    function initClearEvents() {
        $('.status-sentry-clear-events').on('click', function() {
            var type = $(this).data('type');
            var nonce = $(this).data('nonce') || statusSentry.nonce;
            var confirmMessage = 'Are you sure you want to delete all ' + type + ' events? This action cannot be undone.';

            if (!confirm(confirmMessage)) {
                return;
            }

            // Show loading state
            var $button = $(this);
            var originalText = $button.text();
            $button.text('Clearing...').prop('disabled', true);

            // Make AJAX request to clear events
            $.ajax({
                url: statusSentry.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'status_sentry_clear_events',
                    nonce: nonce,
                    type: type
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        alert(response.data.message);

                        // Clear dashboard widget events list
                        $('.status-sentry-dashboard-widget-events ul').empty()
                            .append('<li>No events found</li>');

                        // If we're on the dashboard page, force refresh all dashboard data
                        if (window.statusSentryDashboard && typeof window.statusSentryDashboard.fetchData === 'function') {
                            console.log('Forcing dashboard data refresh...');
                            // Add a timestamp to ensure cache busting
                            window.statusSentryDashboard.fetchData(true);
                        }

                        // Reload the page after a delay to ensure database operations and transient deletions complete
                        setTimeout(function() {
                            // Add cache-busting parameter to the URL
                            var url = new URL(window.location.href);
                            url.searchParams.set('_', Date.now());
                            location.href = url.toString();
                        }, 800); // 800ms delay
                    } else {
                        // Show error message and reset button
                        alert('Error: ' + (response.data || 'Failed to clear events.'));
                        $button.text(originalText).prop('disabled', false);
                    }
                },
                error: function() {
                    // Show error message and reset button
                    alert('Error: Failed to clear events. Please try again.');
                    $button.text(originalText).prop('disabled', false);
                }
            });
        });

        // Add specific handler for the monitoring events clear button
        $('.status-sentry-clear-monitoring-events').on('click', function() {
            // This ensures the type is explicitly set to 'monitoring'
            // even if the data-type attribute is missing or incorrect
            $(this).data('type', 'monitoring');

            // Also clear the dashboard widget events list
            $('.status-sentry-dashboard-widget-events ul').empty();
        });
    }

    // Initialize when the DOM is ready
    $(document).ready(init);

})(jQuery);
