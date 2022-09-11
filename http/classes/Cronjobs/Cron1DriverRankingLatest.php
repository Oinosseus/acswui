<?php

namespace Cronjobs;

class Cron1DriverRankingLatest extends \Core\Cronjob {

    public function __construct() {
        parent::__construct(\Core\Cronjob::IntervalSession);
    }

    protected function process() {
        \DbEntry\DriverRanking::calculateLatest();
    }
}
