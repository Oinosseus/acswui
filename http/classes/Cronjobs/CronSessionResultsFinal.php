<?php

namespace Cronjobs;

class CronSessionResultsFinal extends \Core\Cronjob {

    public function __construct() {
        parent::__construct(\Core\Cronjob::IntervalAlways);
    }

    protected function process() {

        // $last_session_id = (int) $this->loadData("LastCleanedSession", 0);
        // $this->saveData("LastCleanedSession", $max_session_id);

        $time_start = microtime(TRUE);

        $session_count = 0;
        $query = "SELECT Id FROM Sessions WHERE FinalResultsCalculated=0 ORDER BY Id ASC;";
        foreach(\Core\Database::fetchRaw($query) as $row) {

            // interrupt processing to save CPU load
            $time_elapsed = microtime(TRUE) - $time_start;
            if ($time_elapsed > 10) break;

            // get session and calculate results
            $s = \DbEntry\Session::fromId((int) $row['Id']);
            \DbEntry\SessionResultFinal::calculate($s);

            // user info
            $this->verboseOutput("Processing $s<br>");
            ++$session_count;
        }

        // user info
        $this->verboseOutput("$session_count results calculated<br>");
    }


}
