/**
 * Status Sentry Benchmark JavaScript
 *
 * This file handles the benchmark UI, including responsive charts,
 * print functionality, and CSV export.
 *
 * @since      1.6.0
 * @package    Status_Sentry
 */

(function($) {
    'use strict';

    // Benchmark app
    window.statusSentryBenchmark = {
        /**
         * Initialize the benchmark UI.
         */
        init: function() {
            this.setupCharts();
            this.setupActions();
            this.setupResponsiveness();
            this.setupFullWidthToggle();
        },

        /**
         * Set up responsive charts.
         */
        setupCharts: function() {
            // Memory usage chart
            if (document.getElementById('memoryChart')) {
                this.createResponsiveChart(
                    'memoryChart',
                    'Memory Usage (bytes)',
                    window.statusSentryBenchmarkData.memoryData,
                    window.statusSentryBenchmarkData.labels,
                    'rgba(54, 162, 235, 0.5)',
                    'rgba(54, 162, 235, 1)'
                );
            }

            // Execution time chart
            if (document.getElementById('timeChart')) {
                this.createResponsiveChart(
                    'timeChart',
                    'Execution Time (seconds)',
                    window.statusSentryBenchmarkData.timeData,
                    window.statusSentryBenchmarkData.labels,
                    'rgba(255, 99, 132, 0.5)',
                    'rgba(255, 99, 132, 1)'
                );
            }

            // Operations per second chart
            if (document.getElementById('opsChart')) {
                this.createResponsiveChart(
                    'opsChart',
                    'Operations Per Second',
                    window.statusSentryBenchmarkData.opsData,
                    window.statusSentryBenchmarkData.labels,
                    'rgba(75, 192, 192, 0.5)',
                    'rgba(75, 192, 192, 1)'
                );
            }

            // History chart
            if (document.getElementById('historyChart')) {
                this.createHistoryChart();
            }
        },

        /**
         * Create a responsive chart.
         *
         * @param {string} id The chart canvas ID.
         * @param {string} label The chart label.
         * @param {Array} data The chart data.
         * @param {Array} labels The chart labels.
         * @param {string} backgroundColor The background color.
         * @param {string} borderColor The border color.
         */
        createResponsiveChart: function(id, label, data, labels, backgroundColor, borderColor) {
            const ctx = document.getElementById(id).getContext('2d');

            // Store chart instance for later reference
            this.charts = this.charts || {};

            // Destroy existing chart if it exists
            if (this.charts[id]) {
                this.charts[id].destroy();
            }

            // Create new chart
            this.charts[id] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: label,
                        data: data,
                        backgroundColor: backgroundColor,
                        borderColor: borderColor,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },

        /**
         * Create the history chart.
         */
        createHistoryChart: function() {
            console.log('Creating history chart');
            const historyCanvas = document.getElementById('historyChart');

            // Check if the history chart canvas exists
            if (!historyCanvas) {
                console.log('History chart canvas not found, skipping chart creation');
                return;
            }

            // Ensure the canvas has the correct width
            historyCanvas.style.width = '100%';
            historyCanvas.style.minWidth = '100%';

            const ctx = historyCanvas.getContext('2d');

            // Destroy existing chart if it exists
            if (this.charts && this.charts.historyChart) {
                this.charts.historyChart.destroy();
            }

            // Create new chart
            this.charts = this.charts || {};

            // Debug the history data
            console.log('History data:', window.statusSentryBenchmarkHistoryData);

            try {
                this.charts.historyChart = new Chart(ctx, {
                    type: 'line',
                    data: window.statusSentryBenchmarkHistoryData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Operations Per Second'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Benchmark Date'
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
                            }
                        }
                    }
                });
                console.log('History chart created successfully');
            } catch (error) {
                console.error('Error creating history chart:', error);
            }

            console.log('History chart created with width:', historyCanvas.width, 'style width:', historyCanvas.style.width);
        },

        /**
         * Set up action buttons.
         */
        setupActions: function() {
            // Print results
            $('.status-sentry-print-results').on('click', function(e) {
                e.preventDefault();
                window.print();
            });

            // Export as CSV
            $('.status-sentry-export-csv').on('click', function(e) {
                e.preventDefault();
                window.statusSentryBenchmark.exportToCSV();
            });
        },

        /**
         * Export benchmark results to CSV.
         */
        exportToCSV: function() {
            // Get table data
            const table = document.querySelector('.status-sentry-table');
            if (!table) return;

            const rows = table.querySelectorAll('tr');
            let csv = [];

            // Process each row
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');

                for (let j = 0; j < cols.length; j++) {
                    // Get the text content and clean it
                    let data = cols[j].textContent.replace(/(\r\n|\n|\r)/gm, '').trim();
                    // Escape double quotes
                    data = data.replace(/"/g, '""');
                    // Add quotes around the data
                    row.push('"' + data + '"');
                }

                csv.push(row.join(','));
            }

            // Create CSV content
            const csvContent = csv.join('\n');

            // Create download link
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.setAttribute('href', url);
            link.setAttribute('download', 'status-sentry-benchmark-' + new Date().toISOString().slice(0, 10) + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        },

        /**
         * Set up responsive behavior.
         */
        setupResponsiveness: function() {
            // Redraw charts on window resize
            $(window).on('resize', function() {
                window.statusSentryBenchmark.setupCharts();
            });
        },

        /**
         * Set up full width toggle functionality.
         */
        setupFullWidthToggle: function() {
            console.log('Setting up full width toggle');

            // Toggle full width mode
            $(document).on('click', '#status-sentry-toggle-fullwidth', function(e) {
                e.preventDefault();
                console.log('Toggle full width button clicked');

                // Toggle the class on the body
                $('body').toggleClass('status-sentry-fullwidth-mode');
                console.log('Full width mode toggled. Active:', $('body').hasClass('status-sentry-fullwidth-mode'));

                // Update button text
                if ($('body').hasClass('status-sentry-fullwidth-mode')) {
                    $(this).html('<span class="dashicons dashicons-editor-contract" style="margin-top: 3px;"></span> Exit Full Width');
                } else {
                    $(this).html('<span class="dashicons dashicons-editor-expand" style="margin-top: 3px;"></span> Toggle Full Width');
                }

                // Force a small delay before redrawing charts to ensure the DOM has updated
                setTimeout(function() {
                    // Redraw charts to fit the new width
                    window.statusSentryBenchmark.setupCharts();

                    // Specifically handle the history chart if it exists
                    if (document.getElementById('historyChart')) {
                        const historyCanvas = document.getElementById('historyChart');
                        historyCanvas.style.width = '100%';
                        historyCanvas.style.minWidth = '100%';
                        window.statusSentryBenchmark.createHistoryChart();
                    }

                    console.log('Charts redrawn after toggle');
                }, 100);

                // Store the preference in localStorage
                localStorage.setItem('status_sentry_fullwidth_mode', $('body').hasClass('status-sentry-fullwidth-mode'));
                console.log('Preference saved to localStorage');
            });

            // Check if full width mode was previously enabled
            var savedPreference = localStorage.getItem('status_sentry_fullwidth_mode');
            console.log('Saved full width preference:', savedPreference);

            if (savedPreference === 'true') {
                console.log('Applying saved full width preference');
                $('body').addClass('status-sentry-fullwidth-mode');
                $('.status-sentry-toggle-fullwidth').html('<span class="dashicons dashicons-editor-contract" style="margin-top: 3px;"></span> Exit Full Width');

                // Force a small delay before redrawing charts to ensure the DOM has updated
                setTimeout(function() {
                    // Redraw charts to fit the new width
                    window.statusSentryBenchmark.setupCharts();

                    // Specifically handle the history chart if it exists
                    if (document.getElementById('historyChart')) {
                        const historyCanvas = document.getElementById('historyChart');
                        historyCanvas.style.width = '100%';
                        historyCanvas.style.minWidth = '100%';
                        window.statusSentryBenchmark.createHistoryChart();
                    }

                    console.log('Charts redrawn after applying saved preference');
                }, 100);
            }
        }
    };

    // Initialize the benchmark UI when the document is ready
    $(document).ready(function() {
        // Initialize the benchmark UI
        window.statusSentryBenchmark.init();
    });

})(jQuery);
