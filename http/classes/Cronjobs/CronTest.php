<?php

namespace Cronjobs;

class CronTest extends \Core\Cronjob {

    public function __construct() {
        parent::__construct(\Core\Cronjob::IntervalLap);
    }

    protected function process() {
    }
}
