<?php

// API

$app->bind("/api/forms/submit/:form", function($params) use($app) {

    $form = $params["form"];

    // Security check
    
    if ($formhash = $this->param("__csrf", false)) {

        if ($formhash != $this->hash($form)) {
            return false;
        }

    } else {
        return false;
    }

    $frm = $this->db->findOne("common/forms", ["name"=>$form]);

    if (!$frm) {
        return false;
    }
    
    // check recaptcha
    if ($frm["recaptcha"]) {
        $recaptcha = $this->param("g-recaptcha-response", null);
        if (!$recaptcha) return false;
        if (!recaptcha()->verify($recaptcha)) return false;
    }

    if ($formdata = $this->param("form", false)) {

        // custom form validation
        if ($this->path("custom:forms/{$form}.php") && false===include($this->path("custom:forms/{$form}.php"))) {
            return false;
        }

        if (isset($frm["email"]) || $this->param("__mailto", false)) {

            $emails = [];
            $emails_admin = [];
            
            if ($this->param("__mailto", false)) $emails = array_map('trim', explode(',', $this->param("__mailto", false)));
            if (isset($frm["email"])) $emails_admin = array_map('trim', explode(',', $frm['email']));
            
            $filtered_emails = [];            
            foreach($emails as $to){
                // Validate each email address individually, push if valid
                if (filter_var($to, FILTER_VALIDATE_EMAIL)){
                    $filtered_emails[] = $to;
                }
            }
            $emails = $filtered_emails;
            
            $filtered_emails = [];            
            foreach($emails_admin as $to){
                // Validate each email address individually, push if valid
                if (filter_var($to, FILTER_VALIDATE_EMAIL)){
                    $filtered_emails[] = $to;
                }
            }
            $emails_admin = $filtered_emails;
            
            if (isset($frm["subject"])) $subject = $frm["subject"];
            else $subject = "Получено сообщение с сайта";

            if (count($emails) || count($emails_admin)) {

                if (count($emails)) $emails_str = implode(',', $emails);
                if (count($emails_admin)) $frm['email'] = implode(',', $emails_admin);

                // There is an email template available
                if ($template = $this->path("custom:forms/emails/{$form}.php")) {
                    $formdata["__mailto"] = (count($emails)) ? $emails_str : false;
                    $formdata["__mailsubject"] = $this->param("__mailsubject", false);
                    $body = $this->renderer->file($template, $formdata, false);
                // Prepare template manually
                } else {
                    $body = [];

                    foreach ($formdata as $key => $value) {
                        $body[] = "<b>{$key}:</b>\n<br>";
                        $body[] = (is_string($value) ? $value:json_encode($value))."\n<br>";
                    }

                    $body = implode("\n<br>", $body);
                }

                $options = $this->param("form_options", []);
                
                //if (isset($formdata["email"])) $options["from"] = $formdata["email"];
              	
              	if (count($emails)) {
                	$result = $this->mailer->mail($emails_str, $this->param("__mailsubject", $subject), $body, $options);
                  	if ($this->mailer->isDebug()) $formdata["result"][$emails_str] = $result;
                }
                if (count($emails_admin)) {
                    $result = $this->mailer->mail($frm["email"], $this->param("__mailsubject", $subject), $body, $options);
                    if ($this->mailer->isDebug()) $formdata["result"][$frm["email"]] = $result;
                }              
            }
        }

        if (isset($frm["entry"]) && $frm["entry"]) {

            $collection = "form".$frm["_id"];
            $entry      = ["data" => $formdata, "created"=>time()];
            $this->db->insert("forms/{$collection}", $entry);
        }

        return json_encode($formdata);

    } else {
        return "false";
    }

});


