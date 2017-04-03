@start('header')

    {{ $app->assets(['collections:assets/collections.js','collections:assets/js/entries.js'], $app['cockpit/version']) }}
    {{ $app->assets(['assets:js/angular/directives/mediapreview.js'], $app['cockpit/version']) }}
    {{ $app->assets(['https://yastatic.net/share2/share.js']) }}

    @trigger('cockpit.content.fields.sources')

    @if($collection['sortfield'] == 'custom-order')

        {{ $app->assets(['assets:vendor/uikit/js/components/sortable.min.js'], $app['cockpit/version']) }}

    @endif

    <style>

        td .uk-grid+.uk-grid { margin-top: 5px; }

        .type-media .media-url-preview {
            border-radius: 50%;
        }

        .uk-sortable-dragged {
            border: 1px #ccc dashed;
            height: 40px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 2px;
        }

        .uk-sortable-dragged td {
            display: none;
        }
        
        .title {
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .price {
            margin: 10px 0;            
        }
        .price span {
            font-weight: bold;
        }
        
        .ya-share2 {
            margin-top: 10px;
        }
        
        .ya-share2 ul li {
            display: block !important;
            margin-bottom: 5px !important;
            margin-right: 0 !important;
        }
        
        .ya-share2 ul li:last-child {
            margin-bottom: 0 !important;
        }

    </style>

    <script>
        var COLLECTION = {{ json_encode($collection) }};
        var FILTER = {{ json_encode($app->param('filter', '')) }};
        var CURRENT_DATE = '{{ date("Y-m-d") }}';
        var CURRENT_TIME = '{{ time() }}';
        
        angular.module('cockpit.directives').directive("socialSharing", ['$timeout', function($timeout) {
            return {
                restrict: 'A',

                link: function(scope, elm, attrs) {                    
                    
                    var load = function() {
                        Ya.share2(elm[0], {theme: {size: 's'}});
                    };

                    $timeout(load);
                }
            };
        }]);
        
        angular.module('cockpit.directives').directive("itemUp", ['$timeout', '$http', function($timeout, $http) {
            return {
                restrict: 'A',

                link: function(scope, elm, attrs) {
                    scope.item_id = attrs.itemUp;
                    
                    var itemUp = function() {
                        var item = angular.fromJson(attrs.itemUp);
                        var entry_id = item.entry_id;
                        var index = item.index;
                        
                        $http.post(App.route("/api/collections/entries"), {

                            "collection" : COLLECTION,
                            "filter"     : '{"_id": "'+entry_id+'"}'

                        }, {responseType:"json"}).success(function(data){
                            if (data) {                                
                                if (data.length == 1) {
                                    var entry = data[0];                                    
                                    entry.date = CURRENT_DATE;
                                    entry.created = CURRENT_TIME;
                                    entry.modified = CURRENT_TIME;
                                    $http.post(App.route("/api/collections/saveentry"), {
                                        "collection" : COLLECTION,
                                        "createversion": true,
                                        "entry"     : entry
                                    }, {responseType:"json"}).success(function(data){
                                        if (data) {
                                            scope.$parent.entries.splice(index, 1);
                                            scope.$parent.entries.unshift(entry);
                                            scope.$parent.$apply();
                                            App.notify("Запись поднята наверх", "success");
                                        }
                                    }).error(App.module.callbacks.error.http);
                                } else {
                                    App.module.callbacks.error.http();
                                }
                            }
                        }).error(App.module.callbacks.error.http);
                    };
                    
                    $(elm).on("click", function() {
                        itemUp();
                    });
                }
            };
        }]);

    </script>

@end('header')



<div data-ng-controller="entries" ng-cloak>

    <nav class="uk-navbar uk-margin-bottom">
        <span class="uk-navbar-brand"><a href="@route("/collections")">@lang('Collections')</a> / {{ $collection['name'] }}</span>
        <ul class="uk-navbar-nav">
            @hasaccess?("Collections", 'manage.collections')
            <li><a href="@route('/collections/collection/'.$collection["_id"])" title="@lang('Edit collection')" data-uk-tooltip="{pos:'bottom'}"><i class="uk-icon-pencil"></i></a></li>
            @hasaccess?("Collections", 'view.all.entries')
            <li><a class="uk-text-danger" ng-click="emptytable()" title="@lang('Empty collection')" data-uk-tooltip="{pos:'bottom'}"><i class="uk-icon-trash-o"></i></a></li>
            @end
            @end
            <li><a href="@route('/collections/entry/'.$collection["_id"])" title="@lang('Add entry')" data-uk-tooltip="{pos:'bottom'}"><i class="uk-icon-plus-circle"></i></a></li>
        </ul>

        @if($collection['sortfield'] != 'custom-order')
        <div class="uk-navbar-content" data-ng-show="collection && collection.count">
            <form class="uk-form uk-margin-remove uk-display-inline-block" method="get" action="?nc={{ time() }}">
                <div class="uk-form-icon">
                    <i class="uk-icon-filter"></i>
                    <input type="text" placeholder="@lang('Filter entries...')" name="filter[search]" value="@@ filter['search'] @@"> &nbsp;
                    <a class="uk-text-small" href="@route('/collections/entries/'.$collection['_id'])" data-ng-show="filter"><i class="uk-icon-times"></i> @lang('Reset filter')</a>
                </div>
            </form>
        </div>
        @endif

        <div class="uk-navbar-flip">
            @hasaccess?("Collections", 'manage.collections')
            <ul class="uk-navbar-nav">
                <li>
                    <a href="@route('/api/collections/export/'.$collection['_id'])" download="{{ $collection['name'] }}.json" title="@lang('Export data')" data-uk-tooltip="{pos:'bottom'}">
                        <i class="uk-icon-share-alt"></i>
                    </a>
                </li>
            </ul>
            @end
        </div>
    </nav>
    
    <nav class="uk-navbar uk-margin-bottom">
        <form class="uk-form" method="get" action="?nc={{ time() }}">
            <ul class="uk-navbar-nav">            
                <li class="uk-margin-right uk-margin-bottom" data-ng-repeat="field in filter_fields" data-ng-if="field.filter==true">
                    <span>@@ field.label @@:</span>
                    
                    <contentfield data-name="filter[@@ field.name @@]" data-ng-if="field.type=='link-collection'" options="@@ field @@" ng-model="filter[field.name]"></contentfield>
                    
                    <contentfield data-name="filter[@@ field.name @@]" data-ng-if="field.type=='select'" options="@@ field @@" ng-model="filter[field.name]"></contentfield>
                    
                    <select name="filter[@@ field.name @@]" data-ng-if="field.type=='select1'">
                        <option data-ng-repeat="option in field.options" value="@@ option.value @@" ng-selected="option.value=='@@ filter[field.name] @@'">@@ option.label @@</option>
                    </select>
                    <span data-ng-if="field.type=='text'">
                        от <input class="uk-form-width-small" name="filter[@@ field.name @@][min]" value="@@ filter[field.name]['min'] @@">
                        до <input class="uk-form-width-small" name="filter[@@ field.name @@][max]" value="@@ filter[field.name]['max'] @@">
                    </span>
                </li>
                <li><input type="submit" value="Фильтровать" class="uk-button uk-button-primary"></li>
            </ul>
        </form>
    </nav>

    <div class="app-panel uk-margin uk-text-center" data-ng-show="entries && !filter && !entries.length">
        <h2><i class="uk-icon-list"></i></h2>
        <p class="uk-text-large">
            @lang('It seems you don\'t have any entries created.')
        </p>
        <a href="@route('/collections/entry/'.$collection["_id"])" class="uk-button uk-button-success uk-button-large">@lang('Add entry')</a>
    </div>

    <div class="app-panel uk-margin uk-text-center" data-ng-show="entries && filter && !entries.length">
        <h2><i class="uk-icon-search"></i></h2>
        <p class="uk-text-large">
            @lang('No entries found.')
        </p>
    </div>

    <div class="uk-grid" data-uk-grid-margin data-ng-show="entries && entries.length">

        <div class="uk-width-1-1">
            <div class="app-panel">
                <table id="entries-table" class="uk-table uk-table-striped" multiple-select="{model:entries}">
                    <thead>
                        <tr>
                            <th width="10"><input class="js-select-all" type="checkbox"></th>
                            <th>Фото</th>
                            <th>Описание</th>
                            <th width="15%"></th>
                            <th width="10%">&nbsp;</th>
                        </tr>
                    </thead>
                    <tbody {{ $collection['sortfield'] == 'custom-order' ? 'data-uk-sortable="{animation:false}"':'' }}>
                        <tr class="js-multiple-select" data-ng-repeat="entry in entries track by entry._id">
                            <td><input class="js-select" type="checkbox"></td>
                            <td><img ng-src="@route('/mediamanager/thumbnail')/@@ entry['photos'][0].path|base64 @@/150/150" title="@@ entry['title'] @@"></td>
                            <td>
                                <div class="title"><a href="@route('/collections/entry/'.$collection["_id"])/@@ entry._id @@" target="_blank">@@ entry.item_id @@: @@ entry.title @@</a></div>
                                <div class="address">@@ entry.full_address @@</div>
                                <div class="level">Этаж/этажность: @@ entry.level @@/@@ entry.levels @@</div>
                                <div class="area">Площадь (общ/жид/кухня): @@ entry.area @@/@@ entry.usefull_area @@/@@ entry.kitchen_area @@</div>
                                
                                <div class="price">Стоимость: <span>@@ entry.price @@ руб.</span></div>
                            </td>
                            <td>                                
                                <ul>
                                    <li class="date">Дата: @@ entry.modified | fmtdate:'d M, Y' @@</li>
                                    <li>Опубликован: @@ entry.public && 'да' || 'нет' @@</li>
                                    <li>На баннере: @@ entry.top100 && 'да' || 'нет' @@</li>
                                    <li>Продано: @@ entry.sold && 'да' || 'нет' @@</li>
                                    <li>Просмотров: @@ entry.counter @@</li>
                                </ul>                                
                            </td>
                            <td class="uk-text-right">
                                <div data-uk-dropdown>
                                    <i class="uk-icon-bars"></i>
                                    <div class="uk-dropdown uk-dropdown-flip uk-text-left">
                                        <ul class="uk-nav uk-nav-dropdown uk-nav-parent-icon">
                                            <li><a href="@route('/collections/entry/'.$collection["_id"])/@@ entry._id @@"><i class="uk-icon-pencil"></i> @lang('Edit entry')</a></li>
                                            <li><a href="#!" data-item-up='{"entry_id": "@@ entry._id @@", "index": @@ $index @@}'><i class="uk-icon-cloud-upload"></i> Поднять объект</a></li>
                                            <li><a href="#" data-ng-click="remove($index, entry._id)"><i class="uk-icon-trash-o"></i> @lang('Delete entry')</a></li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="ya-share2" data-services="vkontakte,facebook,odnoklassniki,twitter" data-url="http://kapital93.ru/object/@@ entry._id @@/" social-sharing></div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="uk-margin-top">
                    @if($collection['sortfield'] != 'custom-order')
                    <button class="uk-button uk-button-primary" data-ng-click="loadmore()" data-ng-show="entries && !nomore">@lang('Load more...')</button>
                    @endif
                    <button class="uk-button uk-button-danger" data-ng-click="removeSelected()" data-ng-show="selected"><i class="uk-icon-trash-o"></i> @lang('Delete entries')</button>
                </div>

            </div>
        </div>
</div>
