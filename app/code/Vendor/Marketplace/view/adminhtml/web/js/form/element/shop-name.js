define([
    'Magento_Ui/js/form/element/text',
    'uiRegistry'
], function (Text, registry) {
    'use strict';

    return Text.extend({
        defaults: {
            tracks: {
                value: true
            }
        },

        /**
         * Invoked when element value is changed.
         *
         * @param {String} value
         */
        onUpdate: function (value) {
            this._super(value);

            // Simple slug generation: lowercase, replace spaces with hyphens, remove special chars
            var slug = value.toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '') // remove non-alphanums (except spaces and hyphens)
                .trim()
                .replace(/\s+/g, '-')       // replace spaces with hyphens
                .replace(/-+/g, '-');       // remove duplicate hyphens

            var shopUrlName = this.parentName + '.shop_url';
            registry.get(shopUrlName, function (shopUrlComponent) {
                // Only update if shop_url is empty OR if we assume they always want it synced until manually changed?
                // Request said: "when add shop name automaticly add into shop url"
                // Usually this means auto-fill.
                // Since user also said "shop url key disable", they can't manually change it anyway.
                // So we always overwrite.
                shopUrlComponent.value(slug);
            });
        }
    });
});
