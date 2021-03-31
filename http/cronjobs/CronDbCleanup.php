<?php

class CronDbCleanup extends Cronjob {


    public function __construct() {
        parent::__construct(new DateInterval("P1D"));
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


    /**
     * Due to the server plugin implementation it happens that
     * Sessions without any activity are in the database.
     */
    private function cleanEmptySessions() {
        global $acswuiDatabase;

        // --------------------------------------------------------------------
        //                  Clean Empty Race Sessions
        // --------------------------------------------------------------------

        // find all race sessions
        $query_sessions = "SELECT Id FROM Sessions WHERE Type = 3";
        $res_sessions = $acswuiDatabase->fetch_raw_select($query_sessions);
        foreach ($res_sessions as $row) {
            $session_id = $row['Id'];

            // check if laps or collisions exist
            $session_is_driven = FALSE;
            foreach (array("Laps", "CollisionEnv", "CollisionCar") as $table) {
                $query_laps = "SELECT Id FROM $table WHERE Session = $session_id;";
                $res_laps = $acswuiDatabase->fetch_raw_select($query_laps);
                if (count($res_laps) > 0) {
                    $session_is_driven = TRUE;
                    break;
                }
            }
            if ($session_is_driven === TRUE) continue;

            // delete session
            $this->log("Delete Sessions.Id = $session_id");
            $acswuiDatabase->delete_row("Sessions", $session_id);
        }
    }


    public function execute() {
        $this->cleanClearRootFromRacePoll();
        $this->cleanEmptySessions();
    }


}

?>
