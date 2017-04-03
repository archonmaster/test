/**
 * Gallery field.
 */

(function($){
  
    angular.module('cockpit.fields').run(['Contentfields', function(Contentfields) {

        Contentfields.register('photopicker', {
            label: 'Photopicker',
            template: function(model, options) {
                var new_options = {};
                if (angular.isDefined(options.maxWidth)) new_options.maxWidth = options.maxWidth;
                if (angular.isDefined(options.maxHeight)) new_options.maxHeight = options.maxHeight;
              	if (angular.isDefined(options.convert2jpg)) new_options.convert2jpg = options.convert2jpg;
              	if (angular.isDefined(options.img_count)) new_options.img_count = options.img_count;
              	if (angular.isDefined(options.showtitle)) new_options.showtitle = options.showtitle;
                if (Object.keys(new_options).length == 0) new_options = false;
                return '<photopicker ng-model="'+model+'" options=\''+JSON.stringify(new_options || false)+'\'></gallery>';
            }
        });

    }]);

  	App.module.factory("$photopicker", function() {
      	return {
          	processFiles: function(files, id) {
              	var removeList = [];
              	var moveList = [];
              	for (var name in files) {
                  	for (var i=0; i < files[name].length; i++) {
                      	var file = files[name][i];
                      	if (!angular.isDefined(file.temp_folder)) file.temp_folder = "";
                      	if (angular.isDefined(file.removed)) {
                          	removeList.push(file.path);
                          	files[name].splice(i, 1);
                          	i--;
                        } else if (file.temp_folder != "") {
                          	var filename = file.path.replace(file.temp_folder, "").replace(/^\/+|\/+$/g, "");
                          	if (!angular.isDefined(moveList[file.temp_folder])) {
                                moveList[file.temp_folder] = {
                                  	"cmd":"movefiles",
                                    "path": file.temp_folder,
                                    "dest": 'site:'+['uploads', id].join('/').replace(/^\/+|\/+$/g, ""),
                                    "files": [],
                                    "remove_source_dir": true
                                };
                            }
                          	moveList[file.temp_folder].files.push(filename);
                            file.path = [moveList[file.temp_folder]["dest"], filename].join('/').replace(/^\/+|\/+$/g, "");
                            file.temp_folder = "";
                        }
                    }
                }
              
                if (removeList.length > 0) {
                    $.post(App.route('/mediamanager/api'), {
                        "cmd":"removefiles",
                        "paths": removeList
                    });
                }
              
              	for (var i in moveList) {
                  	$.post(App.route('/mediamanager/api'), moveList[i]);
                }
            }
        };
    });
  
    angular.module('cockpit.fields').directive("photopicker", ['$timeout', function($timeout){

        return {

            restrict: 'E',
            require: 'ngModel',
            scope: {
                images: "=ngModel",
            },
            templateUrl: App.base('/assets/js/angular/fields/tpl/photopicker.html'),

            link: function (scope, elm, attrs, ngModel) {

                $timeout(function(){
                    
                    if (!angular.isArray(scope.images)) {
                        scope.images = [];
                    }
                    
                    var site_base  = COCKPIT_SITE_BASE_URL.replace(/^\/+|\/+$/g, ""),
                        media_base = COCKPIT_MEDIA_BASE_URL.replace(/^\/+|\/+$/g, ""),
                        site2media = media_base.replace(site_base, "").replace(/^\/+|\/+$/g, "");
                    
                    scope.options = {
                        maxWidth: "auto",
                        maxHeight: "auto",
                      	convert2jpg: false,
                      	img_count: 5,
                      	showtitle: false
                    }
                    
                    if (angular.isDefined(attrs.options)) {
                        try {
                            var options = angular.fromJson(attrs.options);
                            if (angular.isDefined(options.maxWidth)) scope.options.maxWidth = options.maxWidth;
                            if (angular.isDefined(options.maxHeight)) scope.options.maxHeight = options.maxHeight;
                          	if (angular.isDefined(options.convert2jpg)) scope.options.convert2jpg = options.convert2jpg;
                          	if (angular.isDefined(options.img_count)) scope.options.img_count = options.img_count;
                          	if (angular.isDefined(options.showtitle)) scope.options.showtitle = options.showtitle;
                        } catch(e) {}
                    }
                  
                    /*
                    var getImageSize = function(img) {
                        var url = img.path.replace("site:", COCKPIT_SITE_BASE_URL);
                        var image = new Image();
                        image.link = img;
                        image.onload = function() {
                            this.link.width = this.width;
                            this.link.height = this.height;
                            if (!scope.$$phase) scope.$apply();
                        }
                        image.src = url;
                    }
                    
                    for (img in scope.images) {
                        getImageSize(scope.images[img]);
                    }
                    */
                    
                    App.assets.require(UIkit.Utils.xhrupload ? [] : ['assets/vendor/uikit/js/components/upload.min.js'], function() {
                        var uploadsettings = {
                            "action": App.route('/mediamanager/api'),
                            "single": true,
                            "params": {"cmd":"upload", "mode":"temp_folder", "uniq": "true", "maxWidth": scope.options.maxWidth, "maxHeight": scope.options.maxHeight, "convert2jpg": scope.options.convert2jpg},
                            "before": function(o) {
                                if (typeof scope.temp_folder != "undefined") {
                                    o.params["path"] = scope.temp_folder;
                                }
                            },
                            "loadstart": function(){
                                var loader = elm.find(".photo-loader");
                                var progressBar = loader.find(".uk-progress-bar");
                                loader.removeClass("uk-hidden");                                
                                progressBar.css("width", "5%");
                                progressBar.text("0%");
                            },
                            "progress": function(percent){
                                if (percent == 100) percent = 99;
                                var loader = elm.find(".photo-loader");
                                var progressBar = loader.find(".uk-progress-bar");
                                progressBar.css("width", percent+"%");
                                progressBar.text(percent+"%");
                            },
                            "complete": function(response) {
                                response = $.parseJSON(response);
                                if (response && response.path) {
                                    scope.temp_folder = response.path;
                                }
                                
                                if (response && response.uploaded && response.uploaded.length) {
                                    for (var i = 0; i < response.uploaded.length; i++) {
                                        var mediapath = 'site:'+[site2media, response.path, response.uploaded[i]].join('/').replace(/^\/+|\/+$/g, "");
                                        var temp_folder = 'site:'+[site2media, response.path].join('/').replace(/^\/+|\/+$/g, "");
                                        var image = {
                                            "path": mediapath,
                                            "title": "",
                                            "folder": "",
                                            temp_folder: temp_folder
                                        };
                                        if (!angular.isArray(scope.images)) {
                                            scope.images = [];
                                        }
                                        scope.images.push(image);
                                        //var index = scope.images.push(image);
                                        //getImageSize(scope.images[index - 1]);
                                    }
                                    if (!scope.$$phase) {
                                        scope.$apply();
                                    }
                                }
                            },
                            "allcomplete": function(response){
                                var loader = elm.find(".photo-loader");
                                var progressBar = loader.find(".uk-progress-bar");
                                progressBar.css("width", "100%");
                                progressBar.text("100%");
                                loader.addClass("uk-hidden");
                            }
                        };
                        var uploadselect = new UIkit.uploadSelect(elm.find('input.js-upload-select'), uploadsettings);
                    });


                        
                        // Создать уникальную временную папку
                        // Открыть окно выбора файлов на локальном компьютере
                        // Сохранять файлы во временную папку
                        // При сохранении записи:
                        // Если запись вновь создаваемая, то создать папку для записи
                        // Сихнранизировать постоянную и временную папку:
                        // Лишние файлы из постоянной папки удалить
                        // Файлы из временной папки переместить в постоянную, при этом
                        // изменить пути в массиве
                        
                        // Модуль галереи ответственнен за отображение изображений в форме записи
                        // Модуль медиаменеджера ответственнен за загрузку файлов, создание временно папки и синхранизации
                        
                        ///////////////////
                                                                
                    scope.getUrl = function(img) {
                        return img.path.replace("site:", COCKPIT_SITE_BASE_URL);
                    }
                    
                    scope.getExtension = function(img) {
                      	var ext = img.path.split('.').pop();
                      	var img_types = ["jpg", "jpeg", "png", "gif"];
                      	var img_type = (img_types.indexOf(ext) > -1) ? true : false;
                      	return {
                          	ext: ext,
                          	img_type: img_type
                        }
                    }
                    
                    scope.removeImage = function(index) {
                        scope.images[index]["removed"] = true;
                        //if (!scope.$$phase) scope.$apply();
                    };

                    scope.emptyGallery = function() {

                        App.Ui.confirm(App.i18n.get("Are you sure?"), function(){
                            for (var i = 0; i < scope.images.length; i++) scope.images[i]["removed"] = true;
                            if (!scope.$$phase) scope.$apply();
                        });
                    };

                    scope.updateTitle = function(img) {

                        var title = prompt(App.i18n.get("Title"), img.title);

                        if (title!==null) {

                            img.title = title;
                        }
                    };

                  	scope.processFiles = function(entry_id) {
                      	
                    };

                    App.assets.require(UIkit.sortable ? []:['assets/vendor/uikit/js/components/sortable.min.js'], function(){

                        var $list = elm.find('.uk-grid').on("change.uk.sortable",function(e, sortable, ele){

                            ele = angular.element(ele);

                            $timeout(function(){
                                scope.images.splice(ele.index(), 0, scope.images.splice(scope.images.indexOf(ele.scope().img), 1)[0]);
                            });
                        });

                        UIkit.sortable($list);
                    });

                });
            }
        };

    }]);

})(jQuery);
