define([
    'Magento_Ui/js/form/element/text',
    'uiRegistry'
], function (Text, registry) {
    'use strict';

    return Text.extend({
        defaults: {
            listens: {
                'value': 'onValueChange'
            }
        },

        onValueChange: function (value) {
            if (typeof value === 'undefined' || value === null) {
                return;
            }

            var slug = value.toString().toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .trim()
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-');

            // Try to find the sibling element 'shop_url'
            // parentName is usually the container (fieldset)
            var shopUrlName = this.parentName + '.shop_url';
            
            registry.get(shopUrlName, function (shopUrlComponent) {
                if (shopUrlComponent) {
                    shopUrlComponent.value(slug);
                }
            });
        }
    });
});
