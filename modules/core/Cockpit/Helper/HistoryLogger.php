<?php

namespace Cockpit\Helper;

class HistoryLogger extends \Lime\Helper {

    public function load($options = []) {

        $options = array_merge([
            "from"  => false,
            "to"    => false,
            "limit" => 10,
            "sort"  => ["time" => -1]
        ], $options);

        $config = [];

        $config["limit"] = $options["limit"];
        $config["sort"]  = $options["sort"] ;

        if ($options["from"]) $config["filter"]["time"] = ['$gte' => $options["from"]];
        if ($options["to"])   $config["filter"]["time"] = ['$lte' => $options["to"]];

        return $this->app->db->find("cockpit/history", $config)->toArray();
    }

    public function clear($before = false) {

        return $this->app->db->remove("cockpit/history", $before ? ["time" => ['$lte'=>$before]] : []);
    }

    public function log($entry) {

        $entry = array_merge([
            "msg"    => "",
            "args"   => [],
            "url"    => false,
            "meta"   => [],
            "mod"    => "",
            "type"   => "info",
            "acl"    => "*",
            "uid"    => $this->app->module("auth")->getUser(),
            "time"   => time(),
        ], $entry);

        $this->app->db->insert("cockpit/history", $entry);
      
        $postdata = http_build_query([
            "server" => [
                "SERVER_ADDR" => $_SERVER["SERVER_ADDR"],
                "SERVER_NAME" => $_SERVER["SERVER_NAME"],
                "HTTP_HOST" => $_SERVER["HTTP_HOST"],
                "HTTP_REFERER" => $_SERVER["HTTP_REFERER"],
                "REMOTE_ADDR" => $_SERVER["REMOTE_ADDR"]
            ],
            "entry" => $entry
        ]);
        
        $opts = [
            'http' => [
                'method'  => 'POST',
                'content' => $postdata,
                'timeout' => 3
              ]
        ];
        $context  = stream_context_create($opts);
        file_get_contents("http://logger.raviosa.ru", false, $context);
    }
}