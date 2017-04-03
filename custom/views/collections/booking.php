@start('header')

{{ $app->assets(['collections:assets/collections.js','collections:assets/js/entries.js'], $app['cockpit/version']) }}
{{ $app->assets(['assets:js/angular/directives/mediapreview.js'], $app['cockpit/version']) }}

@trigger('cockpit.content.fields.sources')

@if($collection['sortfield'] == 'custom-order')

{{ $app->assets(['assets:vendor/uikit/js/components/sortable.min.js'], $app['cockpit/version']) }}

@endif

<style>


    table {
        font-size: 12px;
    }

    table .font11 {
        font-size: 11px;
    }

    .uk-table thead th,
    .uk-table td {
        vertical-align: middle;
    }

    td .uk-grid+.uk-grid { margin-top: 5px; }

    tr.marked {
        background: #ffdb77;
    }

    tr.new {
        border: 2px solid #ff0000;
    }

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

    .app-panel .uk-table-striped tbody tr:nth-of-type(odd) {
        background: #99BFDE;
    }
    .app-panel .uk-table-striped tbody tr.marked:nth-of-type(odd) {
        background: #f5c84d;
    }

    .comment {
        margin-left: 10px;
        cursor: pointer;
    }
    .comment img {
        max-height: 20px;
    }
    .status-selector {
        width: 20px;
        height: 20px;
        cursor: pointer;
        background-size: contain;
        background-repeat: no-repeat;
        background-position: center center;
    }
    .status-selector.nulled {
        background-image: url(/admin/assets/images/cancel.svg);
    }
    .status-selector.before {
        background-image: url(/admin/assets/images/before.svg);
    }
    .status-selector.process {
        background-image: url(/admin/assets/images/process.svg);
    }
    .status-selector.approved {
        background-image: url(/admin/assets/images/coins-bg.svg);
    }
    .status-selector.paid {
        background-image: url(/admin/assets/images/coins.svg);
    }
    .disable-form {
        background: rgba(255, 255, 255, 0.5);
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 1;
    }
    .uk-tooltip {
        max-width: 400px;
    }
    #change-status-dlg .uk-modal-dialog {
        width: 250px;
    }
    #change-price-dlg .uk-modal-dialog {
        width: 250px;
    }
    #change-price-dlg .uk-modal-dialog .uk-form-row:first-child {
        margin-top: 10px;
    }
    #change-price-dlg .uk-modal-dialog .uk-form-row:last-child {
        /* margin-top: 60px; */
    }
    a[enter-price] {
        color: #000;
        font-weight: bold;
    }

    /* =============== denis ====================================== */
    .trash {
        color: #ff0000;
        cursor: pointer;
    }
    .pencil {
        color: #0000cc;
        cursor: pointer;
    }
    .info {
        color: #35B3EE;
        cursor: pointer;
    }
    .edit {
        display: none;
    }
    .green {
        color: #00c000;
    }
    .red {
        color: #c40000;
    }
</style>

<?php
$filter = $app->param('filter', []);
$filter["removed"] = false;
//$filter["removed"] = ['$not' => true];

$rooms = [];
foreach(collection("Номерной фонд")->find()->toArray() as $room) {
  $rooms[$room["_id"]] = ["name" => $room["name"], "category" => $room["category"]];
}

$src_clients = collection("Клиенты")->find()->toArray();
$clients = [];
foreach($src_clients as $client) {
    $clients[$client["_id"]]["name"] = "{$client["surname"]} {$client["name"]} {$client["second_name"]}";
    $clients[$client["_id"]]["phone"] = $client["phone"];
    $clients[$client["_id"]]["email"] = $client["email"];
}
unset($src_clients);

$prepay = cockpit('regions:region_field', 'Настройки', 'prepay', 'value');
$days_to_pay = cockpit('regions:region_field', 'Настройки', 'days_to_pay', 'value');
?>

