<?php

namespace Content\Json;

class LaptimeDistributionData extends \Core\JsonContent {

    const xValues = [0.1, 0.2, 0.3, 0.5, 0.6, 0.7, 0.8, 0.9,
                    1, 2, 3, 4, 5, 6, 7, 8, 9,
                    10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20];

    public function __construct() {
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
        foreach ($session->laps($user, TRUE) as $lap) {
            $delta = $lap->laptime() - $besttime;
            if ($max_delta !== NULL && $delta > $max_delta) continue;
            $data[] = $delta;
        }
        return $data;
    }


    private function distributionBuckets($session, $user) {
        // dataset
        $data = array();
        foreach (LaptimeDistributionData::xValues as $x) $data[] = array('x'=>$x, 'y'=>0);

        // count laptimes
        $besttime = $session->lapBest()->laptime();
        $laps_of_user = $session->laps($user, TRUE);
        foreach ($laps_of_user as $lap) {
            $delta = ($lap->laptime() - $besttime) / 1000;
            if ($delta > 20) {
                // don't care for such slow laps
                continue;
            } else if ($delta >= 10) {
                $delta = ceil($delta / 10) * 10;
            } else if ($delta >= 1) {
                $delta = ceil($delta);
            } else {
                $delta = ceil($delta * 10) / 10;
                if ($delta == 0) {
                    $delta = 0.1; // besttime
                }
            }

            $index = array_search($delta, LaptimeDistributionData::xValues);
            $data[$index]['y'] += 1;
        }

        // normalize lapcounts
        for ($i=0; $i < count($data); ++$i) {
            $data[$i]['y'] = round($data[$i]['y'] * 100 / count($laps_of_user));
        }

        return $data;
    }
}
