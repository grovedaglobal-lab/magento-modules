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
                showMessage($t('Please select one or more files to upload.'), 'error');
                return;
            }

            $submitBtn.prop('disabled', true);
            $messages.hide();
            $results.hide();
            $progressContainer.show();
            updateProgress(0, $t('Uploading files... Please do not refresh the page!'));

            $.ajax({
                url: $form.attr('action'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                showLoader: false,
                success: function (response) {
                    if (response.success && response.job_id) {
                        updateProgress(10, $t('Upload received, validating and processing...'));
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
                        } else if (res.status === 'completed' || res.status === 'partial' || res.status === 'failed') {
                            clearInterval(pollInterval);
                            var statusText = $t('Completed!');
                            if (res.status === 'partial') statusText = $t('Completed with some issues.');
                            if (res.status === 'failed') statusText = $t('Processing failed.');

                            updateProgress(100, statusText);
                            setTimeout(function() {
                                $progressContainer.hide();
                                renderResults(res.result_json, res.status);
                                $submitBtn.prop('disabled', false);
                                $form[0].reset();
                                
                                var hasErrors = res.result_json && (
                                    res.result_json.hasErrors || 
                                    (res.result_json.errors && Object.keys(res.result_json.errors).length > 0) ||
                                    (res.result_json.globalErrors && res.result_json.globalErrors.length > 0)
                                );

                                setTimeout(function() { window.location.reload(); }, hasErrors ? 8000 : 3000);
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
            
            var allErrors = {};
            if (resultJson.errors && typeof resultJson.errors === 'object') {
                allErrors = resultJson.errors;
            } else {
                if (resultJson.notFoundSkus && resultJson.notFoundSkus.length) {
                    resultJson.notFoundSkus.forEach(function(s) {
                        allErrors[s] = ['SKU not found in catalog.'];
                    });
                }
                if (resultJson.unauthorizedSkus && resultJson.unauthorizedSkus.length) {
                    resultJson.unauthorizedSkus.forEach(function(s) {
                        allErrors[s] = ['SKU does not belong to your vendor account.'];
                    });
                }
                if (resultJson.skippedFiles) {
                    $.each(resultJson.skippedFiles, function(f, r) {
                        allErrors[f] = [r];
                    });
                }
                if (resultJson.skippedSkus) {
                    $.each(resultJson.skippedSkus, function(s, r) {
                        allErrors[s] = [r];
                    });
                }
                if (resultJson.failedSkus) {
                    $.each(resultJson.failedSkus, function(s, r) {
                        allErrors[s] = [r];
                    });
                }
            }

            var hasErrors = Object.keys(allErrors).length > 0 || (resultJson.globalErrors && resultJson.globalErrors.length > 0);
            var successCount = resultJson.successCount || 0;

            var html = '<div class="dashboard-card shadow-sm">';
            html += '<div class="card-header"><h3><i class="fas fa-clipboard-list"></i> ' + $t('Upload Results') + '</h3></div>';
            html += '<div class="card-content">';
            
            html += '<div style="margin-bottom: 15px; display: flex; gap: 20px; font-size: 0.95rem;">';
            html += '<span style="color: #0b9b3e; font-weight: bold;"><i class="fas fa-check"></i> ' + $t('Successfully Assigned SKUs') + ': ' + escapeHtml(successCount) + '</span>';
            if (hasErrors) {
                html += '<span style="color: #dc2626; font-weight: bold;"><i class="fas fa-exclamation-circle"></i> ' + $t('Errors / Skipped') + ': ' + Object.keys(allErrors).length + '</span>';
            }
            html += '</div>';
            
            if (resultJson.globalErrors && resultJson.globalErrors.length > 0) {
                html += '<div style="color: #e02b27; margin-bottom: 15px;"><strong>Global Errors:</strong><ul>';
                resultJson.globalErrors.forEach(function(err) {
                    html += '<li>' + escapeHtml(err) + '</li>';
                });
                html += '</ul></div>';
            }
            
            if (hasErrors) {
                html += '<div class="table-wrapper shadow-xs rounded-lg overflow-hidden border border-gray-100 mb-4">';
                html += '<table class="shipping-rates-table"><thead><tr>';
                html += '<th class="col label" style="width: 35%;">' + $t('SKU / File') + '</th><th class="col type">' + $t('Error') + '</th>';
                html += '</tr></thead><tbody>';
                
                $.each(allErrors, function(identifier, errors) {
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
                html += '<div style="text-align: right;"><button type="button" class="action-btn secondary small" onclick="window.location.reload();">' + $t('Refresh Page') + '</button></div>';
            } else {
                html += '<div style="color: #0b9b3e; font-weight: bold;"><i class="fas fa-check-circle"></i> ' + $t('All images processed successfully with no errors!') + '</div>';
            }
            
            html += '</div></div>';
            $results.html(html).show();
        }
    };
});
