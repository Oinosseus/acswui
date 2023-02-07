<?php

namespace Cronjobs;

class CronDriverRankingLatest extends \Core\Cronjob {

    public function __construct() {
        parent::__construct(\Core\Cronjob::IntervalSession);
    }

    protected function process() {
        // \DbEntry\DriverRanking::calculateLatest();
        // User->id() => \Core\DriverRankingPoints
        $user_ranking = array();

        // initialize with all active drivers
        foreach (\DbEntry\User::listDrivers() as $u) {
            if (!$u->isCommunity()) continue; // only care for active drivers which are also part of the community
            $user_ranking[$u->id()] = new \Core\DriverRankingPoints();
        }

        // get timestamp where this ranking starts
        $days = \Core\ACswui::getParam("DriverRankingDays");
        $now = new \Datetime("now");
        $now->sub(new \DateInterval("P$days" . "D"));
        $then = $now->format("c");

        // get results from relevant sessions
        $scanned_sessions = 0;
        $query = "SELECT Id FROM Sessions WHERE Timestamp >= '$then' ORDER BY Id ASC;";
        foreach (\Core\Database::fetchRaw($query) as $row) {
            $session = \DbEntry\Session::fromId($row['Id']);
            $scanned_sessions += 1;

            // walk through all session results
            foreach (\DbEntry\SessionResultFinal::listResults($session) as $rslt) {

                // apply session result to all drivers
                foreach ($rslt->driver()->users() as $user) {

                    // skip user if not an active driver
                    $uid = $user->id();
                    if (!array_key_exists($uid, $user_ranking)) continue;

                    // add results
                    $rp = $rslt->rankingPoints();
                    $user_ranking[$uid]->add($rp);
                }
            }
        }

        // count best time positions
        $scanned_records = 0;
        $records_file = \Core\Config::AbsPathData . "/htcache/records_carclass.json";
        if (file_exists($records_file)) {
            $records = json_decode(file_get_contents($records_file), TRUE);

            foreach (array_keys($records) as $carclass_id) {
                foreach (array_keys($records[$carclass_id]) as $track_id) {
                    $scanned_records += 1;

                    $leading_positions = 0;
                    foreach (array_reverse($records[$carclass_id][$track_id]) as $lap_id) {
                        $uid = \DbEntry\Lap::fromId($lap_id)->user()->id();

                        if (array_key_exists($uid, $user_ranking)) {
                            $user_ranking[$uid]->addSxBt($leading_positions);
                        }

                        $leading_positions += 1;
                    }
                }
            }
        }

        // store into database
        foreach ($user_ranking as $uid=>$drp) {

            // update latest value into user table
            $columns = array();
            $columns['RankingLatestData'] = $drp->json();
            $columns['RankingLatestPoints'] = $drp->points();
            \Core\Database::update("Users", $uid, $columns);
        }

    }
}
