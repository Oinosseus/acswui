<?php

class CronDbCleanup extends Cronjob {


    public function __construct() {
        parent::__construct(new DateInterval("PT1H"));
    }


    /**
     * For certain reason the table RacePollCarClasses contains row with User=0
     * Actually User=0 is root, which should not vote at racepoll
     */
    private function cleanClearRootFromRacePoll() {
        global $acswuiDatabase;

        $res = $acswuiDatabase->fetch_2d_array("RacePollCarClasses", ['Id'], ['User'=>0]);
        foreach ($res as $row) {
            $this->log("delete RacePollCarClasses.Id=" . $row['Id']);
            $acswuiDatabase->delete_row("RacePollCarClasses", $row['Id']);
        }
    }


    public function execute() {
        $this->cleanClearRootFromRacePoll();
    }


}

?>
