<?php

class CronDriverRankingLatest extends Cronjob {


    public function __construct() {
        parent::__construct(new DateInterval("PT5M"));
    }


    public function execute() {
        DriverRanking::calculateRanks();
    }
}

?>
