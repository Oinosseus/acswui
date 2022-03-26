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
        $now = \Core\Core::now();
        $past_days = \Core\ACswui::getParam("DriverRankingDays");
        $then = $now->sub(new \DateInterval("P{$past_days}D"));
        $then = \Core\Database::dateTime2timestamp($then);
        $query = "SELECT Id FROM DriverRanking WHERE User = {$user->id()} AND Timestamp > '$then' ORDER BY Id DESC;";

        // request database
        $now = \Core\Core::now();
        $data['Ranking'] = array();
        $res =  \Core\Database::fetchRaw($query);
        foreach ($res as $row) {
            $rnk = \DbEntry\DriverRanking::fromId($row['Id']);
            $diff = \Core\TimeInterval::fromDateInterval($now->diff($rnk->timestamp()));
            $data['Ranking'][] = array('x'=>$diff->days(), 'y'=>$rnk->points());
        }

        return $data;
    }
}