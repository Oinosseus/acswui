<?php

namespace Cronjobs;

class CronStorePublicIP extends \Core\Cronjob {

    public function __construct() {
        parent::__construct(\Core\Cronjob::IntervalSession);
    }

    private function getIp() {
        // solution from here: https://stackoverflow.com/questions/1814611/how-do-i-find-my-servers-ip-address-in-phpcli
        $ip = file_get_contents('http://icanhazip.com/');
        $ip = trim($ip);
        return $ip;
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
