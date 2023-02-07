<?php

namespace Cronjobs;

class CronDriverRankingHistory extends \Core\Cronjob {

    public function __construct() {
        parent::__construct(\Core\Cronjob::IntervalDaily);
    }

    protected function process() {

        foreach (\DbEntry\User::listCommunity() as $u) {

            // check if current ranking shall be transferred to history
            $do_push_history = FALSE;

            // check if history exists
            $rnk_curr = $u->rankingLatest();
            $rnk_hist = \DbEntry\DriverRanking::fromUserLatest($u);
            if ($rnk_hist === NULL) $do_push_history = TRUE;

            // check for significant point difference
            if (!$do_push_history) {
                $pts_curr = $rnk_curr->points();
                $pts_hist = $rnk_hist->points();
                $abs_diff = abs($pts_curr - $pts_hist);
                if ($abs_diff >= 0.5) $do_push_history = TRUE;
            }

            // push history
            if ($do_push_history) {
                $columns = array();
                $this->verboseOutput("Push history for $u<br>");
                $columns['User'] = $u->id();
                $columns['RankingData'] = $rnk_curr->json();
                $columns['RankingPoints'] = $rnk_curr->points();
                $columns['RankingGroup'] = $u->rankingGroup();
                \Core\Database::insert("DriverRanking", $columns);
            }
        }
    }
}
