<?php

namespace Cronjobs;

class CleanEmptySessions extends \Core\Cronjob {

    public function __construct() {
        parent::__construct(\Core\Cronjob::IntervalSession);
    }

    protected function process() {

        // determine sessions to be checked
        $max_session_id = \Core\Cronjob::lastCompletedSession();
        $last_session_id = $this->loadData("LastCleanedSession", 0);

        // find all empty sessions
        $empty_session_ids = array();
        $query = "SELECT Id FROM Sessions WHERE Id > $last_session_id and Id <= $max_session_id";
        $res = \Core\Database::fetchRaw($query);
        foreach ($res as $row) {
            $session_id = (int) $row['Id'];

            if ($this->countLaps($session_id) == 0) $empty_session_ids[] = $session_id;
        }

        // delete empty sessions
        foreach ($empty_session_ids as $session_id) {
            $this->verboseOutput("Deleting Session $session_id<br>");
            $this->deleteSession($session_id);
        }

        // save last checked session
        $this->saveData("LastCleanedSession", $max_session_id);
    }


    private function countLaps(int $session_id) {
        $query = "SELECT COUNT(Id) FROM `Laps` WHERE SESSION = $session_id;";
        $res = \Core\Database::fetchRaw($query);
        return (int) $res[0]['COUNT(Id)'];
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
