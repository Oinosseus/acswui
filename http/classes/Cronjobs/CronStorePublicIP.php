<?php

namespace Cronjobs;

class CronStorePublicIP extends \Core\Cronjob {

    public function __construct() {
        parent::__construct(\Core\Cronjob::IntervalSession);
    }

    private function getIp() {
        $ip = exec('curl -4 http://icanhazip.com/');  // AC needs IPv4
        if ($ip === False) return NULL;
        else return trim($ip);
    }

    protected function process() {

        // get IP
        $ip = $this->getIp();
        $this->verboseOutput("Current server IP: '{$ip}'<br>");

        // store to cache file
        $ip_cache_file = \Core\Config::AbsPathData . "/acswui_config/server_ip.txt";
        file_put_contents($ip_cache_file, $ip, LOCK_EX);
    }
}