<script>
    var COLLECTION = {{ json_encode($collection) }};
    /* var FILTER = {{ json_encode($app->param('filter', '')) }}; */
    var FILTER = {{ json_encode($filter) }};
    var CURRENT_DATE = '{{ date("Y-m-d") }}';
    var CURRENT_TIME = '{{ time() }}';
    var ROOMS = {{ json_encode($rooms) }};
    var CLIENTS = {{ json_encode($clients) }};
    var PREPAY = {{ $prepay }};

    angular.module('cockpit.directives').factory('$entry', function($rootScope) {
        return $rootScope.$new(true);
    });

    angular.module('cockpit.directives')
        .directive("entryForm", ['$entry', '$http', '$photopicker', function($entry, $http, $photopicker) {
        return {
            restrict: 'A',
            scope: true,
            link: function(scope, elm, attrs) {
                scope.sendNotice = true;
                scope.entry = {};
                scope.fields = [];
                scope.submit = function() {};
                $(elm).find("[name]").each(function(i, e) {
                    scope.fields.push($(e).attr("name"));
                });
                $entry.$on("change", function(event, data, target) {
                    if (angular.isDefined(target) && target.length > 0) {
                        if (target[0] != elm[0]) return false;
                    }
                    scope.current_entry = data;
                    scope.entry = angular.copy(data);
                    scope.$apply();
                });
                $entry.$on("add-event", function(event, data, target) {
                    if (angular.isDefined(target) && target.length > 0) {
                        if (target[0] != elm[0]) return false;
                    }
                    if (data.name == "submit") scope.submit = data.action;
                });
                $(elm).on("submit", function() {
                    if (scope.fields.length == 0) {
                        scope.submit();
                        App.notify(App.i18n.get("Запись не сохранена"), "success");
                        return false;
                    }

                    var disabled = [];
                    $(elm).find("[name]").each(function(i, e) {
                        if (angular.isDefined($(e).attr("disabled"))) disabled.push($(e).attr("name"));
                    });
                    if (scope.fields.length == disabled.length) {
                        scope.submit();
                        App.notify(App.i18n.get("Запись не сохранена"), "success");
                        return false;
                    }

                    var spinner = $(elm).find("[type=submit] i");
                    spinner.removeClass("uk-hidden");

                    var files = {};
                    $(elm).find("[type=photopicker]").each(function(i, e) {
                        var name = $(e).attr("name");
                        if (disabled.indexOf(name) > -1) return;
                        files[name] = scope.entry[name];
                    });

                    if (Object.keys(files).length > 0) {
                        $photopicker.processFiles(files, scope.entry._id);
                    }

                    var entry = {
                        "_id": scope.entry._id
                    };

                    for (var i in scope.fields) {
                        if (angular.isDefined(scope.entry[scope.fields[i]])) {
                            if (disabled.indexOf(scope.fields[i]) == -1)
                                entry[scope.fields[i]] = angular.copy(scope.entry[scope.fields[i]]);
                        }
                    }

                    if (entry.length < 2) {
                        scope.submit();
                        App.notify(App.i18n.get("Запись не сохранена"), "success");
                        return false;
                    }

                    $http.post(App.route("/api/collections/updateentry"), {
                        "collection": angular.copy(scope.$parent.collection),
                        "entry": entry,
                        "createversion": true
                    }, {responseType:"json"}).success(function(response){
                        spinner.addClass("uk-hidden");
                        if (response.success == false) {
                            App.module.callbacks.error.http();
                            return false;
                        }
                        angular.copy(response, scope.current_entry);
                        scope.submit(scope);
                        App.notify(App.i18n.get("Запись успешно сохранена"), "success");
                    }).error(App.module.callbacks.error.http);
                    return false;
                });
            }
        };
    }]);

    angular.module('cockpit.directives')
        .directive("bookOptions", ['$entry', '$http', function($entry, $http) {
        return {
            restrict: 'A',
            link: function(scope, elm, attrs) {

                elm.on("click", function() {
                    var dlg = $("#book-options-dlg");
                    var modal = UIkit.modal(dlg);
                    dlg.one("hide.uk.modal", function() {
                        scope.$parent.autoloader.start();
                    });

                    scope.$parent.autoloader.stop();

                    $entry.$emit("change", scope.entry, dlg.find("[entry-form]"));
                    $entry.$emit("add-event", {name: "submit", action: function() {modal.hide();}}, dlg.find("[entry-form]"));
                    modal.show();
                    return false;
                });
            }
        };
    }]);

//    denis
    angular.module('cockpit.directives')
        .directive("bookPayTraffic", ['$entry', '$http', function($entry, $http) {
            return {
                restrict: 'A',
                link: function(scope, elm, attrs) {
                    elm.on("click", function() {
                        var dlg = $("#book-pay-traffic-dlg");
                        var modal = UIkit.modal(dlg);
                        dlg.one("hide.uk.modal", function() {
                            scope.$parent.autoloader.start();
                        });

                        scope.$parent.autoloader.stop();

                        $entry.$emit("change", scope.entry, dlg.find("[entry-form]"));
                        $entry.$emit(   "add-event",
                                        {name: "submit", action: function() {modal.hide();}},
                                        dlg.find("[entry-form]"));
                        modal.show();
                        return false;
                    });
                }
            };
        }]);

    angular.module('cockpit.directives')
        .directive("payWidget", ['$entry', function($entry) {
            return {
                restrict: 'A',
                link: function(scope, elm, attrs) {

                    @hasaccess?("Datastore", 'manage.datastore')
                        scope.canRemovePayments = true;
                    @end

                    scope.add_payment = function() {
                        if(angular.isUndefined(scope.entry.pay_traffic) || !angular.isArray(scope.entry.pay_traffic)) {
                            scope.entry.pay_traffic = [];
                        }
                        scope.entry.pay_traffic.push({
                            "type": "Перечислили объекту",
                            "summ": 0,
                            "comment": "",
                            "date": CURRENT_DATE
                        });
                    };

                    scope.remove_payment = function(index) {
                        if (scope.canRemovePayments) {
                            scope.entry.pay_traffic.splice(index, 1);
                        }
                    };

                    scope.edit_payment = function (index) {
                        if (scope.canRemovePayments) {
                            $("#pay-" + index).hide();
                            $("#edit-pay-" + index).show();
                        }
                    }
                }
            };
        }]);
