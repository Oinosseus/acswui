<?php

namespace Cronjobs;

class CronDriverRankingLatest extends \Core\Cronjob {

    public function __construct() {
        parent::__construct(\Core\Cronjob::IntervalSession);
    }

    protected function process() {

        $user_ranking = array();

        // initialize with all active drivers
        foreach (\DbEntry\User::listDrivers() as $u) {

            if (!$u->isCommunity()) continue; // only care for active drivers which are also part of the community

            $user_ranking[$u->id()] = array();
            $user_ranking[$u->id()]['XP'] = array('P'=>0,  'Q'=>0,  'R'=>0);
            $user_ranking[$u->id()]['SX'] = array('RT'=>0, 'Q'=>0,  'R'=>0, 'BT'=>0);
            $user_ranking[$u->id()]['SF'] = array('CT'=>0, 'CE'=>0, 'CC'=>0);
        }

        // get timestamp where this ranking starts
        $days = \Core\ACswui::getParam("DriverRankingDays");
        $now = new \Datetime("now");
        $now->sub(new \DateInterval("P$days" . "D"));
        $then = $now->format("c");
        $this->verboseOutput("Scanning sessions since $then<br>");

        // get results from relevant sessions
        $scanned_sessions = 0;
        $query = "SELECT Id FROM Sessions WHERE Timestamp >= '$then' ORDER BY Id ASC;";
        foreach (\Core\Database::fetchRaw($query) as $row) {
            $session = \DbEntry\Session::fromId($row['Id']);
            $scanned_sessions += 1;

            // walk through all session results
            foreach ($session->results() as $rslt) {
                $uid = $rslt->user()->id();
                $ranking_points = $rslt->rankingPoints();

                // skip user if not an active driver
                if (!array_key_exists($uid, $user_ranking)) continue;

                // add results
                foreach (array_keys($user_ranking[$uid]) as $grp) {
                    foreach (array_keys($user_ranking[$uid][$grp]) as $key) {
                        if ($grp == "SX" && $key == "BT") continue;  // skip, because not existing in session results
                        $user_ranking[$uid][$grp][$key] += $ranking_points[$grp][$key];
                    }
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
                            $user_ranking[$uid]['SX']['BT'] += $leading_positions * \Core\Acswui::getPAram('DriverRankingSxBt');
                        }

                        $leading_positions += 1;
                    }
                }
            }
        }

        // store ranking
        $filepath = \Core\Config::AbsPathData . "/htcache/driver_ranking.json";
        $f = fopen($filepath, "w");
        fwrite($f, json_encode($user_ranking, JSON_PRETTY_PRINT));
        fclose($f);

        $this->verboseOutput("Scanned sessions = $scanned_sessions<br>");
        $this->verboseOutput("Scanned records = $scanned_records<br>");
    }
}
