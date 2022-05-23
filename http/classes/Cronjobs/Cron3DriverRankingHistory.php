<?php

namespace Cronjobs;


/**
 * This cronjob stored the latest driver ranking into the database
 */
class Cron3DriverRankingHistory extends \Core\Cronjob {

    public function __construct() {
        parent::__construct(\Core\Cronjob::IntervalMonthly,
                            \Core\ACswui::parameterCollection()->child("DriverRankingGroupCycle"));
    }

    protected function process() {
        foreach (\DbEntry\DriverRanking::listLatest() as $rnk_cur) {

            // get previous ranking object
            $rnk_old = $rnk_cur->lastHistory();

            // check if to store into db
            $do_store = FALSE;
            if ($rnk_old === NULL ||                                    // no previous ranking exists
                ($rnk_old->points() - 0.04) < $rnk_cur->points() ||     // significant point decrease
                ($rnk_old->points() + 0.04) > $rnk_cur->points() ||     // significant point increase
                $rnk_old->group() != $rnk_cur->groupNext() ||           // new group will be assigned
                $rnk_old->group() != $rnk_cur->group()                  // somehow current group is wrong
                ) {

                // save into db
                $rnk_cur->pushHistory();
            }
        }
    }
}
