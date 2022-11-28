<?php

namespace Cronjobs;


/**
 * This cronjob stored the latest driver ranking into the database
 */
class Cron3DriverRankingGroupAssignment extends \Core\Cronjob {

    public function __construct() {
        parent::__construct(\Core\Cronjob::IntervalMonthly,
                            \Core\ACswui::parameterCollection()->child("DriverRankingGroupCycle"));
    }

    protected function process() {

        // walk through all current rankings
        $user_ids = array();
        foreach (\DbEntry\DriverRanking::listLatest() as $rnk) {
            $user_ids[] = $rnk->user()->id();

            // user info
            $current_group = $rnk->user()->rankingGroup();
            $next_group = $rnk->groupNext();
            if ($next_group < $current_group)
                    $this->verboseOutput("Promoting user {$rnk->user()->id()} from group $current_group to $next_group<br>");
            else if ($next_group > $current_group)
                    $this->verboseOutput("Demoting user {$rnk->user()->id()} from group $current_group to $next_group<br>");

            // update ranking
            $columns = array();
            $columns["RankingGroup"] = $rnk->groupNext();
            $columns["RankingPoints"] = $rnk->points();
            \Core\Database::update("Users", $rnk->user()->id(), $columns);
        }

        // reset group of all other drivers
        $query = "SELECT Id FROM Users WHERE RankingGroup!=0;";
        foreach (\Core\Database::fetchRaw($query) as $row) {
            $id = (int) $row['Id'];
            if (in_array($id, $user_ids)) continue;
            \Core\Database::update("Users", $id, ['RankingGroup'=>0, 'RankingPoints'=>0]);
        }
    }
}
