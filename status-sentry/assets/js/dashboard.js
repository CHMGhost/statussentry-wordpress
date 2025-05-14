/**
 * Status Sentry Dashboard JavaScript
 *
 * This file handles the dashboard UI, including charts, KPI cards,
 * and data fetching from the REST API.
 *
 * @since      1.5.0
 * @package    Status_Sentry
 */

(function($) {
    'use strict';

    // Dashboard app - make it globally accessible
    window.statusSentryDashboard = {
        /**
         * Initialize the dashboard.
         */
        init: function() {
            this.container = $('#status-sentry-dashboard-app');

            // Set up error handling for global AJAX errors
            $(document).ajaxError((event, jqXHR, settings, thrownError) => {
                console.error('Global AJAX error:', thrownError);
                console.error('Status code:', jqXHR.status);
                console.error('URL:', settings.url);

                // If this is a dashboard API call and it failed with a 500 error
                if (settings.url.indexOf('status-sentry/v1/dashboard') !== -1 && jqXHR.status === 500) {
                    // Show a user-friendly error message
                    if (!this.errorShown) {
                        this.container.html(`
                            <div class="notice notice-error">
                                <p><strong>Dashboard Error:</strong> There was a problem loading the dashboard data.</p>
                                <p>Using fallback data to display the dashboard.</p>
                            </div>
                            <div id="status-sentry-dashboard-content"></div>
                        `);
                        this.container = $('#status-sentry-dashboard-content');
                        this.errorShown = true;
                    }
                }
            });

            this.fetchData();
            this.setupRefresh();
            this.setupEventHandlers();
        },

        /**
         * Set up automatic refresh.
         */
        setupRefresh: function() {
            // Refresh dashboard data every 10 seconds with force_refresh=true
            setInterval(() => {
                this.fetchData(true);
            }, 10000);
        },

        /**
         * Set up event handlers.
         */
        setupEventHandlers: function() {
            // Set up baselines refresh button click handler
            $(document).on('click', '#status-sentry-refresh-baselines', (e) => {
                e.preventDefault();

                // Add spinning class to the refresh icon
                const button = $(e.currentTarget);
                button.addClass('refreshing').prop('disabled', true);
                button.find('.dashicons').addClass('dashicons-update-spin');

                // Fetch baseline data with force_refresh=true and manual_refresh=true
                this.fetchBaselinesData(true, true).then(() => {
                    // Remove spinning class after refresh completes
                    setTimeout(() => {
                        button.removeClass('refreshing').prop('disabled', false);
                        button.find('.dashicons').removeClass('dashicons-update-spin');
                    }, 500);
                });
            });

            // Set up dashboard refresh button click handler
            $(document).on('click', '#status-sentry-refresh-dashboard', (e) => {
                e.preventDefault();

                // Add spinning class to the refresh icon
                const button = $(e.currentTarget);
                button.addClass('refreshing').prop('disabled', true);
                button.find('.dashicons').addClass('dashicons-update-spin');

                // Update last refreshed time
                $('.status-sentry-last-refresh').text('Last refreshed: Just now');

                // Force a complete refresh of all dashboard data with cache busting
                this.forceRefreshAllData().then(() => {
                    // Remove spinning class after refresh completes
                    setTimeout(() => {
                        button.removeClass('refreshing').prop('disabled', false);
                        button.find('.dashicons').removeClass('dashicons-update-spin');
                    }, 500);
                });
            });
        },

        /**
         * Force refresh all dashboard data with cache busting.
         *
         * @return {Promise} A promise that resolves when all data is refreshed
         */
        forceRefreshAllData: function() {
            // Generate a unique timestamp for cache busting
            const timestamp = Date.now();

            // Show a loading overlay
            this.container.append('<div class="status-sentry-loading-overlay"><div class="spinner is-active"></div><p>Refreshing all dashboard data...</p></div>');

            // Clear any existing caches in PHP and browser
            return new Promise((resolve) => {
                // First, call a special endpoint to clear server-side caches
                $.ajax({
                    url: statusSentry.restUrl + 'clear-cache',
                    method: 'POST',
                    timeout: 5000,
                    data: { _wpnonce: statusSentry.restNonce },
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', statusSentry.restNonce);
                    },
                    complete: () => {
                        // Now fetch all data with force refresh
                        this.fetchData(true);

                        // Remove the loading overlay after a short delay
                        setTimeout(() => {
                            $('.status-sentry-loading-overlay').fadeOut(function() {
                                $(this).remove();
                            });
                            resolve();
                        }, 1000);
                    }
                });
            });
        },

        /**
         * Fetch baseline data from the REST API.
         *
         * @param {boolean} forceFresh - Whether to force fresh data
         * @param {boolean} manualRefresh - Whether this is a manual refresh from the UI button
         * @return {Promise} A promise that resolves when the data is fetched
         */
        fetchBaselinesData: function(forceFresh, manualRefresh = false) {
            const self = this;
            const timestamp = Date.now();

            return new Promise((resolve) => {
                $.ajax({
                    url: statusSentry.restUrl + 'baselines',
                    method: 'GET',
                    timeout: 10000, // 10 second timeout
                    data: forceFresh ? {
                        force_refresh: 'true',
                        manual_refresh: manualRefresh ? 'true' : 'false',
                        _: timestamp
                    } : { force_refresh: 'true' },
                    cache: false,
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', statusSentry.restNonce);
                        // Add cache-busting headers
                        xhr.setRequestHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
                        xhr.setRequestHeader('Pragma', 'no-cache');
                        xhr.setRequestHeader('Expires', '0');
                    },
                    success: function(response) {
                        console.log('Baselines data received');
                        self.renderBaselines(response);
                        resolve(response);
                    },
                    error: function(error) {
                        console.error('Error fetching baseline data:', error);
                        console.error('Status code:', error.status);
                        console.error('Error message:', error.statusText);

                        // Use fallback data
                        self.renderBaselines({
                            baselines: self.generateFallbackBaselines()
                        });
                        resolve(null);
                    }
                });
            });
        },

        /**
         * Fetch dashboard data from the REST API.
         *
         * @param {boolean} forceFresh - Whether to force fresh data by adding a timestamp parameter
         */
        fetchData: function(forceFresh) {
            const self = this;

            // Disable jQuery caching for all AJAX requests to ensure fresh data
            $.ajaxSetup({
                cache: false,
                // Add cache-busting headers to all AJAX requests
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
                    xhr.setRequestHeader('Pragma', 'no-cache');
                    xhr.setRequestHeader('Expires', '0');
                }
            });

            // Generate a timestamp for cache busting
            const timestamp = Date.now();

            // Clear any existing transient timeouts
            if (this.dataTimeout) {
                clearTimeout(this.dataTimeout);
            }

            // Show loading state if first load
            if (!this.container.hasClass('loaded')) {
                this.container.html('<div class="status-sentry-loading"><span class="spinner is-active"></span><p>Loading dashboard data...</p></div>');
            }

            // If forcing fresh data, show a loading indicator
            if (forceFresh) {
                this.container.append('<div class="status-sentry-refreshing"><span class="spinner is-active"></span><p>Refreshing dashboard data...</p></div>');

                // Remove the loading indicator after 2 seconds
                setTimeout(function() {
                    $('.status-sentry-refreshing').fadeOut(function() {
                        $(this).remove();
                    });
                }, 2000);
            }

            // Log REST API URL for debugging
            console.log('Status Sentry REST API URL:', statusSentry.restUrl);
            console.log('Status Sentry REST Nonce:', statusSentry.restNonce ? 'Available' : 'Missing');
            console.log('Status Sentry Dashboard Data Endpoint:', statusSentry.dashboardDataEndpoint);

            // Set a timeout to ensure we show something even if all AJAX calls fail
            this.dataTimeout = setTimeout(() => {
                if (!this.container.hasClass('loaded')) {
                    console.log('Data fetch timeout - using fallback data');
                    // Use fallback data for all sections
                    this.renderDashboard();
                    this.renderOverview({
                        event_counts: {
                            core_monitoring: 0,
                            db_monitoring: 0,
                            conflict_detection: 0,
                            performance_monitoring: 0
                        },
                        resource_status: {
                            memory_usage_percent: 45,
                            cpu_load: 30
                        },
                        recommendations: [
                            {
                                type: 'warning',
                                message: 'Could not load live data. Using fallback data.',
                                action: 'Check server logs for more information.'
                            }
                        ]
                    });
                    // Trends rendering removed
                }
            }, 5000); // 5 second timeout

            // Fetch dashboard data from the new unified endpoint
            $.ajax({
                url: statusSentry.dashboardDataEndpoint,
                method: 'GET',
                timeout: 10000, // 10 second timeout
                data: { bypass_cache: forceFresh ? 'true' : 'false', _: timestamp },
                cache: false,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', statusSentry.restNonce);
                    // Add cache-busting headers
                    xhr.setRequestHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
                    xhr.setRequestHeader('Pragma', 'no-cache');
                    xhr.setRequestHeader('Expires', '0');
                },
                success: function(response) {
                    console.log('Dashboard data received:', response);

                    // Render the dashboard layout
                    self.renderDashboard();

                    // Render the charts
                    if (response.eventTypes) {
                        self.renderEventTypeChart('eventTypeChart', response.eventTypes);
                    }

                    if (response.timeline) {
                        self.renderEventTimelineChart('eventTimelineChart', response.timeline);
                    }

                    if (response.health) {
                        self.renderSystemHealth('#status-sentry-system-health', response.health);
                    }

                    clearTimeout(self.dataTimeout); // Clear the timeout if we got data
                },
                error: function(error) {
                    console.error('Error fetching dashboard data:', error);
                    console.error('Status code:', error.status);
                    console.error('Error message:', error.statusText);
                    console.error('Response text:', error.responseText);

                    // Render the dashboard with fallback data
                    self.renderDashboard();

                    // Render fallback charts
                    self.renderEventTypeChart('eventTypeChart', {
                        info: 2,
                        warning: 1,
                        error: 0,
                        critical: 0,
                        performance: 1,
                        security: 0,
                        conflict: 0,
                        health: 1
                    });

                    self.renderEventTimelineChart('eventTimelineChart', self.generateDummyTimelineData());

                    self.renderSystemHealth('#status-sentry-system-health', {
                        php_version: '7.4',
                        php_status: 'warning',
                        wp_version: '6.0',
                        wp_status: 'good',
                        memory_limit: '128M',
                        memory_usage: '64MB',
                        memory_usage_percent: 50,
                        memory_status: 'good',
                        status: 'warning'
                    });
                }
            });

            // Fetch overview data - always bypass cache
            $.ajax({
                url: statusSentry.restUrl + 'overview',
                method: 'GET',
                timeout: 10000, // 10 second timeout
                data: { bypass_cache: 'true', _: timestamp },
                cache: false,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', statusSentry.restNonce);
                    // Add cache-busting headers
                    xhr.setRequestHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
                    xhr.setRequestHeader('Pragma', 'no-cache');
                    xhr.setRequestHeader('Expires', '0');
                },
                success: function(response) {
                    console.log('Overview data received:', response);
                    self.renderOverview(response);
                    clearTimeout(self.dataTimeout); // Clear the timeout if we got data
                },
                error: function(error) {
                    console.error('Error fetching overview data:', error);
                    console.error('Status code:', error.status);
                    console.error('Error message:', error.statusText);
                    console.error('Response text:', error.responseText);
                    self.renderError('overview');
                }
            });

            // Trends data fetch removed

            // Fetch recent events
            $.ajax({
                url: statusSentry.restUrl + 'recent',
                method: 'GET',
                timeout: 10000, // 10 second timeout
                data: forceFresh ? { _: timestamp } : {},
                cache: false,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', statusSentry.restNonce);
                    // Add cache-busting headers
                    xhr.setRequestHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
                    xhr.setRequestHeader('Pragma', 'no-cache');
                    xhr.setRequestHeader('Expires', '0');
                },
                success: function(response) {
                    console.log('Recent events data received');
                    self.renderRecentEvents(response);
                },
                error: function(error) {
                    console.error('Error fetching recent events:', error);
                    console.error('Status code:', error.status);
                    console.error('Error message:', error.statusText);

                    // Use fallback data
                    self.renderRecentEvents({
                        events: self.generateFallbackEvents()
                    });
                }
            });

            // Fetch baseline data using our dedicated method
            this.fetchBaselinesData(forceFresh);
        },

        /**
         * Render event type distribution chart.
         *
         * @param {string} canvasId - The ID of the canvas element.
         * @param {Object} eventTypes - The event type counts.
         */
        renderEventTypeChart: function(canvasId, eventTypes) {
            const canvas = document.getElementById(canvasId);
            if (!canvas || !window.Chart) {
                console.error('Canvas element or Chart.js not found');
                return;
            }

            // Prepare data for the chart
            const labels = [];
            const data = [];
            const backgroundColors = [];

            // Define colors for each event type
            const colors = {
                info: 'rgba(54, 162, 235, 0.7)',
                warning: 'rgba(255, 206, 86, 0.7)',
                error: 'rgba(255, 99, 132, 0.7)',
                critical: 'rgba(153, 51, 51, 0.7)',
                performance: 'rgba(75, 192, 192, 0.7)',
                security: 'rgba(153, 102, 255, 0.7)',
                conflict: 'rgba(255, 159, 64, 0.7)',
                health: 'rgba(102, 204, 102, 0.7)'
            };

            // Process event types
            for (const [type, count] of Object.entries(eventTypes)) {
                if (count > 0) {
                    labels.push(type.charAt(0).toUpperCase() + type.slice(1));
                    data.push(count);
                    backgroundColors.push(colors[type] || 'rgba(128, 128, 128, 0.7)');
                }
            }

            // If no data, add a placeholder
            if (data.length === 0) {
                labels.push('No Events');
                data.push(1);
                backgroundColors.push('rgba(200, 200, 200, 0.7)');
            }

            // Create the chart
            const ctx = canvas.getContext('2d');

            // Destroy existing chart if it exists
            if (this.eventTypeChart) {
                this.eventTypeChart.destroy();
            }

            this.eventTypeChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: backgroundColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 12,
                                padding: 10
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    return `${label}: ${value}`;
                                }
                            }
                        }
                    },
                    layout: {
                        padding: {
                            top: 10,
                            right: 10,
                            bottom: 10,
                            left: 10
                        }
                    }
                }
            });
        },

        /**
         * Render event timeline chart.
         *
         * @param {string} canvasId - The ID of the canvas element.
         * @param {Object} timeline - The timeline data.
         */
        renderEventTimelineChart: function(canvasId, timeline) {
            const canvas = document.getElementById(canvasId);
            if (!canvas || !window.Chart) {
                console.error('Canvas element or Chart.js not found');
                return;
            }

            // Create the chart
            const ctx = canvas.getContext('2d');

            // Destroy existing chart if it exists
            if (this.eventTimelineChart) {
                this.eventTimelineChart.destroy();
            }

            this.eventTimelineChart = new Chart(ctx, {
                type: 'bar',
                data: timeline,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            stacked: true,
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Event Count'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                boxWidth: 12,
                                padding: 10
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    layout: {
                        padding: {
                            top: 10,
                            right: 10,
                            bottom: 10,
                            left: 10
                        }
                    }
                }
            });
        },

        /**
         * Render system health metrics.
         *
         * @param {string} selector - The selector for the container element.
         * @param {Object} health - The health metrics.
         */
        renderSystemHealth: function(selector, health) {
            const container = $(selector);
            if (!container.length) {
                console.error('System health container not found');
                return;
            }

            // Create health grid
            const grid = $('<div class="status-sentry-health-grid"></div>');

            // PHP Version
            grid.append(this.createHealthItem(
                'PHP Version',
                health.php_version,
                health.php_status,
                health.php_status === 'error' ? 'Outdated PHP version' :
                health.php_status === 'warning' ? 'Consider upgrading PHP' : 'PHP version is up to date'
            ));

            // WordPress Version
            grid.append(this.createHealthItem(
                'WordPress Version',
                health.wp_version,
                health.wp_status,
                health.wp_status === 'warning' ? 'Update available' : 'WordPress is up to date'
            ));

            // Memory Usage
            grid.append(this.createHealthItem(
                'Memory Usage',
                `${health.memory_usage} / ${health.memory_limit}`,
                health.memory_status,
                `${health.memory_usage_percent}% of available memory used`
            ));

            // Max Execution Time
            if (health.max_execution_time) {
                grid.append(this.createHealthItem(
                    'Max Execution Time',
                    `${health.max_execution_time}s`,
                    health.execution_status,
                    health.execution_status === 'warning' ? 'Consider increasing max execution time' : 'Execution time limit is adequate'
                ));
            }

            // Database Version
            if (health.db_version) {
                grid.append(this.createHealthItem(
                    'Database Version',
                    health.db_version,
                    health.db_status,
                    'Database version is adequate'
                ));
            }

            // Add the grid to the container
            container.empty().append(grid);
        },

        /**
         * Create a health item element.
         *
         * @param {string} title - The title of the health item.
         * @param {string} value - The value of the health item.
         * @param {string} status - The status of the health item (good, warning, error).
         * @param {string} description - The description of the health item.
         * @return {jQuery} The health item element.
         */
        createHealthItem: function(title, value, status, description) {
            return $(`
                <div class="status-sentry-health-item status-${status}">
                    <h4>${title}</h4>
                    <div class="status-sentry-health-value">${value}</div>
                    <div class="status-sentry-health-description">${description}</div>
                </div>
            `);
        },

        /**
         * Generate dummy timeline data.
         *
         * @return {Object} The dummy timeline data.
         */
        generateDummyTimelineData: function() {
            const dates = [];
            for (let i = 6; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                dates.push(date.toISOString().split('T')[0]);
            }

            const eventTypes = ['info', 'warning', 'error', 'critical', 'performance', 'security', 'conflict', 'health'];
            const colors = {
                info: 'rgba(54, 162, 235, 0.7)',
                warning: 'rgba(255, 206, 86, 0.7)',
                error: 'rgba(255, 99, 132, 0.7)',
                critical: 'rgba(153, 51, 51, 0.7)',
                performance: 'rgba(75, 192, 192, 0.7)',
                security: 'rgba(153, 102, 255, 0.7)',
                conflict: 'rgba(255, 159, 64, 0.7)',
                health: 'rgba(102, 204, 102, 0.7)'
            };

            const datasets = [];
            eventTypes.forEach(type => {
                const data = [];
                for (let i = 0; i < 7; i++) {
                    // Generate random counts, with more common event types having higher counts
                    switch (type) {
                        case 'info':
                        case 'warning':
                            data.push(Math.floor(Math.random() * 6));
                            break;
                        case 'error':
                        case 'performance':
                            data.push(Math.floor(Math.random() * 4));
                            break;
                        default:
                            data.push(Math.floor(Math.random() * 2));
                            break;
                    }
                }

                datasets.push({
                    label: type.charAt(0).toUpperCase() + type.slice(1),
                    data: data,
                    backgroundColor: colors[type],
                    borderColor: colors[type],
                    borderWidth: 1
                });
            });

            return {
                labels: dates,
                datasets: datasets
            };
        },

        /**
         * Render the dashboard layout.
         */
        renderDashboard: function() {
            // Only create the layout once
            if (this.container.hasClass('loaded')) {
                return;
            }

            // Create dashboard layout
            const layout = `
                <div class="status-sentry-dashboard-header">
                    <button id="status-sentry-refresh-dashboard" class="button button-primary">
                        <span class="dashicons dashicons-update"></span> Refresh Dashboard
                    </button>
                    <span class="status-sentry-last-refresh">Last refreshed: Just now</span>
                </div>
                <div class="status-sentry-dashboard-grid">
                    <div class="status-sentry-recommendations"></div>
                    <div class="status-sentry-kpi-cards"></div>
                    <div class="status-sentry-filters">
                        <select id="status-sentry-event-type-filter">
                            <option value="all">All Event Types</option>
                            <option value="info">Info</option>
                            <option value="warning">Warning</option>
                            <option value="error">Error</option>
                            <option value="critical">Critical</option>
                            <option value="performance">Performance</option>
                            <option value="security">Security</option>
                            <option value="conflict">Conflict</option>
                            <option value="health">Health</option>
                        </select>
                    </div>
                    <div class="status-sentry-charts">
                        <div class="status-sentry-chart">
                            <h3>Event Types Distribution</h3>
                            <canvas id="eventTypeChart"></canvas>
                        </div>
                        <div class="status-sentry-chart">
                            <h3>Event Timeline (Last 7 Days)</h3>
                            <canvas id="eventTimelineChart"></canvas>
                        </div>
                    </div>
                    <div class="status-sentry-system-health">
                        <h3>System Health</h3>
                        <div id="status-sentry-system-health"></div>
                    </div>
                    <div class="status-sentry-recent-events">
                        <h3>Recent Events</h3>
                        <div class="status-sentry-events-table"></div>
                    </div>
                    <div class="status-sentry-baselines">
                        <h3>System Baselines</h3>
                        <div class="status-sentry-baselines-header">
                            <button id="status-sentry-refresh-baselines" class="button">
                                <span class="dashicons dashicons-update"></span> Refresh Baselines
                            </button>
                        </div>
                        <div class="status-sentry-baselines-content"></div>
                    </div>
                </div>
            `;

            this.container.html(layout);
            this.container.addClass('loaded');

            // Set up event type filter
            $('#status-sentry-event-type-filter').on('change', (e) => {
                const selectedType = $(e.target).val();
                this.filterRecentEvents(selectedType);
            });
        },

        /**
         * Filter recent events by type.
         *
         * @param {string} type The event type to filter by, or 'all' for all types.
         */
        filterRecentEvents: function(type) {
            const table = this.container.find('.status-sentry-events-table table');
            if (!table.length) {
                return;
            }

            const rows = table.find('tbody tr');
            if (type === 'all') {
                rows.show();
            } else {
                rows.each(function() {
                    const eventType = $(this).find('td:nth-child(2)').text().trim().toLowerCase();
                    if (eventType === type) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            }
        },

        /**
         * Render overview data.
         *
         * @param {Object} data The overview data.
         */
        renderOverview: function(data) {
            this.renderDashboard();

            // Store the last known values for use in error fallbacks
            if (data.event_counts) {
                this.lastKnownEventCounts = data.event_counts;
            }

            if (data.resource_status) {
                this.lastKnownResourceStatus = data.resource_status;
            }

            // Render recommendations
            this.renderRecommendations(data.recommendations);

            // Render KPI cards
            this.renderKPICards(data.event_counts, data.resource_status);
        },

        /**
         * Render recommendations.
         *
         * @param {Array} recommendations The recommendations.
         */
        renderRecommendations: function(recommendations) {
            const container = this.container.find('.status-sentry-recommendations');
            container.empty();

            if (!recommendations || recommendations.length === 0) {
                return;
            }

            // Get the first recommendation
            const recommendation = recommendations[0];

            // Create recommendation banner
            const banner = $('<div class="status-sentry-recommendation"></div>');
            banner.addClass('status-sentry-recommendation-' + recommendation.type);

            // Add icon based on type
            let icon = 'dashicons-yes-alt';
            if (recommendation.type === 'warning') {
                icon = 'dashicons-warning';
            } else if (recommendation.type === 'error') {
                icon = 'dashicons-dismiss';
            }

            banner.html(`
                <span class="dashicons ${icon}"></span>
                <div class="status-sentry-recommendation-content">
                    <p class="status-sentry-recommendation-message">${recommendation.message}</p>
                    <p class="status-sentry-recommendation-action">${recommendation.action}</p>
                </div>
            `);

            container.append(banner);

            // If there are more recommendations, add a count
            if (recommendations.length > 1) {
                const count = $('<span class="status-sentry-recommendation-count"></span>');
                count.text(recommendations.length - 1 + ' more');
                banner.append(count);
            }
        },

        /**
         * Render KPI cards.
         *
         * @param {Object} event_counts The event counts.
         * @param {Object} resource_status The resource status.
         */
        renderKPICards: function(event_counts, resource_status) {
            const container = this.container.find('.status-sentry-kpi-cards');
            container.empty();

            // Create event count cards
            for (const [feature, count] of Object.entries(event_counts)) {
                const card = $('<div class="status-sentry-kpi-card"></div>');
                const title = feature.replace(/_/g, ' ');

                card.html(`
                    <h3>${title.charAt(0).toUpperCase() + title.slice(1)}</h3>
                    <div class="status-sentry-kpi-value">${count}</div>
                    <div class="status-sentry-kpi-label">Events</div>
                `);

                container.append(card);
            }

            // Create resource status cards
            if (resource_status) {
                // Memory usage card
                if (resource_status.memory_usage_percent !== undefined) {
                    const memoryCard = $('<div class="status-sentry-kpi-card"></div>');
                    const memoryPercent = Math.round(resource_status.memory_usage_percent);

                    memoryCard.html(`
                        <h3>Memory Usage</h3>
                        <div class="status-sentry-kpi-value">${memoryPercent}%</div>
                        <div class="status-sentry-kpi-progress">
                            <div class="status-sentry-kpi-progress-bar" style="width: ${memoryPercent}%"></div>
                        </div>
                    `);

                    container.append(memoryCard);
                }

                // CPU load card
                if (resource_status.cpu_load !== undefined) {
                    const cpuCard = $('<div class="status-sentry-kpi-card"></div>');
                    const cpuLoad = Math.round(resource_status.cpu_load);

                    cpuCard.html(`
                        <h3>CPU Load</h3>
                        <div class="status-sentry-kpi-value">${cpuLoad}%</div>
                        <div class="status-sentry-kpi-progress">
                            <div class="status-sentry-kpi-progress-bar" style="width: ${cpuLoad}%"></div>
                        </div>
                    `);

                    container.append(cpuCard);
                }
            }
        },

        /**
         * Render trends data and chart methods removed
         */

        /**
         * Render recent events.
         *
         * @param {Object} data The recent events data.
         */
        renderRecentEvents: function(data) {
            this.renderDashboard();

            const container = this.container.find('.status-sentry-events-table');
            container.empty();

            if (!data.events || data.events.length === 0) {
                container.html('<p>No recent events found.</p>');
                return;
            }

            // Create table
            const table = $('<table class="widefat status-sentry-table"></table>');

            // Add header
            const header = $('<thead></thead>');
            header.html(`
                <tr>
                    <th>Feature</th>
                    <th>Hook</th>
                    <th>Time</th>
                </tr>
            `);
            table.append(header);

            // Add body
            const body = $('<tbody></tbody>');

            data.events.forEach(event => {
                const row = $('<tr></tr>');
                row.html(`
                    <td>${event.feature_name}</td>
                    <td>${event.hook}</td>
                    <td>${event.time_ago}</td>
                `);
                body.append(row);
            });

            table.append(body);
            container.append(table);

            // Add view all button
            let eventsUrl = 'admin.php?page=status-sentry-events';
            // Use admin_url function if available, otherwise use the adminUrl from localized data
            if (typeof admin_url === 'function') {
                eventsUrl = admin_url(eventsUrl);
            } else if (statusSentry && statusSentry.adminUrl) {
                eventsUrl = statusSentry.adminUrl + eventsUrl;
            }

            const viewAllButton = $('<a href="' + eventsUrl + '" class="button">View All Events</a>');
            container.append($('<p></p>').append(viewAllButton));
        },

        /**
         * Render baselines.
         *
         * @param {Object} data The baselines data.
         */
        renderBaselines: function(data) {
            this.renderDashboard();

            const container = this.container.find('.status-sentry-baselines-content');
            container.empty();

            if (!data.baselines || data.baselines.length === 0) {
                container.html('<p>No baseline data available.</p>');
                return;
            }

            // Create table
            const table = $('<table class="widefat status-sentry-table"></table>');

            // Add header
            const header = $('<thead></thead>');
            header.html(`
                <tr>
                    <th data-key="label">Metric</th>
                    <th data-key="value">Value</th>
                    <th data-key="unit">Unit</th>
                    <th data-key="threshold">Threshold</th>
                    <th data-key="time_ago">Last Updated</th>
                </tr>
            `);
            table.append(header);

            // Add body
            const body = $('<tbody></tbody>');

            // Process baselines
            const baselines = data.baselines.map(baseline => {
                // Use label if available, otherwise use metric
                const label = baseline.label || baseline.metric;

                // Extract numeric value and unit if they're combined
                let value = baseline.value;
                let unit = baseline.unit || '';

                if (typeof value === 'string' && !baseline.unit) {
                    // Try to extract unit from the value string
                    const matches = value.match(/^([\d.]+)\s*(.*)$/);
                    if (matches && matches.length > 2) {
                        value = matches[1];
                        unit = matches[2].trim();
                    }
                }

                return {
                    ...baseline,
                    label: label,
                    value: value,
                    unit: unit,
                    threshold: baseline.threshold || '20%'
                };
            });

            // Render each baseline
            baselines.forEach(baseline => {
                const row = $('<tr></tr>');

                // Add deviation class if needed
                if (baseline.is_deviation) {
                    row.addClass('status-sentry-baseline-deviation');
                }

                row.html(`
                    <td>${baseline.label}</td>
                    <td>${baseline.value}</td>
                    <td>${baseline.unit}</td>
                    <td>${baseline.threshold}</td>
                    <td>${baseline.time_ago}</td>
                `);
                body.append(row);
            });

            table.append(body);
            container.append(table);

            // Add sorting functionality
            this.addTableSorting(table);
        },

        /**
         * Add sorting functionality to a table.
         *
         * @param {jQuery} table The table to add sorting to.
         */
        addTableSorting: function(table) {
            const headers = table.find('th');

            headers.each(function() {
                $(this).css('cursor', 'pointer');
                $(this).append(' <span class="sort-indicator"></span>');
            });

            headers.on('click', function() {
                const header = $(this);
                const key = header.data('key');
                const tbody = table.find('tbody');
                const rows = tbody.find('tr').toArray();
                const index = header.index();

                // Toggle sort direction
                const isAscending = header.hasClass('asc');

                // Remove sort classes from all headers
                headers.removeClass('asc desc');
                headers.find('.sort-indicator').text('');

                // Add sort class to clicked header
                header.addClass(isAscending ? 'desc' : 'asc');
                header.find('.sort-indicator').text(isAscending ? ' ▼' : ' ▲');

                // Sort rows
                rows.sort(function(a, b) {
                    const aValue = $(a).find('td').eq(index).text();
                    const bValue = $(b).find('td').eq(index).text();

                    // Try to sort numerically if possible
                    const aNum = parseFloat(aValue);
                    const bNum = parseFloat(bValue);

                    if (!isNaN(aNum) && !isNaN(bNum)) {
                        return isAscending ? bNum - aNum : aNum - bNum;
                    }

                    // Fall back to string comparison
                    return isAscending ?
                        bValue.localeCompare(aValue) :
                        aValue.localeCompare(bValue);
                });

                // Reattach sorted rows
                tbody.empty();
                rows.forEach(function(row) {
                    tbody.append(row);
                });
            });
        },

        /**
         * Render error message.
         *
         * @param {string} section The section that failed to load.
         */
        renderError: function(section) {
            this.renderDashboard();

            let container;

            switch (section) {
                case 'overview':
                    container = this.container.find('.status-sentry-recommendations, .status-sentry-kpi-cards');

                    // Get last known values or use empty objects if none exist
                    const lastEventCounts = this.lastKnownEventCounts || {
                        core_monitoring: 0,
                        db_monitoring: 0,
                        conflict_detection: 0,
                        performance_monitoring: 0
                    };

                    const lastResourceStatus = this.lastKnownResourceStatus || {};

                    // Provide fallback data for overview using last known values when available
                    const fallbackOverview = {
                        event_counts: lastEventCounts,
                        resource_status: lastResourceStatus,
                        recommendations: [
                            {
                                type: 'warning',
                                message: 'Could not load live data. Using last known values or defaults.',
                                action: 'Check server logs for more information.'
                            }
                        ]
                    };

                    // Render fallback data
                    this.renderOverview(fallbackOverview);
                    break;

                // Trends case removed

                case 'recent':
                    container = this.container.find('.status-sentry-events-table');
                    container.html('<div class="status-sentry-error"><p>Failed to load recent events. Please refresh the page to try again.</p></div>');
                    break;

                case 'baselines':
                    container = this.container.find('.status-sentry-baselines-content');
                    container.html('<div class="status-sentry-error"><p>Failed to load baseline data. Please refresh the page to try again.</p></div>');
                    break;

                default:
                    return;
            }
        },

        /**
         * Generate fallback trend data method removed
         */

        /**
         * Generate fallback events data.
         *
         * @return {Array} The fallback events data.
         */
        generateFallbackEvents: function() {
            const events = [];
            const features = ['Core Monitoring', 'DB Monitoring', 'Conflict Detection', 'Performance Monitoring'];
            const hooks = ['init', 'wp_loaded', 'admin_init', 'wp_footer', 'shutdown'];
            const now = new Date();

            // Generate 10 events
            for (let i = 0; i < 10; i++) {
                const feature = features[Math.floor(Math.random() * features.length)];
                const hook = hooks[Math.floor(Math.random() * hooks.length)];
                const hours = Math.floor(Math.random() * 24);

                events.push({
                    id: i + 1,
                    feature: feature.toLowerCase().replace(' ', '_'),
                    feature_name: feature,
                    hook: hook,
                    time_ago: hours + ' hours ago'
                });
            }

            return events;
        },

        /**
         * Generate fallback baselines data.
         *
         * @return {Array} The fallback baselines data.
         */
        generateFallbackBaselines: function() {
            const baselines = [];
            const metrics = ['Memory Usage', 'CPU Load', 'Database Size', 'Query Time', 'Page Load'];
            const units = ['MB', '%', 'MB', 'ms', 'ms'];
            const thresholds = ['20%', '15%', '25%', '30%', '20%'];

            // Generate 5 baselines
            for (let i = 0; i < 5; i++) {
                const days = Math.floor(Math.random() * 7) + 1;
                const value = Math.floor(Math.random() * 100);
                const isDeviation = Math.random() > 0.8; // 20% chance of deviation

                baselines.push({
                    id: i + 1,
                    metric: metrics[i],
                    label: metrics[i],
                    value: value,
                    unit: units[i],
                    threshold: thresholds[i],
                    is_deviation: isDeviation,
                    time_ago: days + ' days ago',
                    last_updated: new Date(Date.now() - (days * 86400000)).toISOString()
                });
            }

            return baselines;
        }
    };

    // Initialize the dashboard when the document is ready
    $(document).ready(function() {
        // Disable jQuery caching for all AJAX requests to ensure fresh data
        $.ajaxSetup({
            cache: false,
            headers: {
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                'Expires': '0'
            }
        });

        // Initialize the dashboard
        window.statusSentryDashboard.init();
    });

})(jQuery);
