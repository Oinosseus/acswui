<?php

namespace Cronjobs;

class CronDriverRankingGroups extends \Core\Cronjob {

    public function __construct() {
        parent::__construct(\Core\Cronjob::IntervalMonthly,
                            \Core\ACswui::parameterCollection()->child("DriverRankingGroupCycle"));
    }

    protected function process() {
        $count_rising = 0;
        $count_falling = 0;
        foreach (\DbEntry\DriverRanking::listLatest() as $rnk) {
            $uid = $rnk->user()->id();

            // get last ranking
            $query = "SELECT Id FROM DriverRanking WHERE User = $uid ORDER BY Id DESC LIMIT 1;";
            $res = \Core\Database::fetchRaw($query);
            $rnk_old = (count($res) == 0) ? NULL : \DbEntry\DriverRanking::fromId($res[0]['Id']);

            // update group
            if ($rnk_old !== NULL) {
                $group_old = $rnk->rankingGroup();
                $group_new = $rnk->rankingGroupCalculated();

                // only allow jumping one group at a time
                if ($group_new < $group_old) {
                    \Core\Database::update("DriverRanking", $rnk_old->id(), ["RankingGroup"=>($group_old-1), "RankingLast"=>$current_ranking_points]);
                    $count_rising += 1;
                } else if ($group_new > $group_old) {
                    \Core\Database::update("DriverRanking", $rnk_old->id(), ["RankingGroup"=>($group_old+1), "RankingLast"=>$current_ranking_points]);
                    $count_falling += 1;
                }

                // update last points
                $current_ranking_points = $rnk->points();
                \Core\Database::update("DriverRanking", $rnk_old->id(), ["RankingLast"=>$current_ranking_points]);
            }
        }

        $this->verboseOutput("Rising Drivers = $count_rising<br>");
        $this->verboseOutput("Falling Drivers = $count_falling<br>");
    }
}
