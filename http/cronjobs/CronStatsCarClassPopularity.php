<?php

class CronStatsCarClassPopularity extends Cronjob {

    public function __construct() {
        parent::__construct(NULL, TRUE);
    }


    public function execute() {
        $stps = StatsCarClassPopularity::calculatePopularities();

        foreach ($stps as $stp) {
            $stp->save();
        }

        $this->log("Saved " . count($stps) . " popularities");
    }
}

?>