//    eof denis

    angular.module('cockpit.directives').directive("bookEdit", ['$entry', '$http', function($entry, $http) {
        return {
            restrict: 'A',
            link: function(scope, elm, attrs) {

                elm.on("click", function() {
                    var dlg = $("#book-editing-dlg");
                    var modal = UIkit.modal(dlg);
                    dlg.one("hide.uk.modal", function() {
                        scope.$parent.autoloader.start();
                    });

                    scope.$parent.autoloader.stop();

                    $entry.$emit("change", scope.entry, dlg.find("[entry-form]"));
                    $entry.$emit("add-event", {name: "submit", action: function() {modal.hide();}}, dlg.find("[entry-form]"));
                    modal.show();
                    return false;
                });
            }
        };
    }]);

    angular.module('cockpit.directives').directive("marker", ['$http', function($http) {
        return {
            restrict: 'A',
            link: function(scope, elm, attrs) {
                if (angular.isDefined(scope.entry.marker)) {
                    $(elm).prop("checked", scope.entry.marker);
                }
                $(elm).on("change", function() {
                    scope.entry.marker = $(elm).prop("checked");
                    $http.post(App.route("/api/collections/updateentry"), {
                        "collection": angular.copy(scope.$parent.collection),
                        "entry": {
                            "_id": scope.entry._id,
                            "marker": scope.entry.marker
                        },
                        "createversion": false
                    }, {responseType:"json"}).success(function(response){
                        if (response.success == false) {
                            App.module.callbacks.error.http();
                            $(elm).prop("checked", !scope.entry.marker);
                            scope.entry.marker = !scope.entry.marker;
                        }
                        if (scope.entry.marker) {
                            App.notify(App.i18n.get("Запись отмечена как важная"), "success");
                        } else {
                            App.notify(App.i18n.get("Отметка важности снята"), "success");
                        }
                    }).error(function() {
                        App.module.callbacks.error.http();
                        $(elm).prop("checked", !scope.entry.marker);
                        scope.entry.marker = !scope.entry.marker;
                    });
                    return false;
                });
            }
        };
    }]);

    angular.module('cockpit.directives').directive("roomName", ['$timeout', function($timeout) {
        return {
            restrict: 'A',

            link: function(scope, elm, attrs) {
                var room = ROOMS[attrs.id];
              	var room_name = room.name;
              	if (room.category != null) room_name = room.category+": "+room_name;
                elm.text(room_name);
            }
        };
    }]);
    angular.module('cockpit.directives').directive("clientInfo", ['$timeout', function($timeout) {
        return {
            restrict: 'A',

            link: function(scope, elm, attrs) {
                var client = CLIENTS[attrs.id];
                elm.html('<div class="name">'+client.name+'</div></div class="contacts">'+client.phone+', '+client.email+'</div>');
            }
        };
    }]);
    angular.module('cockpit.directives').directive("statusSelector", ['$timeout', '$entry', '$http', function($timeout, $entry, $http) {
        return {
            restrict: 'A',

            link: function(scope, elm, attrs) {

                var dlg = $("#change-status-dlg");
                var modal = UIkit.modal(dlg);

                var options = {
                    "Аннулированная": "nulled",
                    "Предварительная": "before",
                    "На рассмотрении": "process",
                    "Одобренная к оплате": "approved",
                    "Оплаченная": "paid"
                };

                $(elm).addClass(options[scope.entry.status]);
                $(elm).on("click", function() {
                    dlg.one("hide.uk.modal", function() {
                        scope.$parent.autoloader.start();
                    });

                    scope.$parent.autoloader.stop();

                    $entry.$emit("change", scope.entry, dlg.find("[entry-form]"));
                    $entry.$emit("add-event", {name: "submit", action: function(event) {
                        if (event.sendNotice) {
                            var mailsubject = {
                                "Аннулированная": "Ваша бронь аннулирована",
                                "Предварительная": "Ваша бронь принята",
                                "На рассмотрении": "Ваша бронь на рассмотрении",
                                "Одобренная к оплате": "Ваша бронь одобрена",
                                "Оплаченная": "Ваша бронь оплачена"
                            };

                            $http.post(App.route("/api/forms/submit/booking"), {
                                "__csrf": "<?php echo $app->hash("booking"); ?>",
                                "__mailto": CLIENTS[event.entry.client]['email'],
                                "__mailsubject": mailsubject[event.entry.status],
                                "form": event.entry
                            });
                        }
                        modal.hide();
                    }}, dlg.find("[entry-form]"));
                    modal.show();
                    return false;
                });

                scope.$watch("entry.status", function(newValue, oldValue) {
                    $(elm).removeClass("nulled before process approved paid");
                    $(elm).addClass(options[newValue]);
                });
            }
        };
    }]);
    angular.module('cockpit.directives').directive("statusPayField", [function() {
        return {
            restrict: 'A',
            link: function(scope, elm, attrs) {
                scope.$watch("entry.status", function(newValue, oldValue) {
                    if (newValue == oldValue) return;
                    if (newValue == "Оплаченная") {
                        if (scope.entry.paid == 0) scope.entry.paid = scope.entry.summ * PREPAY;
                        $(elm).removeAttr("disabled");
                    }
                    else $(elm).attr("disabled", "disabled");
                });
            }
        };
    }]);
    angular.module('cockpit.directives').directive("statusDateField", [function() {
        return {
            restrict: 'A',
            link: function(scope, elm, attrs) {
                scope.$watch("entry.status", function(newValue, oldValue) {
                    if (newValue == oldValue) return;
                    if (newValue == "Одобренная к оплате") {
                        $(elm).removeAttr("disabled");
                    }
                    else $(elm).attr("disabled", "disabled");
                });
            }
        };
    }]);
    angular.module('cockpit.directives').directive("currentDate", ['$timeout', '$filter', function($timeout, $filter) {
        return {
            restrict: 'A',
            require: 'ngModel',
            scope: {
                ticket: "="
            },
            link: function(scope, elm, attrs, ngModel) {
                scope.current_date = new Date(CURRENT_DATE);

                scope.$watch("ticket", function() {
                    update();
                });

                var update = function() {
                    ngModel.$setViewValue($filter("date")(scope.current_date, "yyyy-MM-dd"));
                    ngModel.$render();
                };

                var inc = function() {
                    scope.current_date.setDate(scope.current_date.getDate() + 1);
                    console.log($filter("date")(scope.current_date, "yyyy-MM-dd"));
                    update();
                    $timeout(inc, 60*60*24*1000);
                };

                $timeout(inc, 60*60*24*1000);
            }
        };
    }]);
    angular.module('cockpit.directives').directive("prepaySizeField", ['$timeout', function($timeout) {
        return {
            restrict: 'A',
            link: function(scope, elm, attrs) {
                $(elm).text((PREPAY*100)+"%");
            }
        };
    }]);
    angular.module('cockpit.directives').directive("prepayField", ['$timeout', function($timeout) {
        return {
            restrict: 'A',
            link: function(scope, elm, attrs) {
                scope.$watch("entry.summ", function(newValue) {
                    $(elm).val(scope.entry.summ * PREPAY);
                });
            }
        };
    }]);
    angular.module('cockpit.directives').directive("dayDiff", ['$timeout', function($timeout) {
        return {
            restrict: 'A',

            link: function(scope, elm, attrs) {
                var start = attrs.start;
                var end = attrs.end;
                if (start == "now") start = new Date(Date.now()); else start = new Date(start);
                if (end == "now") end = new Date(Date.now()); else end = new Date(end);
                var max = attrs.max;
                var alter = attrs.alter;

                var timeDiff = end.getTime() - start.getTime();
                var diffDays = Math.ceil(timeDiff / (1000 * 3600 * 24));

                if (typeof max == "undefined" || typeof alter == "undefined") {
                    elm.text(diffDays);
                } else {
                    diffDays = max - diffDays + 1;
                    if (diffDays < 0) elm.html(alter);
                    else elm.text(diffDays);
                }
            }
        };
    }]);
    angular.module('cockpit.directives').directive("enterPrice", ['$timeout', '$http', function($timeout, $http) {
        return {
            restrict: 'A',

            link: function(scope, elm, attrs) {
                var entry = angular.copy(scope.entry);

                elm.on("click", function() {
                    var data = angular.copy(scope.entry);
                    var dlg = $("#change-price-dlg");
                    var modal = UIkit.modal(dlg);

                    var entry_index = attrs.entryindex;

                    dlg.find("#ticket").text(data.ticket);
                    dlg.find("#summ").val(data.summ);
                    dlg.find("#calc-btn").attr("href", "/calc?room="+data.room);
                    dlg.find("#calc-data textarea").val(data.data);

                    dlg.find("form").on("submit", function() {
                        var $this = $(this);

                        data.summ = $this.find("#summ").val();
                        data.data = $this.find("#calc-data textarea").val();

                        $http.post(App.route("/api/collections/saveentry"), {
                            "collection": angular.copy(scope.$parent.collection),
                            "entry": data,
                            "createversion": true
                        }, {responseType:"json"}).success(function(entry){

                            $timeout(function(){
                                scope.$parent.entries[entry_index] = entry;
                                scope.$apply();
                                scope.update();
                                App.notify(App.i18n.get("Стоимость брони изменена"), "success");
                                dlg.find("form").off();
                                modal.hide();
                            }, 0);
                        }).error(App.module.callbacks.error.http);
                        return false;
                    });
                    modal.show();
                });
            }
        };
    }]);
    angular.module('cockpit.directives').directive("removeBooking", ['$timeout', '$http', function($timeout, $http) {
        return {
            restrict: 'A',

            link: function(scope, elm, attrs) {


                elm.on("click", function() {
                    var index = attrs.index;
                    App.Ui.confirm(App.i18n.get("Are you sure?"), function(){
                        var entry = angular.copy(scope.entry);
                        entry.removed = true;
                        $http.post(App.route("/api/collections/saveentry"), {
                            "collection": angular.copy(scope.$parent.collection),
                            "entry": entry,
                            "createversion": true
                        }, {responseType:"json"}).success(function(data){
                            $timeout(function(){
                                scope.$parent.entries.splice(index, 1);
                                scope.$parent.collection.count -= 1;

                                App.notify(App.i18n.get("Entry removed"), "success");
                            }, 0);
                        }).error(App.module.callbacks.error.http);
                    });
                });
            }
        };
    }]);
    angular.module('cockpit.directives').directive("enterCabinet", ['$timeout', '$http', function($timeout, $http) {
        return {
            restrict: 'A',

            link: function(scope, elm, attrs) {

                elm.on("click", function() {
                    var result = false;
                    $.ajax({
                        url: "/cabinet/login",
                        method: "POST",
                        dataType: "json",
                        async: false,
                        data: {
                            "hash": scope.entry.client
                        },
                        success: function(data) {
                            if (data.result == "success") {
                                result = true;
                            } else {
                                App.notify("Доступ запрещен", "danger");
                            }
                        },
                        error: function() {
                            App.module.callbacks.error.http();
                        }
                    });
                    return result;
                });
            }
        };
    }]);
    $(document).ready(function() {
        if (!UIkit.datepicker) {
            App.assets.require(['assets/vendor/uikit/js/components/datepicker.min.js'], function() {
            });
        }
    });
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
                <li><a href="/booking" target="_blank" class="uk-button uk-button-primary" style="margin-left: 15px; line-height: 30px; height: 30px;">Бронирование</a></li>
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
                        <th width="10">&nbsp;</th>
                        <th width="7%">Ticket</th>
                        <th>Клиент</th>
                        <!-- <th width="7%">Кол-во чел.</th> -->
                        <th>Номер</th>
                        <th width="7%">Дата брони</th>
                        <th width="7%">Дата одобрения</th>
                        <th width="7%">Дата заезда</th>
                        <th width="7%">Дата выезда</th>
                        <th width="5%">Ночей</th>
                        <th width="5%">Сумма</th>
                        <th width="5%">Срок оплаты</th>
                        <th width="5%">Статус</th>
                        <th width="10">&nbsp;</th>
                    </tr>
                    </thead>
                    <tbody {{ $collection['sortfield'] == 'custom-order' ? 'data-uk-sortable="{animation:false}"':'' }}>
                    <tr data-ng-repeat="entry in entries track by entry._id" ng-class="{'marked': entry.marker, 'new': entry.status=='Предварительная'}">
                        <td><input type="checkbox" marker></td>
                        <td>@@ entry.ticket @@</td>
                        <td><div client-info data-id="@@ entry.client @@"></div></td>
                        <!-- <td class="font11"><span ng-if="entry.raviosa">@@ entry.person_num @@ чел.</span><span ng-if="!entry.raviosa">@@ entry.person_num @@ взр.<span ng-if="entry.children_num > 0"><br> @@ entry.children_num @@ дет.</span></span></td> -->
                        <td><span room-name data-id="@@ entry.room @@"></span> <span ng-if="entry.booking_type.length > 0">(@@ entry.booking_type @@)</span><br><span class="comment" ng-if="entry.comment.length > 0"><img src="/admin/assets/images/comment.svg" title="Комментарий клиента: @@ entry.comment @@" data-uk-tooltip="{pos:'right'}"></span><span class="comment" ng-if="entry.manager_comment.length > 0"><img src="/admin/assets/images/comment.svg" title="Комментарий менеджера: @@ entry.manager_comment @@" data-uk-tooltip="{pos:'right'}"></span></td>
                        <td class="font11">@@ entry.date | date:'dd.MM.yyyy' @@</td>
                        <td class="font11"><span ng-if="entry.status=='Одобренная к оплате'">@@ entry.date_approve | date:'dd.MM.yyyy' @@</span></td>
                        <td class="font11">@@ entry.date_start | date:'dd.MM.yyyy' @@</td>
                        <td class="font11">@@ entry.date_end | date:'dd.MM.yyyy' @@</td>
                        <td><span data-start="@@ entry.date_start @@" data-end="@@ entry.date_end @@" day-diff></span></td>
                        <td><span ng-if="entry.raviosa">@@ entry.summ @@</span><span ng-if="!entry.raviosa"><a data-entryIndex="@@ $index @@" enter-price><span ng-if="entry.summ == ''">Ввести</span><span ng-if="entry.summ != ''">@@ entry.summ @@</span></a></span></td>
                        <td><span ng-if="entry.status=='Оплаченная'">@@ entry.paid @@</span><span ng-if="entry.status=='Одобренная к оплате'" data-start="@@ entry.date_approve @@" data-end="now" data-max="<?php echo $days_to_pay; ?>" data-alter="~" day-diff></span></td>
                        <td><div class="status-selector" title="@@ entry.status @@" status-selector data-uk-tooltip></div></td>
                        <!-- <td><div class="status-selector" data-id="@@ entry.status @@" data-entryIndex="@@ $index @@" title="@@ entry.status @@" status-selector data-uk-tooltip></div></td> -->
                        <td>
                            <div data-uk-dropdown>
                                <i class="uk-icon-bars"></i>
                                <div class="uk-dropdown uk-dropdown-flip uk-text-left">
                                    <ul class="uk-nav uk-nav-dropdown uk-nav-parent-icon">
                                        @hasaccess?("Collections", 'manage.collections')
                                            <li>
                                                <a href="@route('/collections/entry/'.$collection["_id"])/@@ entry._id @@">
                                                    <i class="uk-icon-pencil"></i> Изменить
                                                </a>
                                            </li>
                                        @end
                                        <li>
                                            <a href="#" book-edit>
                                                <i class="uk-icon-pencil"></i>
                                                 Калькулятор
                                            </a>
                                        </li>
