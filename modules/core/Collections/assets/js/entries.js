(function($){

    App.module.controller("entries", function($scope, $rootScope, $http, $timeout){

        $scope.collection = COLLECTION || {};
        $scope.fields = [];
        //$scope.search = $('input[name="filter"]').val();
        
        var filter_fields = [];
        COLLECTION.fields.forEach(function(field) {
            /*
            if (field.type == 'select') {
                var options = [{value: 'null', label: 'не выбран'}];
                if (angular.isArray(field.options)) {
                    field.options.forEach(function(opt) {
                        options.push({value: opt, label: opt});
                    });
                }
                field.options = options;
            }
            */
            filter_fields.push(field);
        });
        
        $scope.filter = FILTER || false;
        // hack for angularjs and selectbox
        for (var f in $scope.filter) {
            if (typeof($scope.filter[f]) == 'string') {
                if ($scope.filter[f].indexOf('undefined:undefined') > -1) delete $scope.filter[f];
            }
        }
        $scope.filter_fields = filter_fields;
        
        $scope.fields = (COLLECTION.fields.length ? COLLECTION.fields : [COLLECTION.fields]).filter(function(field){
            return field.lst;
        });
        
        $scope.duplicate = function(index, entryId) {
            $http.post(App.route("/api/collections/duplicateEntry"), {

                "collectionId": angular.copy($scope.collection._id),
                "entryId": entryId

            }, {responseType:"json"}).success(function(data){
                
                if (data.success == false) {
                    App.module.callbacks.error.http(data.message);
                    return;
                }

                $timeout(function(){
                    
                    $scope.entries.splice(index+1, 0, data.entry);
                    $scope.collection.count += 1;
                    
                    console.log($scope.entries);
                    App.notify(App.i18n.get("Entry dublicated"), "success");
                }, 0);
            }).error(App.module.callbacks.error.http);
        };

        $scope.remove = function(index, entryId){
            App.Ui.confirm(App.i18n.get("Are you sure?"), function(){

                $http.post(App.route("/api/collections/removeentry"), {

                    "collection": angular.copy($scope.collection),
                    "entryId": entryId

                }, {responseType:"json"}).success(function(data){

                    $timeout(function(){
                        $scope.entries.splice(index, 1);
                        $scope.collection.count -= 1;

                        App.notify(App.i18n.get("Entry removed"), "success");
                    }, 0);
                }).error(App.module.callbacks.error.http);
            });
        };
      
      	var doFilter = function() {
          	var filter = false;
			if ($scope.filter) {
                var criterias = {};
                if ($scope.filter.search) {
                    COLLECTION.fields.forEach(function(field){
                        switch(field.type) {
                            case 'text':
                            case 'code':
                            case 'html':
                            case 'markdown':
                            case 'wysiwyg':
                                criterias[field.name] = {'$regex':$scope.filter.search};
                                break;
                        }
                    });
                    if (Object.keys(criterias).length) filter = {'$or':criterias};
                } else {
                    for(var field in $scope.filter) {
                        var value = $scope.filter[field];
                        if (value != 'null') {
                            if (value == "true") value = true;
                            if (value == "false") value = false;
                            var type = typeof(value);
                            if (type == "object") {
                                if (typeof value['$not'] != "undefined") {
                                    criterias[field] = value;
                                }
                                //if (value["min"] != "" && value["max"] != "") criterias[field] = {'$and': {'$gte': value['min'], '$lte': value['max']}};
                                if (typeof value["min"] != "undefined") {
                                    if (value["min"] != "") criterias[field] = {'$gte': value['min']};
                                }
                                if (typeof value["max"] != "undefined") {
                                    if (value["max"] != "") criterias[field] = {'$lte': value['max']};
                                }
                            } else {
                                criterias[field] = value;
                            }
                        }
                    }
                    if (Object.keys(criterias).length) filter = {'$and':criterias};
                }
            }
          	return filter;
        };

        $scope.loadmore = function() {

            var limit  = COLLECTION.sortfield == 'custom-order' ? 10000 : 100;
          	var filter = doFilter();

            $http.post(App.route("/api/collections/entries"), {

                "collection" : angular.copy($scope.collection),
                "limit"      : limit,
                "filter"     : JSON.stringify(filter),
                "skip"       : $scope.entries ? $scope.entries.length : 0,
                "sort"       : (COLLECTION.sortfield == 'custom-order' ? {"custom-order":1}:false)

            }, {responseType:"json"}).success(function(data){
                if (data) {
                    if (!$scope.entries) {
                        $scope.entries = [];
                    }
                    if (data.length) {
                        if (data.length < limit) {
                            $scope.nomore = true;
                        }
                        $scope.entries = $scope.entries.concat(data);
                    } else {
                       $scope.nomore = true;
                    }
                }
            }).error(App.module.callbacks.error.http);
        };
      
      	$scope.loadnew = function(callback) {
            var limit  = COLLECTION.sortfield == 'custom-order' ? 10000 : 100;
          	var filter = doFilter();
          
            $http.post(App.route("/api/collections/entries"), {

                "collection" : angular.copy($scope.collection),
                "limit"      : limit,
                "filter"     : JSON.stringify(filter),
                "skip"       : 0,
                "sort"       : (COLLECTION.sortfield == 'custom-order' ? {"custom-order":1}:false)

            }, {responseType:"json"}).success(function(data){
                if (data) {
                    if (!$scope.entries) {
                        $scope.entries = [];
                    }
                  	if ($scope.entries.length == 0) {
                        if (data.length) {
                            if (data.length < limit) {
                                $scope.nomore = true;
                            } else {
                              	$scope.nomore = false;
                            }
                            $scope.entries = $scope.entries.concat(data);
                          	Notification.requestPermission();
                            var mailNotification = new Notification("Санаторий Белое Озеро", {
                                tag : "books",
                                body : "Пришли новые брони!!!",
                                icon : "http://profpab.ru/img/logo.svg"
                            });
                            App.notify(data.length + " " + App.i18n.get("new entries"), "success");
                        } else {
                           $scope.nomore = true;
                        }
                    } else {
                      	if (data.length) {
                          	var list = [];
                          	var newlist = [];
                          	for (i in $scope.entries) list.push($scope.entries[i]._id);
                          	for (i in data) if (list.indexOf(data[i]._id) == -1) newlist.push(data[i]);
                          	if (newlist.length > 0) {
                              	$scope.entries = newlist.concat($scope.entries);
                              	Notification.requestPermission();
                                var mailNotification = new Notification("Санаторий Белое Озеро", {
                                    tag : "books",
                                    body : "Пришли новые брони!!!",
                                    icon : "http://profpab.ru/img/logo.svg"
                                });
                              	App.notify(newlist.length + " " + App.i18n.get("new entries"), "success");
                            }
                        }
                    }
                }
              	if (angular.isDefined(callback)) callback();
            }).error(function() {
              App.module.callbacks.error.http();
              if (angular.isDefined(callback)) callback();
            });
        };

        // batch actions

        $scope.selected = null;

        $scope.$on('multiple-select', function(e, data){
            $timeout(function(){
                $scope.selected = data.items.length ? data.items : null;
            }, 0);
        });

        $scope.removeSelected = function(){
            if ($scope.selected && $scope.selected.length) {

                App.Ui.confirm(App.i18n.get("Are you sure?"), function() {

                    var row, scope, $index, $ids = [], collection = angular.copy($scope.collection);

                    for(var i=0;i<$scope.selected.length;i++) {
                        row    = $scope.selected[i],
                        scope  = $(row).scope(),
                        entry  = scope.entry,
                        $index = scope.$index;

                        (function(row, scope, entry, $index){

                            $http.post(App.route("/api/collections/removeentry"), {
                                "collection": collection,
                                "entryId": entry._id
                            }, {responseType:"json"}).error(App.module.callbacks.error.http);

                            $ids.push(entry._id);
                            $scope.collection.count -= 1;

                        })(row, scope, entry, $index);
                    }

                    $scope.entries = $scope.entries.filter(function(entry){
                        return ($ids.indexOf(entry._id)===-1);
                    });
                });
            }
        };

        $scope.emptytable = function() {

            App.Ui.confirm(App.i18n.get("Are you sure?"), function() {
                $http.post(App.route("/api/collections/emptytable"), {

                    "collection": angular.copy($scope.collection)

                }, {responseType:"json"}).success(function(data){

                    $timeout(function(){
                        $scope.entries = [];
                        $scope.collection.count = 0;
                        App.notify(App.i18n.get("Done."), "success");
                    }, 0);

                }).error(App.module.callbacks.error.http);
            });
        };


        var table = $("#entries-table").on("change.uk.sortable",function(){

            var entries = [];

            table.find('tbody').children().each(function(i){

                var entry = angular.copy($(this).scope().entry);

                entry['custom-order'] = i;

                $http.post(App.route("/api/collections/saveentry"), {"collection": COLLECTION, "entry":entry, "createversion": false}).success(function(data){

                }).error(App.module.callbacks.error.http);

                entries.push(entry);
            });

            $scope.$apply(function(){
                $scope.entries = entries;
            });
        });

        $scope.loadmore();       
      
      	var Autoloader = function(timeout) {
          	if (timeout) this.timeout = timeout * 1000;
          	this.promise = false;
          	this.start = function() {
              	if (!COLLECTION.autoloader) return;
              	if (this.promise) return;
              	var $this = this;
                var autoloader = function() {
                    if (!$this.timeout) $scope.loadnew();
                    else {
                        $this.promise = $timeout(function() {
                            $scope.loadnew(autoloader);
                        }, $this.timeout);
                    }
                };
              	autoloader();
            };
          	this.stop = function() {
              	if (this.promise) {
                  	$timeout.cancel(this.promise);
                  	this.promise = false;
                }
            };
        };
      
      	$scope.autoloader = new Autoloader(COLLECTION.autoloader_timeout);
      	$scope.autoloader.start();
        
    });

})(jQuery);
