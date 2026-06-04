/**
 * Custom Vendor Select Component
 * Populates other fields when a vendor is selected.
 */
define([
    'Magento_Ui/js/form/element/select',
    'uiRegistry',
    'jquery'
], function (Select, registry, $) {
    'use strict';

    return Select.extend({
        defaults: {
            // No specific defaults needed
        },

        /**
         * On value change
         * @param {String} value
         */
        onUpdate: function (value) {
            // FORCE LOGGING
            if (window.console) {
                console.log('>>> VENDOR SELECT: onUpdate triggered with value:', value);
                console.log('>>> VENDOR SELECT: Configured Service URL:', this.serviceUrl);
            }

            var self = this;
            this._super();

            if (!value) {
                return;
            }

            var serviceUrl = this.serviceUrl;

            // Fallback hardcoded URL if config is missing (for debugging)
            if (!serviceUrl) {
                console.warn('>>> VENDOR SELECT: Service URL missing, using fallback.');
                serviceUrl = 'indiangst/vendor/getVendorData';
            }

            console.log('>>> VENDOR SELECT: Final AJAX URL:', serviceUrl);

            var setFieldValue = function (fieldName, data) {
                if (data !== undefined && data !== null) {
                    console.log('>>> VENDOR SELECT: Setting field', fieldName, 'to', data);
                    registry.get(self.parentName + '.' + fieldName, function (component) {
                        component.value(data);
                    });
                }
            };

            // AJAX call to get vendor details
            if (serviceUrl) {
                $.ajax({
                    url: serviceUrl,
                    data: { vendor_id: value, form_key: window.FORM_KEY },
                    type: 'POST',
                    dataType: 'json',
                    showLoader: true,
                    success: function (data) {
                        console.log('>>> VENDOR SELECT: AJAX Success. Data:', data);
                        if (data) {
                            setFieldValue('business_name', data.business_name);
                            setFieldValue('gstin', data.gstin);
                            setFieldValue('pan_number', data.pan_number);
                            setFieldValue('postcode', data.postcode);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('>>> VENDOR SELECT: AJAX Error:', status, error);
                        console.log('Response:', xhr.responseText);
                    }
                });
            }
        }
    });
});
