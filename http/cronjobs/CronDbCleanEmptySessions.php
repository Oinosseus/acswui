<?php

class CronDbCleanEmptySessions extends Cronjob {


    public function __construct() {
        parent::__construct(new DateInterval("PT15M"));
    }


    public function execute() {
        global $acswuiDatabase;

        // query reverse over session types
        // first clean races, then qualifyings, then practices
        $query_sessions = "SELECT Id FROM Sessions ORDER BY Type DESC;";
        $res_sessions = $acswuiDatabase->fetch_raw_select($query_sessions);
        foreach ($res_sessions as $row) {
            $session_id = $row['Id'];

            if ($this->sessionIsActive($session_id)) continue;
            if ($this->sessionIsMiddle($session_id)) continue;

            if ($this->sessionIsEmpty($session_id))
                $this->sessionDelete($session_id);
        }
    }


    private function sessionDelete(int $session_id) {
        global $acswuiDatabase;

        $this->log("Delete Sessions.Id = $session_id");

        // delete session
        $acswuiDatabase->delete_row("Sessions", $session_id);

        // delete session results
        $res = $acswuiDatabase->fetch_2d_array("SessionResults", ["Id"], ["Session"=>$session_id]);
        foreach ($res as $row) {
            $acswuiDatabase->delete_row("SessionResults", $row['Id']);
            $this->log("Delete empty session  ID $session_id");
        }
    }


    private function sessionIsActive(int $session_id) {

        // check if any slot has requested session as current session
        foreach (ServerSlot::listSlots() as $slot) {
            $session = $slot->currentSession();
            if ($session !== NULL && $session->id() == $session_id) return TRUE;
        }

        return FALSE;
    }


    //! @return TRUE if session is empty, else FALSE
    private function sessionIsEmpty(int $session_id) {
        global $acswuiDatabase;

        // check if laps or collisions exist
        foreach (array("Laps", "CollisionEnv", "CollisionCar") as $table) {
            $query_laps = "SELECT Id FROM $table WHERE Session = $session_id;";
            $res_laps = $acswuiDatabase->fetch_raw_select($query_laps);
            if (count($res_laps) > 0) return FALSE;
        }

        return TRUE;
    }


    //! @return TRUE if the session is a predecessor and has a predecessor
    private function sessionIsMiddle(int $session_id) {
        global $acswuiDatabase;

        // check if session is a predecessor
        $res = $acswuiDatabase->fetch_2d_array("Sessions", ["Id"], ["Predecessor"=>$session_id]);
        $is_predecessor = (count($res) > 0) ? TRUE : FALSE;

        // check if session has a predecessor
        $res = $acswuiDatabase->fetch_2d_array("Sessions", ["Predecessor"], ["Id"=>$session_id]);
        $has_predecessor = ($res[0]['Predecessor'] == 0) ? FALSE : TRUE;

        return $is_predecessor && $has_predecessor;
    }


}

?>
