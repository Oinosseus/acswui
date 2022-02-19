<?php

namespace Cronjobs;

class CronDriverRanking2Database extends \Core\Cronjob {

    public function __construct() {
        parent::__construct(\Core\Cronjob::IntervalDaily);
    }

    protected function process() {
        $store_count = 0;
        foreach (\DbEntry\DriverRanking::listLatest() as $rnk_new) {
            $uid = $rnk_new->user()->id();

            // get last ranking
            $query = "SELECT Id FROM DriverRanking WHERE User = $uid ORDER BY Id DESC LIMIT 1;";
            $res = \Core\Database::fetchRaw($query);
            $rnk_old = (count($res) == 0) ? NULL : \DbEntry\DriverRanking::fromId($res[0]['Id']);

            // save to db (if not equal to last DB entry)
            if ($rnk_old === NULL || !$rnk_new->equalRankingTo($rnk_old)) {
                $rnk_new->save();
                $store_count += 1;
            }
        }

        $this->verboseOutput("Store Count = $store_count<br>");
    }
}
