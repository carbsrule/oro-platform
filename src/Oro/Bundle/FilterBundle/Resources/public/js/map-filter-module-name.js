define(function() {
    'use strict';

    var moduleNameTemplate = 'oro/filter/{{type}}-filter';
    var types = {
            string:          'choice',
            choice:          'select',
            'date-grouping': 'select',
            single_choice:   'select',
            selectrow:       'select-row',
            multichoice:     'multiselect',
            boolean:         'select',
            dictionary:      'dictionary'
        };

    return function(type) {
        return moduleNameTemplate.replace('{{type}}', types[type] || type);
    };
});
