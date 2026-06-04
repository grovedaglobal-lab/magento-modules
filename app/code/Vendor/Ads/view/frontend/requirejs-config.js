var config = {
    paths: {
        'chartjs': 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min',
        'chartjsAnnotation': 'https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@2.0.1/dist/chartjs-plugin-annotation.min'
    },
    shim: {
        'chartjsAnnotation': {
            deps: ['chartjs']
        }
    }
};
