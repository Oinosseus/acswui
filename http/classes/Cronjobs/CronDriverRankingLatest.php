<?php

namespace Cronjobs;

class CronDriverRankingLatest extends \Core\Cronjob {

    public function __construct() {
        parent::__construct(\Core\Cronjob::IntervalSession);
    }

    protected function process() {

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

    }
}
