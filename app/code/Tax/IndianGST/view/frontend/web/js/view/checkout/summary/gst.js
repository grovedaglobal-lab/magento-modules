define([
    'Magento_Checkout/js/view/summary/abstract-total',
    'Magento_Checkout/js/model/quote',
    'Magento_Catalog/js/price-utils',
    'Magento_Checkout/js/model/totals'
], function (Component, quote, priceUtils, totals) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Tax_IndianGST/checkout/summary/gst'
        },

        /**
         * @return {Boolean}
         */
        isDisplayed: function () {
            return this.getPureValue() > 0;
        },

        /**
         * Get pure value
         * @return {Number}
         */
        getPureValue: function () {
            var total = totals.getSegment('tax_indiangst');
            if (total) {
                return parseFloat(total.value);
            }
            return 0;
        },

        /**
         * Get formatted value
         * @return {String}
         */
        getValue: function () {
            return this.getFormattedPrice(this.getPureValue());
        },

        /**
         * Get Breakdown
         * @return {Array}
         */
        getBreakdown: function () {
            var total = totals.getSegment('tax_indiangst');
            var breakdown = [];

            console.log("GST JS: Checking Segment", total);

            if (total && total.extension_attributes) {
                var attrs = total.extension_attributes;
                console.log('GST JS: Found Extension Attributes:', attrs);

                var isIncl = (attrs.is_inclusive === true || attrs.is_inclusive == 1 || attrs.is_inclusive === 'true' || attrs.is_inclusive == 3);
                var suffix = isIncl ? ' (Incl)' : ' (Excl)';

                if (attrs.cgst > 0) {
                    breakdown.push({
                        label: 'CGST' + suffix,
                        value: this.getFormattedPrice(attrs.cgst)
                    });
                }
                if (attrs.sgst > 0) {
                    breakdown.push({
                        label: 'SGST' + suffix,
                        value: this.getFormattedPrice(attrs.sgst)
                    });
                }
                if (attrs.igst > 0) {
                    breakdown.push({
                        label: 'IGST' + suffix,
                        value: this.getFormattedPrice(attrs.igst)
                    });
                }
            }

            return breakdown;
        }
    });
});
