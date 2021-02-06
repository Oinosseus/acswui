<?php

class CronStatsGeneral extends Cronjob {


    public function __construct() {
        parent::__construct(new DateInterval("P1D"));
    }


    public function execute() {
        $stats = StatsGeneral::calculateStats();
        $stats->save();
    }
}

?>
