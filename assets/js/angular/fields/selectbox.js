(function($){

    angular.module('cockpit.fields').run(['Contentfields', function(Contentfields) {

       Contentfields.register('select', {
           label: 'Select',
           template: function(model, options, name) {
                return '<selectbox'+(name ? ' data-name="'+name+'"':'')+' options=\''+JSON.stringify(options.options || false)+'\' parent="'+(options.parent || 'false')+'"'+(options.classes ? ' data-class="'+options.classes+'"':'')+' ng-model="'+model+'"></selectbox>';
           }
       });

    }]);
    
    angular.module('cockpit.fields').directive("selectbox", ['$timeout', '$rootScope', function($timeout, $rootScope){

        return {

            restrict: 'E',
            require: 'ngModel',        
            templateUrl: App.base('/assets/js/angular/fields/tpl/selectbox.html?v=0.1'),

            link: function (scope, elm, attrs, ngModel) {
                scope.options = JSON.parse(attrs.options);
                for (o in scope.options) {
                    if (typeof scope.options[o].key == 'undefined') {
                        scope.options[o] = {key: scope.options[o], value: scope.options[o]};
                    }
                }
                scope.classes = attrs.class;
                scope.name = attrs.name;
                $timeout(function(){
                    scope.option = ngModel.$viewValue;
                    scope.$watch('option', function() {
                        ngModel.$setViewValue(scope.option);
                    }, true);
                });
                
                scope.getOptions = function() {
                    var options;
                    var result = new Array;
                    if (attrs.parent == 'false') {                        
                        return scope.options;
                    } else {
                        return scope.parent.subOptions;
                    }
                };
                                
                if (attrs.parent != 'false') {
                    scope.hasParent = true;
                    var fields = scope.$parent.collection.fields;
                    for (var i in fields) {
                        if (fields[i].name == attrs.parent) {
                            scope.parent = fields[i];
                        }
                    }
                    scope.$watch('parent', function() {
                        if (typeof scope.parent.subOptions != "undefined")
                            ngModel.$setViewValue('');
                    }, true);
                } else {
                    scope.hasParent = false;
                }
            }
        };

    }]);

})(jQuery);
