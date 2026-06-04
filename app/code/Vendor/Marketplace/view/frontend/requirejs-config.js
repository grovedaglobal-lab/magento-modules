var config = {
    map: {
        '*': {
            'Magento_Rule/rules': 'Vendor_Marketplace/js/rules',
            'Magento_Checkout/template/minicart/item/default.html': 'Vendor_Marketplace/template/minicart/item/default.html'
        },
    },
    paths: {
        'chartjs': 'https://cdn.jsdelivr.net/npm/chart.js/dist/chart.umd'
    },
    config: {
        mixins: {
            'Magento_Checkout/js/view/summary/item/details': {
                'Vendor_Marketplace/js/view/summary/item/details-mixin': true
            }
        }
    }
};

