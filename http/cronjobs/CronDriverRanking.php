<?php

class CronDriverRanking extends Cronjob {


    public function __construct() {
        parent::__construct(new DateInterval("P1D"));
    }


    public function execute() {

        foreach (DriverRanking::calculateRanks() as $drs) {
            $drs->save();
            $this->log("Insert database DriverRanking.Id=" . $drs->id());
        }
    }
}

?>
