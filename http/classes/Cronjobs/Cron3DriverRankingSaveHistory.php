<?php

namespace Cronjobs;


/**
 * This cronjob stored the latest driver ranking into the database
 */
class Cron3DriverRankingSaveHistory extends \Core\Cronjob {

    public function __construct() {
        parent::__construct(\Core\Cronjob::IntervalSession);
    }

    protected function process() {

        // check if new race sessions are available
        $max_session_id = \DbEntry\Session::fromLastFinished()->id();
        $last_session_id = (int) $this->loadData("LastScannedRace", 0);

        // get all race sessions since the last scanned race
        $race_sessions = array();
        $query = "SELECT Id FROM Sessions WHERE Id > $last_session_id AND Id <= $max_session_id AND Type = " . \DbEntry\Session::TypeRace;
        $res = \Core\Database::fetchRaw($query);
        foreach ($res as $row) {
            $session = \DbEntry\Session::fromId($row['Id']);
            $race_sessions[] = $session;
        }

        // iterate over all rankings
        foreach (\DbEntry\DriverRanking::listLatest() as $rnk_cur) {

            // check if driver attended a race
            $driver_did_race = FALSE;
            foreach ($race_sessions as $session) {
                foreach ($session->drivers() as $driver) {
                    if ($driver->id() == $rnk_cur->user()->id()) {
                        $driver_did_race = TRUE;
                        break;
                    }
                }
                if ($driver_did_race) break;
            }

            // get previous ranking object
            $rnk_old = $rnk_cur->lastHistory();

            // check if to store into db
            $do_store = FALSE;
            if ($rnk_old === NULL ||                                    // no previous ranking exists
                $driver_did_race ||                                     // driver attended a race
                ($rnk_old->points() + 0.04) < $rnk_cur->points() ||     // significant point decrease
                ($rnk_old->points() - 0.04) > $rnk_cur->points()        // significant point increase
                ) {

                // save into db
                $rnk_cur->pushHistory();
            }
        }

        // save last checked session
        $this->saveData("LastScannedRace", \DbEntry\Session::fromLastCompleted()->id());
    }
}
