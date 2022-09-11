<?php

namespace Content\Json;

/**
 */
class DriverRankingData extends \Core\JsonContent {


    public function __construct() {
        $this->requirePermission("Json");
    }


    public function getDataArray() {

        $data = array();

        // get requested user
        if (!array_key_exists("UserId", $_GET)) {
            \Core\Log::error("UserId not requested!");
            return array();
        }
        $user = \DbEntry\User::fromId($_GET['UserId']);
        if ($user === NULL) {
            \Core\Log::error("Invalid UserId '{$_GET['UserId']}'!");
            return array();
        }
        if (!$user->privacyFulfilled()) return array();

        // user data
        $data = array();
        $data['User'] = array();
        $data['User']['Name'] = $user->name();
        $data['User']['Color'] = $user->getParam("UserColor");

        // create query
        $now = new \DateTime("now");
        $past_days = \Core\ACswui::getParam("DriverRankingDays");
        $then = $now->sub(new \DateInterval("P{$past_days}D"));
        $then = \Core\Database::timestamp($then);
        $query = "SELECT Id FROM DriverRanking WHERE User = {$user->id()} AND Timestamp > '$then' ORDER BY Id DESC;";

        // add latest ranking
        $data['Ranking'] = array();
        $now = new \DateTime("now");
        foreach (\DbEntry\DriverRanking::listLatest() as $rnk) {
            if ($rnk->user() !== NULL && $rnk->user()->id() == $user->id()) {
                $diff = \Core\TimeInterval::fromDateInterval($now->diff($rnk->timestamp()));
                $data['Ranking'][] = array('x'=>$diff->days(), 'y'=>$rnk->points());
                break;
            }
        }

        // request database
        $now = new \DateTime("now");
        $res =  \Core\Database::fetchRaw($query);
        foreach ($res as $row) {
            $rnk = \DbEntry\DriverRanking::fromId($row['Id']);
            $diff = \Core\TimeInterval::fromDateInterval($now->diff($rnk->timestamp()));
            $data['Ranking'][] = array('x'=>$diff->days(), 'y'=>$rnk->points());
        }

        return $data;
    }
}
