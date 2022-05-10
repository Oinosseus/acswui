<?php

namespace Cronjobs;

class CleanEmptySessions extends \Core\Cronjob {

    public function __construct() {
        parent::__construct(\Core\Cronjob::IntervalSession);
    }

    protected function process() {

        // determine sessions to be checked
        $max_session_id = \DbEntry\Session::fromLastCompleted()->id();
        $last_session_id = (int) $this->loadData("LastCleanedSession", 0);
        $this->verboseOutput("Cleaning $last_session_id < Session-Id <= $max_session_id<br>");

        // find all empty sessions
        $empty_session_ids = array();
        $query = "SELECT Id FROM Sessions WHERE Id > $last_session_id AND Id <= $max_session_id";
        $res = \Core\Database::fetchRaw($query);
        foreach ($res as $row) {
            $session_id = (int) $row['Id'];

            if ($this->sessionIsEmpty($session_id)) $empty_session_ids[] = $session_id;
        }

        // delete empty sessions
        foreach ($empty_session_ids as $session_id) {
            $this->verboseOutput("Deleting Session $session_id<br>");
            $this->deleteSession($session_id);
        }

        // save last checked session
        $this->saveData("LastCleanedSession", $max_session_id);
    }


    //! @return TRUE If this session and all predecessors are empty, else FALSE
    private function sessionIsEmpty($session_id) {

        // check if predecessors are empty
        $query = "SELECT Predecessor FROM Sessions WHERE Id = $session_id";
        $res = \Core\Database::fetchRaw($query);
        if (count($res) > 0) {
            $predecessor_id = $res[0]['Predecessor'];
            if (!$this->sessionIsEmpty($predecessor_id)) return FALSE;
        }

        // check if this session is empty
        $query = "SELECT COUNT(Id) FROM `Laps` WHERE SESSION = $session_id;";
        $res = \Core\Database::fetchRaw($query);
        return ($res[0]['COUNT(Id)'] == 0) ? TRUE : FALSE;
    }


    private function deleteSession(int $session_id) {

        // delete session dependencies
        foreach (['CollisionCar', 'CollisionEnv', 'SessionResults'] as $table) {
            \Core\Database::query("DELETE FROM $table WHERE $table.Session = $session_id");
        }

        // delete session
        \Core\Database::query("DELETE FROM Sessions WHERE Sessions.id = $session_id");
    }
}
