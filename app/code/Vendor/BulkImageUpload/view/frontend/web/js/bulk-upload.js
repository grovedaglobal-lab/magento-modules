define([
    'jquery',
    'mage/translate',
    'mage/url'
], function ($, $t, urlBuilder) {
    'use strict';

    function escapeHtml(string) {
        return $('<div>').text(string).html();
    }

    return function (config, element) {
        var $form = $(element);
        var $messages = $('#bulk-upload-messages');
        var $progressContainer = $('#bulk-upload-progress-container');
        var $progressBar = $('#bulk-upload-progress-bar');
        var $progressText = $('#bulk-upload-status-text');
        var $progressPercentage = $('#bulk-upload-percentage');
        var $results = $('#bulk-upload-results');
        var $submitBtn = $('#bulk-upload-submit-btn');

        var pollInterval;

        $form.on('submit', function (e) {
            e.preventDefault();

            var formData = new FormData($form[0]);
            var fileInput = $('#bulk_image_zip')[0];
            if (!fileInput.files.length) {
                showMessage($t('Please select a ZIP file.'), 'error');
                return;
            }

            $submitBtn.prop('disabled', true);
            $messages.hide();
            $results.hide();
            $progressContainer.show();
            updateProgress(0, $t('Uploading & Extracting ZIP...'));

            $.ajax({
                url: $form.attr('action'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                showLoader: false,
                success: function (response) {
                    if (response.success && response.job_id) {
                        updateProgress(10, $t('Validating SKU matching...'));
                        startPolling(response.job_id);
                    } else {
                        handleError(response.error || $t('Upload failed.'));
                    }
                },
                error: function (xhr, status, error) {
                    handleError(error || $t('A network error occurred.'));
                }
            });
        });

        function startPolling(jobId) {
            var statusUrl = urlBuilder.build('marketplace/bulkimage/status') + '?job_id=' + encodeURIComponent(jobId);
            
            pollInterval = setInterval(function () {
                $.ajax({
                    url: statusUrl,
                    type: 'GET',
                    showLoader: false,
                    success: function (res) {
                        if (res.error) {
                            clearInterval(pollInterval);
                            handleError(res.error);
                            return;
                        }

                        var total = res.total || 0;
                        var processed = res.processed || 0;
                        var pct = total > 0 ? Math.round((processed / total) * 100) : 0;
                        
                        // Scale percentage from 10% to 100% since 0-10% was upload phase
                        var actualPct = 10 + (pct * 0.9);
                        
                        if (res.status === 'processing') {
                            updateProgress(actualPct, $t('Processing images...') + ' (' + processed + '/' + total + ')');
                        } else if (res.status === 'completed' || res.status === 'failed') {
                            clearInterval(pollInterval);
                            updateProgress(100, res.status === 'completed' ? $t('Completed!') : $t('Failed.'));
                            setTimeout(function() {
                                $progressContainer.hide();
                                renderResults(res.result_json, res.status);
                                $submitBtn.prop('disabled', false);
                                $form[0].reset();
                                // Reload page to update job history table
                                setTimeout(function() { window.location.reload(); }, 3000);
                            }, 1000);
                        }
                    },
                    error: function () {
                        clearInterval(pollInterval);
                        handleError($t('Lost connection to server.'));
                    }
                });
            }, 2000); // poll every 2 seconds
        }

        function updateProgress(pct, text) {
            $progressBar.css('width', pct + '%');
            $progressPercentage.text(Math.round(pct) + '%');
            if (text) {
                $progressText.empty();
                $progressText.append($('<i>').addClass('fas fa-spinner fa-spin')).append(' ' + escapeHtml(text));
                if (pct === 100) {
                    $progressText.empty();
                    $progressText.append($('<i>').addClass('fas fa-check-circle')).append(' ' + escapeHtml(text));
                }
            }
        }

        function handleError(msg) {
            $progressContainer.hide();
            $submitBtn.prop('disabled', false);
            showMessage(msg, 'error');
        }

        function showMessage(msg, type) {
            var color = type === 'error' ? '#e02b27' : '#006400';
            var bgColor = type === 'error' ? '#fae5e5' : '#e5efe5';
            $messages.empty();
            var $alert = $('<div>')
                .css({
                    'padding': '10px 20px',
                    'color': color,
                    'background-color': bgColor,
                    'border-radius': '4px'
                })
                .text(msg);
            $messages.append($alert).show();
        }

        function renderResults(resultJson, status) {
            if (!resultJson) {
                showMessage($t('Job finished but no detailed results were returned.'), status === 'completed' ? 'success' : 'error');
                return;
            }
            
            var html = '<div class="dashboard-card shadow-sm">';
            html += '<div class="card-header"><h3><i class="fas fa-clipboard-list"></i> ' + $t('Upload Results') + '</h3></div>';
            html += '<div class="card-content">';
            
            html += '<div style="margin-bottom: 20px;"><strong>' + $t('Processed SKUs') + ':</strong> ' + escapeHtml(resultJson.successCount || 0) + '</div>';
            
            if (resultJson.globalErrors && resultJson.globalErrors.length > 0) {
                html += '<div style="color: #e02b27; margin-bottom: 15px;"><strong>Global Errors:</strong><ul>';
                resultJson.globalErrors.forEach(function(err) {
                    html += '<li>' + escapeHtml(err) + '</li>';
                });
                html += '</ul></div>';
            }
            
            if (resultJson.errors && Object.keys(resultJson.errors).length > 0) {
                html += '<div class="table-wrapper shadow-xs rounded-lg overflow-hidden border border-gray-100">';
                html += '<table class="shipping-rates-table"><thead><tr>';
                html += '<th class="col label">SKU / File</th><th class="col type">Error</th>';
                html += '</tr></thead><tbody>';
                
                $.each(resultJson.errors, function(identifier, errors) {
                    html += '<tr>';
                    html += '<td class="col label font-bold text-gray-700">' + escapeHtml(identifier) + '</td>';
                    html += '<td class="col type text-sm text-red-600"><ul>';
                    if (Array.isArray(errors)) {
                        errors.forEach(function(err) {
                            html += '<li>' + escapeHtml(err) + '</li>';
                        });
                    } else {
                        html += '<li>' + escapeHtml(errors) + '</li>';
                    }
                    html += '</ul></td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table></div>';
            } else {
                html += '<div style="color: #0b9b3e; font-weight: bold;"><i class="fas fa-check-circle"></i> ' + $t('All images processed successfully with no errors!') + '</div>';
            }
            
            html += '</div></div>';
            $results.html(html).show();
        }
    };
});