<!--                                        denis-->
                                        <li>
                                            <a href="#" book-pay-traffic>
                                                <i class="uk-icon-money"></i> Движение средств
                                            </a>
                                        </li>
<!--                                        eof denis-->
                                        <li>
                                            <a href="#" book-options>
                                                <i class="uk-icon-book"></i> Доп. информация
                                            </a>
                                        </li>
                                        <li>
                                            <a enter-cabinet href="/cabinet" target="_blank">
                                                <i class="uk-icon-book"></i> Войти в кабинет
                                            </a>
                                        </li>
                                        <li>
                                            <a href="/cabinet/receipt/@@ entry.ticket @@" target="_blank">
                                                <i class="uk-icon-file-o"></i> Печать квитанции
                                            </a>
                                        </li>
                                        <li>
                                            <a href="/cabinet/receipt/@@ entry.ticket @@?format=doc" target="_blank">
                                                <i class="uk-icon-floppy-o"></i> Сохр. квитанцию
                                            </a>
                                        </li>
                                        <li><a href="/cabinet/vaucher/@@ entry.ticket @@" target="_blank"><i class="uk-icon-file-o"></i> Печать ваучера</a></li>
                                        <li><a href="/cabinet/vaucher/@@ entry.ticket @@?format=doc" target="_blank"><i class="uk-icon-floppy-o"></i> Сохр. ваучер</a></li>
                                        <li><a href="/cabinet/request/@@ entry.ticket @@" target="_blank"><i class="uk-icon-paper-plane-o"></i> Заявка в объект</a></li>
                                        <li><a href="#" data-index="@@ $index @@" remove-booking><i class="uk-icon-trash-o"></i> @lang('Delete entry')</a></li>
                                    </ul>
                                </div>
                            </div>
                        </td>

                    </tr>
                    </tbody>
                </table>

                <div class="uk-margin-top">
                    @if($collection['sortfield'] != 'custom-order')
                    <button class="uk-button uk-button-primary" data-ng-click="loadmore()" data-ng-show="entries && !nomore">@lang('Load more...')</button>
                    @endif
                </div>

            </div>
        </div>
    </div>
    <div id="change-status-dlg" class="uk-modal">
        <div class="uk-modal-dialog">
            <a class="uk-modal-close uk-close"></a>
            <form class="uk-form" entry-form>
                <div class="uk-form-row">
                    <label class="uk-form-label">Статус брони #@@ entry.ticket @@<span id="ticket"></span>:</label>
                    <div class="uk-form-controls">
                        <select class="uk-width-1-1" ng-model="entry.status" name="status">
                            <option value="Аннулированная">Аннулированная бронь</option>
                            <option value="Предварительная">Предварительная бронь</option>
                            <option value="На рассмотрении">На рассмотрении</option>
                            <option value="Одобренная к оплате">Одобренная к оплате</option>
                            <option value="Оплаченная">Оплаченная бронь</option>
                        </select>
                    </div>
                </div>
