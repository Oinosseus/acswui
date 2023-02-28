<?php

namespace Cronjobs;

class CronDriverRankingLatest extends \Core\Cronjob {

    public function __construct() {
        parent::__construct(\Core\Cronjob::IntervalSession);
    }

    protected function process() {


        # ---------------------------------------------------------------------
        #                     Calculate Current Ranking
        # ---------------------------------------------------------------------

        // determine time
        $days = \Core\ACswui::getParam("DriverRankingDays");
        $dt = new \Datetime("now");
        $dt->sub(new \DateInterval("P$days" . "D"));

        // calculate
        $user_ranking = \Core\DriverRankingPoints::calculateSince($dt);

        // store into database
        foreach ($user_ranking as $uid=>$drp) {

            // update latest value into user table
            $columns = array();
            $columns['RankingLatestData'] = $drp->json();
            $columns['RankingLatestPoints'] = $drp->points();
            \Core\Database::update("Users", $uid, $columns);
        }


        # ---------------------------------------------------------------------
        #                     Calculate Next Ranking
        # ---------------------------------------------------------------------

        // determine time
        $days = \Core\ACswui::getParam("DriverRankingDays");
        $dt = new \Datetime("now");

        // find the day of next driver ranking
        $enum = \Core\ACSwui::parameterCollection()->child("DriverRankingGroupCycle");
        for ($i=0; $i<=31; ++$i) {  // try all days, up to one month
            $dt->add(new \DateInterval("P1D"));
            if ($enum->dayMatches($dt)) {

                // calculate
                $dt->sub(new \DateInterval("P$days" . "D"));
                $user_ranking = \Core\DriverRankingPoints::calculateSince($dt);

                // store into database
                foreach ($user_ranking as $uid=>$drp) {

                    // update latest value into user table
                    $columns = array();
                    $columns['RankingPointsNext'] = $drp->points();
                    \Core\Database::update("Users", $uid, $columns);
                    // echo "HERE, $uid, {$drp->points()}<br>";
                }

                break;
            }
        }

    }
}
