<?php

class CronStatsTrackPopularity extends Cronjob {

    //! limits the amount of scanning by time in [s]
    private $MaxScanDuration = 100.0;


    public function __construct() {
        parent::__construct(new DateInterval("P1D"));
    }


    public function execute() {
        $stps = StatsTrackPopularity::calculatePopularities();

        foreach ($stps as $stp) {
            $stp->save();
        }

        $this->log("Saved " . count($stps) . " popularities");
    }
}

?>
