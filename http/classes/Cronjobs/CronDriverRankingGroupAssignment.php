<?php

namespace Cronjobs;


/**
 * This cronjob stored the latest driver ranking into the database
 */
class CronDriverRankingGroupAssignment extends \Core\Cronjob {

    public function __construct() {
        parent::__construct(\Core\Cronjob::IntervalMonthly,
                            \Core\ACswui::parameterCollection()->child("DriverRankingGroupCycle"));
    }

    protected function process() {
        $this->assignGroups();
        // $this->calculatePointsAtNextAssignment();
    }


    private function assignGroups() {

        // group assign all drivers
        $processed_user_ids = array();
        foreach (\DbEntry\User::listDrivers() as $u) {

            $processed_user_ids[] = $u->id();
            $group_current = $u->rankingGroup();
            $group_next = \Core\DriverRankingPoints::calculateGroup($u->rankingLatest()->points());

            // check for pro-/demotion
            $group_change = 0;
            if ($group_next > $group_current && $group_current < (\Core\Config::DriverRankingGroups - 1)) {
                $group_change = 1;
            } else if ($group_next < $group_current && $group_current > 0) {
                $group_change = -1;
            }

            // update database
            if ($group_change !== 0) {

                // calculate next group
                $group_next = $group_current + $group_change;

                // user info
                $term = ($group_change < 0) ? "Demoting" : "Promoting";
                $this->verboseOutput("$term user $u from group $group_current to $group_next<br>");

                // database
                $columns = array();
                $columns["RankingGroup"] = $group_next;
                $columns["RankingPoints"] = $u->rankingLatest()->points();
                \Core\Database::update("Users", $u->id(), $columns);
            }
        }

        // reset group of all other drivers
        $query = "SELECT Id FROM Users WHERE RankingGroup!=0;";
        foreach (\Core\Database::fetchRaw($query) as $row) {
            $id = (int) $row['Id'];
            if (in_array($id, $processed_user_ids)) continue;
            \Core\Database::update("Users", $id, ['RankingGroup'=>0,
                                                  'RankingPoints'=>0,
                                                  'RankingLatestPoints'=>0,
                                                  'RankingLatestData'=>""]);
        }
    }


    // private function calculatePointsAtNextAssignment() {
    //
    //     // determine time
    //     $days = \Core\ACswui::getParam("DriverRankingDays");
    //     $dt = new \Datetime("now");
    //
    //     // find the day of next driver ranking
    //     $enum = \Core\ACSwui::parameterCollection()->child("DriverRankingGroupCycle");
    //     for ($i=0; $i<=31; ++$i) {  // try all days, up to one month
    //         $dt->add(new \DateInterval("P1D"));
    //         if ($enum->dayMatches($dt)) {
    //
    //             // calculate
    //             $dt->sub(new \DateInterval("P$days" . "D"));
    //             $user_ranking = \Core\DriverRankingPoints::calculateSince($dt);
    //
    //             // store into database
    //             foreach ($user_ranking as $uid=>$drp) {
    //
    //                 // update latest value into user table
    //                 $columns = array();
    //                 $columns['RankingPointsNext'] = $drp->points();
    //                 \Core\Database::update("Users", $uid, $columns);
    //                 // echo "HERE, $uid, {$drp->points()}<br>";
    //             }
    //
    //             break;
    //         }
    //     }
    // }
}
