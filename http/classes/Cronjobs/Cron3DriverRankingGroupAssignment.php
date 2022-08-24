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
        foreach (\DbEntry\DriverRanking::listLatest() as $rnk_cur) {

            // get the latest object from DB
            $rnk_old = $rnk_cur->lastHistory();
            if ($rnk_old === NULL) continue;

            // update group assignment of latest object in DB
            $fields = array();
            $fields['RankingGroup'] = $rnk_cur->groupNext();
            \Core\Database::update("DriverRanking", $rnk_old->id(), $fields);
        }
    }
}
