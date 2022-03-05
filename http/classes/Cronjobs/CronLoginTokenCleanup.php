<?php

namespace Cronjobs;

class CronLoginTokenCleanup extends \Core\Cronjob {

    public function __construct() {
        parent::__construct(\Core\Cronjob::IntervalDaily);
    }

    protected function process() {
        $count_deleted = 0;
        foreach (\Core\Database::fetch("LoginTokens", ['Id']) as $row) {
            $lt = \DbEntry\LoginToken::fromId($row['Id']);
            if ($lt->expired()) {
                $this->verboseOutput("Deleting LoginToken $lt");
                $lt->delete();
                ++$count_deleted;
            }
        }

        $this->verboseOutput("LoginTokens deleted: $count_deleted");
    }
}