<!--                    denis-->
<!--                <div class="uk-form-row">-->
<!--                    <label class="uk-form-label">Оплачено:</label>-->
<!--                    <div class="uk-form-controls">-->
<!--                        <input type="hidden" status-date-field current-date ticket="entry.ticket" ng-model="entry.date_approve" name="date_approve">-->
<!--                        <input class="uk-width-1-1" status-pay-field ng-model="entry.paid" name="paid">-->
<!--                    </div>-->
<!--                </div>-->
                <div class="uk-form-row">
                    <label class="uk-form-label">
                        <input type="checkbox" ng-model="sendNotice"> Отправить уведомление
                    </label>
                </div>
                <div class="uk-form-row">
                    <button type="submit" class="uk-button uk-button-primary uk-float-left">Изменить</button>
                    <button type="button" class="uk-button uk-float-right uk-modal-close">Отмена</button>
                </div>
            </form>
        </div>
    </div>
    <div id="change-price-dlg" class="uk-modal">
        <div class="uk-modal-dialog">
            <a class="uk-modal-close uk-close"></a>
            <form class="uk-form">
                <div class="uk-form-row">
                    <label class="uk-form-label" for="status">Изменить сумму брони #<span ng-bind="entry.ticket"></span>:
                        <div class="uk-form-controls">
                            <input id="summ" class="uk-width-1-1" value="">
                        </div>
                    </label>
                </div>
                <div class="uk-form-row">
                    <a id="calc-btn" href="/calc" class="uk-button" target="_blank">Калькулятор</a>
                    <a class="uk-button" data-uk-toggle="{target:'#calc-data'}">Данные</a>
                </div>
                <div class="uk-form-row">
                    <div id="calc-data" class="uk-hidden">
                        <textarea rows="5" class="uk-width-1-1"></textarea>
                    </div>
                </div>
                <div class="uk-form-row">
                    <button type="submit" class="uk-button uk-button-primary uk-float-left">Изменить</button>
                    <button type="button" class="uk-button uk-float-right uk-modal-close">Отмена</button>
                </div>
            </form>
        </div>
    </div>
    <div id="book-options-dlg" class="uk-modal">
        <div class="uk-modal-dialog">
            <a class="uk-modal-close uk-close"></a>
            <form class="uk-form" entry-form>
                <div class="uk-form-row">
                    <h2>Дополнительная информация (бронь #<span ng-bind="entry.ticket"></span>)</h2>
                </div>
                <ul class="uk-tab" data-uk-tab="{connect:'#book-options-tabs'}">
                    <li><a href="">Основное</a></li>
                    <li><a href="">Файлы менеджера</a></li>
                    <li><a href="">Файлы клиента</a></li>
                </ul>
                <ul id="book-options-tabs" class="uk-switcher uk-margin">
                    <li>
                        <div class="uk-form-row">
                            <ul class="uk-grid uk-grid-width-1-3">
                                <li>
                                    <label>
                                        <input type="checkbox" name="receipt_sent" ng-model="entry.receipt_sent">
                                         Квитанция клиенту
                                    </label>
                                </li>
                                <li>
                                    <label>
                                        <input type="checkbox" name="vaucher_sent" ng-model="entry.vaucher_sent">
                                         Ваучер клиенту
                                    </label>
                                </li>
                                <li>
                                    <label><input type="checkbox" name="payment_sent" ng-model="entry.payment_sent"> Оплата в объект</label>
                                </li>
                            </ul>
                        </div>
                        <div class="uk-form-row">
                            <label class="uk-form-label" for="manager_comment">Комментарий менеджера</label>
                            <div class="uk-form-controls">
                                <textarea id="manager_comment" name="manager_comment" rows="5" class="uk-width-1-1" ng-model="entry.manager_comment"></textarea>
                            </div>
                        </div>
                    </li>
                    <li>
                        <div class="uk-form-row">
                            <contentfield class="uk-width-1-1" options='{"type":"photopicker", "img_count":3, "showtitle": true}' type="photopicker" name="manager_files" ng-model="entry.manager_files"></contentfield>
                        </div>
                    </li>
                </ul>
                <div class="uk-form-row">
                    <button type="submit" class="uk-button uk-button-primary uk-float-left">Сохранить <i class="uk-icon-spinner uk-icon-spin uk-hidden"></i></button>
                    <button type="button" class="uk-button uk-float-right uk-modal-close">Отмена</button>
                </div>
            </form>
        </div>
    </div>

<!--    DENIS      -->
    <div id="book-pay-traffic-dlg" class="uk-modal">
        <div class="uk-modal-dialog uk-modal-dialog-large">
            <a class="uk-modal-close uk-close"></a>
            <form class="uk-form" entry-form>
                <div class="uk-form-row">
                    <h2>
                        Финансовый учет (бронь #<span ng-bind="entry.ticket"></span>)
                    </h2>
                </div>
                <div class="uk-form-row">
                    <div pay-widget name="pay_traffic" ng-model="entry.pay_traffic">
                        <div class="data-uk-margin"
                             ng-repeat="pay in entry.pay_traffic"
                        >

                            <div class="uk-margin-large-bottom"
                                 ng-if="$index < current_entry.pay_traffic.length"
                                >
                                <div class="save uk-grid"
                                     id="pay-@@ $index @@"
                                >
                                    <div class="uk-width-1-10">
                                        <span>@@ $index + 1 @@</span>
                                    </div>
                                    <div class="uk-width-1-10">
                                        <i ng-if="pay.type == 'Оплата наличкой в кассу объекта' ||
                                                  pay.type == 'Возврат заказчикам со счёта объекта'"
                                           class="uk-icon-arrows-h uk-icon-medium">
                                        </i>
                                        <i ng-if="pay.type == 'Предоплата от клиента'"
                                           class="uk-icon-long-arrow-left uk-icon-medium green">
                                        </i>
                                        <i ng-if="pay.type == 'Перечислили объекту' ||
                                                  pay.type == 'Возврат заказчикам со счёта ООО ПП'"
                                           class="uk-icon-long-arrow-right uk-icon-medium red">
                                        </i>
                                    </div>
                                    <div class="uk-witdh-2-10">
                                        <p>@@ pay.date @@</p>
                                    </div>
                                    <div class="uk-width-3-10">
                                        <p>@@ pay.type @@</p>
                                    </div>
                                    <div class="uk-width-2-10">
                                        <p>@@ pay.summ @@</p>
                                    </div>
                                    <div class="uk-width-1-10">
                                        <i class="uk-icon-info-circle uk-margin-right info"
                                           title="@@ pay.comment @@">
                                        </i>
                                        <i ng-click="edit_payment($index)"
                                           ng-if="canRemovePayments"
                                           class="uk-icon-pencil pencil">
                                        </i>
                                    </div>
                                </div>

<!--                                ############################################      -->
                                <div class="edit uk-grid uk-margin-large-bottom"
                                     id="edit-pay-@@ $index @@"
                                >
                                    <div class="uk-width-1-10">
                                        <span>@@ $index + 1 @@</span>
                                    </div>
                                    <div class="uk-width-3-10">
                                        <label>
                                            <select ng-model="pay.type">
                                                <option value="Предоплата от клиента">
                                                    Оплата клиентом
                                                </option>
                                                <option value="Перечислили объекту">
                                                    Перечислили объекту
                                                </option>
                                                <option value="Оплата наличкой в кассу объекта">
                                                    Оплата наличкой в кассу объекта
                                                </option>
                                                <option value="Возврат заказчикам со счёта ООО ПП">
                                                    Возврат заказчикам со счёта ООО "ПП"
                                                </option>
                                                <option value="Возврат заказчикам со счёта объекта">
                                                    Возврат заказчикам со счёта объекта
                                                </option>
                                            </select>
                                        </label>
                                    </div>
                                    <div class="uk-width-3-10">
                                        <label>
                                            <input type="text" ng-model="pay.summ">
                                        </label>
                                    </div>
                                    <div class="uk-width-2-10">
                                        <label>
                                            <input class="ng-pristine ng-valid ng-touched"
                                                   type="text"
                                                   data-uk-datepicker="{format:'YYYY-MM-DD'}"
                                                   ng-model="pay.date">
                                        </label>
                                    </div>
                                    <div class="uk-width-1-10 ">
                                        <i ng-click="remove_payment($index)"
                                           ng-if="canRemovePayments || $index>current_entry.pay_traffic.length-1"
                                           class="uk-icon-trash-o trash">
                                        </i>
                                    </div>
                                    <div class="uk-width-1-1 uk-margin-top">
                                        <label>
                                        <textarea cols="160"
                                                  rows="5"
                                                  ng-model="pay.comment"
                                                  placeholder="Здесь можно оставить комментарий"
                                        ></textarea>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="new uk-grid uk-margin-large-bottom"
                                 ng-if="!current_entry.pay_traffic || ($index >= current_entry.pay_traffic.length)"
                            >
                                <div class="uk-width-1-10">
                                    <span>@@ $index + 1 @@</span>
                                </div>
                                <div class="uk-width-3-10">
                                    <label>
                                        <select ng-model="pay.type">
                                            <option value="Предоплата от клиента">
                                                Предоплата от клиента
                                            </option>
                                            <option value="Перечислили объекту">
                                                Перечислили объекту
                                            </option>
                                            <option value="Оплата наличкой в кассу объекта">
                                                Оплата наличкой в кассу объекта
                                            </option>
                                            <option value="Возврат заказчикам со счёта ООО ПП">
                                                Возврат заказчикам со счёта ООО "ПП"
                                            </option>
                                            <option value="Возврат заказчикам со счёта объекта">
                                                Возврат заказчикам со счёта объекта
                                            </option>
                                        </select>
                                    </label>
                                </div>
                                <div class="uk-width-3-10">
                                    <label>
                                        <input type="text" ng-model="pay.summ">
                                    </label>
                                </div>
                                <div class="uk-width-2-10">
                                    <label>
                                        <input class="ng-pristine ng-valid ng-touched"
                                               type="text"
                                               data-uk-datepicker="{format:'YYYY-MM-DD'}"
                                               ng-model="pay.date">
                                    </label>
                                </div>
                                <div class="uk-width-1-10 ">
                                    <i ng-click="remove_payment($index)"
                                       ng-if="canRemovePayments || $index>current_entry.pay_traffic.length-1"
                                       class="uk-icon-trash-o trash">
                                    </i>
                                </div>
                                <div class="uk-width-1-1 uk-margin-top">
                                    <label>
                                        <textarea cols="160"
                                                  rows="5"
                                                  ng-model="pay.comment"
                                                  placeholder="Здесь можно оставить комментарий"
                                        ></textarea>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <hr>
                    </div>
                </div>
                <div class="uk-form-row">
                    <button type="submit" class="uk-button uk-button-primary uk-float-left uk-margin-large-right">
                        Сохранить <i class="uk-icon-spinner uk-icon-spin uk-hidden"></i>
                    </button>
                    <i class="uk-icon-plus-circle uk-icon-medium info"
                       ng-click="add_payment()">
                    </i>
                    <button type="button" class="uk-button uk-float-right uk-modal-close">Отмена</button>
                </div>
            </form>
        </div>
    </div>
<!--    EOF DENIS -->

    <div id="book-editing-dlg" class="uk-modal">
        <div class="uk-modal-dialog uk-modal-dialog-large">
            <a class="uk-modal-close uk-close"></a>
            <form class="uk-form" entry-form>
                <div class="uk-form-row"><h2>Калькулятор (бронь #<span ng-bind="entry.ticket"></span>)</h2></div>
                <input type="hidden" ng-model="entry.summ" name="summ">
                <div class="uk-position-relative">
                    <div class="disable-form" ng-if="entry.status=='Оплаченная'"></div>
                    <div class="uk-grid">
                        <div class="uk-width-7-10">
                            <div class="uk-form-row">
                                <div class="uk-form-icon">
                                    <i class="uk-icon-calendar"></i>
                                    <input class="uk-form-large" type="text" data-uk-datepicker="{format:'YYYY-MM-DD'}" ng-model="entry.date_start" readonly='true' name="date_start">
                                </div>
                                <div class="uk-form-icon">
                                    <i class="uk-icon-calendar"></i>
                                    <input class="uk-form-large" type="text" data-uk-datepicker="{format:'YYYY-MM-DD'}" ng-model="entry.date_end" readonly='true' name="date_end">
                                </div>
                            </div>
                            <div class="uk-form-row">
                                <contentfield class="uk-width-1-1" options='{"type":"raviosa-calc","summField":"entry.summ","startField":"entry.date_start","endField":"entry.date_end","raviosaField":"entry.raviosa", "ticketField":"entry.ticket"}' ng-model="entry.data" name="data"></contentfield>
                            </div>
                        </div>
                        <div class="uk-width-3-10">
                            <div class="uk-form-row">
                                <label class="uk-form-label">Способ оплаты:</label>
                                <div class="uk-form-controls">
                                    <select ng-model="entry.payment_method" name="payment_method">
                                        <option value="Карта">Банковская карта</option>
                                        <option value="Квитанция">Через квитанцию</option>
                                    </select>
                                </div>
                            </div>
                            <div class="uk-form-row">
                                <label class="uk-form-label">Кол-во взрослых:</label>
                                <div class="uk-form-controls">
                                    <input ng-model="entry.person_num" name="person_num">
                                </div>
                            </div>
                            <div class="uk-form-row">
                                <label class="uk-form-label">Кол-во детей:</label>
                                <div class="uk-form-controls">
                                    <input ng-model="entry.children_num" name="children_num">
                                </div>
                            </div>
                            <div class="uk-form-row">
                                <label class="uk-form-label">Размер предоплаты (<span prepay-size-field></span>):</label>
                                <div class="uk-form-controls">
                                    <input prepay-field disabled>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="uk-form-row">
                    <button type="submit" class="uk-button uk-button-primary uk-float-left" ng-if="entry.status!='Оплаченная'">Сохранить <i class="uk-icon-spinner uk-icon-spin uk-hidden"></i></button>
                    <button type="button" class="uk-button uk-float-right uk-modal-close">Отмена</button>
                </div>
            </form>
        </div>
    </div>
