<?php

// ACL
$app("acl")->addResource("Mediamanager", ['manage']);


$app->on("app.layout.header", function() {

    $mediapath = trim($this->module("auth")->getGroupSetting("media.path", '/'), '/');
    
    $current_user = $this->module("auth")->getUser();
    $mediapath = preg_replace('/{user_id}/', $current_user["_id"], $mediapath);

    ?>
        <script>
            window.COCKPIT_SITE_BASE_URL  = '<?=$this->pathToUrl("site:");?>';
            window.COCKPIT_MEDIA_BASE_URL = '<?=rtrim($this->pathToUrl("site:{$mediapath}"), '/');?>';
        </script>
    <?php
});


$app->on("admin.init", function() {

    // bind controller
    $this->bindClass("Mediamanager\\Controller\\Mediamanager", "mediamanager");

    // thumbnail api
    $this->bind("/media/thumbnail/*", function($params) {
        $options = $this->params("options", []);
        return $this->module("mediamanager")->thumbnail($params[":splat"], $options);
    });

    if (!$this->module("auth")->hasaccess("Mediamanager","manage")) return;

    $this("admin")->menu("top", [
        "url"    => $this->routeUrl("/mediamanager"),
        "label"  => '<i class="uk-icon-cloud"></i>',
        "title"  => $this("i18n")->get("Mediamanager"),
        "active" => (strpos($this["route"], '/mediamanager') === 0)
    ], 0);


    // handle global search request
    $this->on("cockpit.globalsearch", function($search, $list) {

        $user = $this->module("auth")->getUser();

        $bookmarks = $this->memory->get("mediamanager.bookmarks.".$user["_id"], ["folders"=>[], "files"=>[]]);

        foreach ($bookmarks["folders"] as $f) {
            if (stripos($f["name"], $search)!==false){
                $list[] = [
                    "title" => '<i class="uk-icon-folder-o"></i> '.$f["name"],
                    "url"   => $this->routeUrl('/mediamanager#'.$f["path"])
                ];
            }
        }

        foreach ($bookmarks["files"] as $f) {
            if (stripos($f["name"], $search)!==false){
                $list[] = [
                    "title" => '<i class="uk-icon-file-o"></i> '.$f["name"],
                    "url"   => $this->routeUrl('/mediamanager#'.dirname($f["path"]))
                ];
            }
        }
    });

});

$app->on("cockpit.content.fields.settings", function() {
  $app = cockpit();
  
  // Max width
  $label = $app("i18n")->get("Max width");
echo <<<EOD
<div class="uk-form-row" data-ng-if="field.type=='photopicker'">
	<label class="uk-form-label">{$label}</label>
    <div class="uk-form-controls">
    	<input class="uk-form-blank uk-width-1-1" type="text" data-ng-model="field['maxWidth']" placeholder="{$label}...">
    </div>
</div>
EOD;
  
  // Max height
  $label = $app("i18n")->get("Max height");
echo <<<EOD
<div class="uk-form-row" data-ng-if="field.type=='photopicker'">
	<label class="uk-form-label">{$label}</label>
    <div class="uk-form-controls">
    	<input class="uk-form-blank uk-width-1-1" type="text" data-ng-model="field['maxHeight']" placeholder="{$label}...">
    </div>
</div>
EOD;
  
  // Convert to jpg
  $label = $app("i18n")->get("Convert to jpg");
echo <<<EOD
<div class="uk-form-row" data-ng-if="field.type=='photopicker'">
	<label class="uk-form-label">{$label}</label>
    <div class="uk-form-controls">
    	<input type="checkbox" data-ng-model="field['convert2jpg']">
    </div>
</div>
EOD;
  
  // Show title
  $label = $app("i18n")->get("Show title");
echo <<<EOD
<div class="uk-form-row" data-ng-if="field.type=='photopicker'">
	<label class="uk-form-label">{$label}</label>
    <div class="uk-form-controls">
    	<input type="checkbox" data-ng-model="field['showtitle']">
    </div>
</div>
EOD;
});