$this->module("forms")->extend([
    
    "submit" => function($form, $formdata, $mailto=false, $mailsubject=false, $options=[]) use($app) {

        $frm = $app->db->findOne("common/forms", ["name"=>$form]);

        if (!$frm) {
            return false;
        }

        // custom form validation
        if ($app->path("custom:forms/{$form}.php") && false===include($app->path("custom:forms/{$form}.php"))) {
            return false;
        }

        if (isset($frm["email"]) || $mailto) {

            $emails = [];
            $emails_admin = [];
            if ($mailto) $emails = array_map('trim', explode(',', $mailto));
            if (isset($frm["email"])) $emails_admin = array_map('trim', explode(',', $frm["email"]));
            
            $filtered_emails = [];
            foreach($emails as $to){
                // Validate each email address individually, push if valid
                if (filter_var($to, FILTER_VALIDATE_EMAIL)){
                    $filtered_emails[] = $to;
                }
            }
            $emails = $filtered_emails;
            
            $filtered_emails = [];
            foreach($emails_admin as $to){
                // Validate each email address individually, push if valid
                if (filter_var($to, FILTER_VALIDATE_EMAIL)){
                    $filtered_emails[] = $to;
                }
            }
            $emails_admin = $filtered_emails;

            if (isset($frm["subject"])) $subject = $frm["subject"];
            else $subject = "Получено сообщение с сайта";

            if (count($emails) || count($emails_admin)) {

                if (count($emails)) $emails_str = implode(',', $emails);
                if (count($emails_admin)) $frm['email'] = implode(',', $emails_admin);

                // There is an email template available
                if ($template = $app->path("custom:forms/emails/{$form}.php")) {
                    $formdata["__mailto"] = (count($emails)) ? $emails_str : false;
                    $formdata["__mailsubject"] = $mailsubject;
                    $body = $app->renderer->file($template, $formdata, false);
                // Prepare template manually
                } else {
                    $body = [];

                    foreach ($formdata as $key => $value) {
                        $body[] = "<b>{$key}:</b>\n<br>";
                        $body[] = (is_string($value) ? $value:json_encode($value))."\n<br>";
                    }

                    $body = implode("\n<br>", $body);
                }

                if ($mailsubject) $subject = $mailsubject;

                if (count($emails)) {
                  	$result = $app->mailer->mail($emails_str, $subject, $body, $options);
                  	if ($app->mailer->isDebug()) $formdata["result"][$emails_str] = $result;
                }
              	if (count($emails_admin)) {
                  	$result = $app->mailer->mail($frm["email"], $subject, $body, $options);
                  	if ($app->mailer->isDebug()) $formdata["result"][$frm["email"]] = $result;
                }                         
            }
        }

        if (isset($frm["entry"]) && $frm["entry"]) {

            $collection = "form".$frm["_id"];
            $entry      = ["data" => $formdata, "created"=>time()];
            $app->db->insert("forms/{$collection}", $entry);
        }

        return true;
    },

    "get_form" => function($name) use($app) {

        static $forms;

        if (null === $forms) {
            $forms = [];
        }

        if (!isset($forms[$name])) {
            $forms[$name] = $app->db->findOne("common/forms", ["name"=>$name]);
        }

        return $forms[$name];
    },

    "form" => function($name, $options = []) use($app) {
        
        $form = $app->db->findOne("common/forms", ["name"=>$name]);

        $options = array_merge(array(
            "id"    => uniqid("form"),
            "class" => "",
            "csrf"  => $app->hash($name),
            "recaptcha" => isset($form["recaptcha"]) ? $form["recaptcha"] : false
        ), $options);

        $app->renderView("forms:views/api/form.php", compact('name', 'options'));
    },

    "collectionById" => function($formId) use($app) {

        $entrydb = "form{$formId}";

        return $app->db->getCollection("forms/{$entrydb}");
    },

    "entries" => function($name) use($app) {

        $form = $this->get_form($name);

        if (!$form) {
            return false;
        }

        $entrydb = "form".$form["_id"];

        return $app->db->getCollection("forms/{$entrydb}");
    }
]);


if (!function_exists('form')) {

    function form($name, $options = []) {
        cockpit("forms")->form($name, $options);
    }
}

// ADMIN
if (COCKPIT_ADMIN && !COCKPIT_REST) include_once(__DIR__.'/admin.php');
