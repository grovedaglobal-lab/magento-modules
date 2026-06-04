define([
    'Magento_Catalog/js/price-utils',
    'Magento_Checkout/js/model/quote'
], function (priceUtils, quote) {
    'use strict';

    return function (target) {
        return target.extend({
            defaults: {
                template: 'Vendor_Marketplace/checkout/summary/item/details'
            },

            /**
             * format price
             * @param {Number} price
             * @returns {String}
             */
            getFormattedPrice: function (price) {
                return priceUtils.formatPrice(price, quote.getPriceFormat());
            },

            /**
             * Check if discount applies
             * @param {Object} item
             * @return {Boolean}
             */
            hasDiscount: function (item) {
                return item.discount_amount && parseFloat(item.discount_amount) > 0;
            }
        });
    };
});
