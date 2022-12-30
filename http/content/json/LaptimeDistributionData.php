<?php

namespace Content\Json;

class LaptimeDistributionData extends \Core\JsonContent {


    public function __construct() {
        $this->requirePermission("Json");
    }


    public function getDataArray() {

        // get session
        if (!array_key_exists("SessionId", $_GET)) {
            \Core\Log::warning("No SessionId given!");
            return [];
        }
        $session = \DbEntry\Session::fromId($_GET["SessionId"]);
        if ($session === NULL) {
            \Core\Log::warning("Invalid SessionId '" . $_GET["SessionId"] . "'!");
            return [];
        }

        // get user
        if (!array_key_exists("UserId", $_GET)) {
            \Core\Log::warning("No UserId given!");
            return [];
        }
        $user = \DbEntry\User::fromId($_GET["UserId"]);
        if ($user === NULL) {
            \Core\Log::warning("Invalid UserId '" . $_GET["UserId"] . "'!");
            return [];
        } else if (!$user->privacyFulfilled()) {
            return [];
        }

        // options
        $max_delta = NULL;
        if (array_key_exists("LaptimeDistributionMaxDelta", $_GET)) {
            $max_delta = (int) $_GET['LaptimeDistributionMaxDelta'];
            $max_delta *= 1000;
        }

        // get requested data
        $data = array();
        $data['User'] = array();
        $data['User']['Name'] = $user->name();
        $data['User']['Color'] = $user->getParam('UserColor');
        if (array_key_exists("Laptimes", $_GET)) {

            switch ($_GET['Laptimes']) {
                case "Buckets":
                    $data['Laptimes'] = $this->distributionBuckets($session, $user);
                    break;
                case "Deltas":
                    $data['Laptimes'] = $this->laptimeDeltas($session, $user, $max_delta);
                    breaK;
            }

        }


        return $data;
    }


    private function laptimeDeltas($session, $user, $max_delta=NULL) {
        $data = array();
        $besttime = $session->lapBest()->laptime();
        $se = new \Compound\SessionEntry($session, NULL, $user);
        foreach ($session->laps($se, TRUE) as $lap) {
            $delta = $lap->laptime() - $besttime;
            if ($max_delta !== NULL && $delta > $max_delta) continue;
            $data[] = $delta;
        }
        return $data;
    }


    private function distributionBuckets($session, $user) {
        // count laptimes
        $buckets = array();
        $besttime = $session->lapBest()->laptime();
        $laps_of_user = $session->laps($user, TRUE);
        $lap_bucket_increment = 100 / count($laps_of_user);
        foreach ($laps_of_user as $lap) {
            $delta = $lap->laptime() - $besttime;

            // determine bucket
            $bucket = ($delta == 0) ? 0 : (int) round(log10($delta) * 10); // forward calculate bucket number from time

            // throw into bucket
            if (!array_key_exists($bucket, $buckets)) $buckets[$bucket] = 0;
            $buckets[$bucket] += $lap_bucket_increment;
        }

        // reshape for output data
        $output_data = array();
        foreach ($buckets as $bucket=>$value) {
            $data_pair = array();
            $data_pair["x"] = 10 ** ($bucket / 10) / 1000;  // backward calculate time from bucket number
            $data_pair["y"] = $value;
            $output_data[] = $data_pair;
        }

        return $output_data;
    }
}
