<?php

class DrvRnk {
    public $User = NULL;
    public $DrivenPracticeLength = 0;
    public $DrivenQualifyLength = 0;
    public $DrivenRaceLength = 0;
    public $CollisionsEnvH = 0;
    public $CollisionsEnvL = 0;
    public $CollisionsCarH = 0;
    public $CollisionsCarL = 0;
    public $RaceAheadPositions = 0;
    public $QualifyingAheadPositions = 0;
    public $BestTimesAheadPositions = 0;
    public $BestRaceTime = 0;
    public $Cuts = 0;
    public $Score = 0;

    public function __construct($user) {
        $this->User = $user;
    }

    public function getScore($group=NULL, $value=NULL) {
        global $acswuiConfig;

        $driven = $this->DrivenPracticeLength + $this->DrivenQualifyLength + $this->DrivenRaceLength;

        if ($group == NULL) {
            $result  = $this->getScore("XP", "R");
            $result += $this->getScore("XP", "Q");
            $result += $this->getScore("XP", "P");
            $result += $this->getScore("SX", "R");
            $result += $this->getScore("SX", "Q");
            $result += $this->getScore("SX", "RT");
            $result += $this->getScore("SX", "BT");
            $result += $this->getScore("SF", "CUT");
            $result += $this->getScore("SF", "CEH");
            $result += $this->getScore("SF", "CEL");
            $result += $this->getScore("SF", "CCH");
            $result += $this->getScore("SF", "CCL");

        } else if ($group == "XP") {

            if ($value == "R") {
                $result = $acswuiConfig->DriverRanking['XP']['R'];
                $result *= $this->DrivenRaceLength * 1e-6;

            } else if ($value == "Q") {
                $result = $acswuiConfig->DriverRanking['XP']['Q'];
                $result *= $this->DrivenQualifyLength * 1e-6;

            } else if ($value == "P") {
                $result = $acswuiConfig->DriverRanking['XP']['P'];
                $result *= $this->DrivenPracticeLength * 1e-6;
            }


        } else if ($group == "SX") {

            if ($value == "R") {
                $result = $acswuiConfig->DriverRanking['SX']['R'];
                $result *= $this->RaceAheadPositions;

            } else if ($value == "Q") {
                $result = $acswuiConfig->DriverRanking['SX']['Q'];
                $result *= $this->QualifyingAheadPositions;

            } else if ($value == "RT") {
                $result = $acswuiConfig->DriverRanking['SX']['RT'];
                $result *= $this->BestRaceTime;

            } else if ($value == "BT") {
                $result = $acswuiConfig->DriverRanking['SX']['BT'];
                $result *= $this->BestTimesAheadPositions;
            }


        } else if ($group == "SF") {

            if ($value == "CUT") {
                $result = $acswuiConfig->DriverRanking['SF']['CUT'];
                $result *= $this->Cuts;
                $result /= 1e-6 * $driven;

            } else if ($value == "CEH") {
                $result = $acswuiConfig->DriverRanking['SF']['CEH'];
                $result *= $this->CollisionsEnvH;
                $result /= 1e-6 * $driven;

            } else if ($value == "CEL") {
                $result = $acswuiConfig->DriverRanking['SF']['CEL'];
                $result *= $this->CollisionsEnvL;
                $result /= 1e-6 * $driven;

            } else if ($value == "CCH") {
                $result = $acswuiConfig->DriverRanking['SF']['CCH'];
                $result *= $this->CollisionsCarH;
                $result /= 1e-6 * $driven;

            } else if ($value == "CCL") {
                $result = $acswuiConfig->DriverRanking['SF']['CCL'];
                $result *= $this->CollisionsCarL;
                $result /= 1e-6 * $driven;
            }

        }

        return $result;
    }

    public function getScoreStr($group=NULL, $value=NULL) {
        $score = $this->getScore($group, $value);
        return sprintf("%0.0f", $score);
    }

    public function getScoreHtml($group=NULL) {
        if ($group == NULL) {
            $html = sprintf("<span title=\"%0.1f\">", $this->getScore());
            $html .= sprintf("%0.0f</span>", $this->getScore());

        } else if ($group == "XP") {
            $val_r = $this->getScore($group, "R");
            $val_q = $this->getScore($group, "Q");
            $val_p = $this->getScore($group, "P");
            $html = sprintf("<span title=\"R = %0.1f\nQ = %0.1f\nP = %0.1f\">", $val_r, $val_q, $val_p);
            $html .= sprintf("%0.0f</span>", $val_r + $val_q + $val_p);

        } else if ($group == "SX") {
            $val_r = $this->getScore($group, "R");
            $val_q = $this->getScore($group, "Q");
            $val_rt = $this->getScore($group, "RT");
            $val_bt = $this->getScore($group, "BT");
            $html = sprintf("<span title=\"R = %0.1f\nQ = %0.1f\nRT = %0.1f\nBT = %0.1f\">", $val_r, $val_q, $val_rt, $val_bt);
            $html .= sprintf("%0.0f</span>", $val_r + $val_q + $val_rt + $val_bt);

        } else if ($group == "SF") {
            $val_cut = $this->getScore($group, "CUT");
            $val_ceh = $this->getScore($group, "CEH");
            $val_cel = $this->getScore($group, "CEL");
            $val_cch = $this->getScore($group, "CCH");
            $val_ccl = $this->getScore($group, "CCL");
            $html = sprintf("<span title=\"CUT = %0.1f\nCEH = %0.1f\nCEL = %0.1f\nCCH = %0.1f\nCCL = %0.1f\">", $val_cut, $val_ceh, $val_cel, $val_cch, $val_ccl);
            $html .= sprintf("%0.0f</span>", $val_cut + $val_ceh + $val_cel + $val_cch + $val_ccl);

        }

        return $html;
    }
}


class driver_ranking extends cContentPage {

    private $CurrentCarClass = NULL;

    public function __construct() {
        $this->MenuName   = _("Driver Ranking");
        $this->PageTitle  = "Driver Leaderboard";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_DriverRanking"];
    }


    public function getHtml() {
        global $acswuiConfig;
        global $acswuiDatabase;
        global $acswuiLog;


        $html = "";

        // --------------------------------------------------------------------
        //                             Calculation
        // --------------------------------------------------------------------

        $driver_rank_dict = array(); // key = UserId, value = DrvRnk()

        // minimum timestamp
        $d1 = new DateTime();
        $dt = new DateInterval("P" . $acswuiConfig->DriverRanking['DEF']['LT'] . "D");
        $d0 = $d1->sub($dt);
//         echo $d0->format("Y-m-d") . "<br>";


        // scan laps of sessions
        $query = "SELECT Id FROM Sessions WHERE Timestamp >= '" . $d0->format("Y-m-d") . "'";
        foreach ($acswuiDatabase->fetch_raw_select($query) as $row) {
            $session = new Session($row['Id']);


            // collisions
            foreach ($session->collisions() as $cll) {

                $user = $cll->user();

                // get driver ranking object
                if (!array_key_exists($user->Id(), $driver_rank_dict)) {
                    $driver_rank_dict[$user->Id()] = new DrvRnk($user);
                }
                $drv_rnk = $driver_rank_dict[$user->Id()];

                // CollisionCar
                if ($cll->type() == CollisionType::Car) {
                    if ($cll->speed() >= $acswuiConfig->DriverRanking['DEF']['VHL'])
                        $drv_rnk->CollisionsCarH += 1;
                    else
                        $drv_rnk->CollisionsCarL += 1;

                // CollisionEnv
                } else if ($cll->type() == CollisionType::Env) {
                    if ($cll->speed() >= $acswuiConfig->DriverRanking['DEF']['VHL'])
                        $drv_rnk->CollisionsEnvH += 1;
                    else
                        $drv_rnk->CollisionsEnvL += 1;

                // Unknown Collision
                } else {
                    $acswuiLog->logError("Unknown Collision type!");
                }

                // save driver ranking object
                $driver_rank_dict[$user->Id()] = $drv_rnk;
            }


            // laps (driven length)
            foreach ($session->drivenLaps() as $lap) {

                $user = $lap->user();

                // get driver ranking object
                if (!array_key_exists($user->Id(), $driver_rank_dict)) {
                    $driver_rank_dict[$user->Id()] = new DrvRnk($user);
                }
                $drv_rnk = $driver_rank_dict[$user->Id()];

                // store driven length
                if ($session->type() == 1) {
                    $drv_rnk->DrivenPracticeLength += $session->track()->length();
                } else if ($session->type() == 2) {
                    $drv_rnk->DrivenQualifyLength += $session->track()->length();
                } else if ($session->type() == 3) {
                    $drv_rnk->DrivenRaceLength += $session->track()->length();
                }

                // cuts
                $drv_rnk->Cuts += $lap->cuts();

                // save driver ranking object
                $driver_rank_dict[$user->Id()] = $drv_rnk;
            }


            // results (race/qualifying position)
            foreach ($session->results() as $rslt) {

                // get driver ranking object
                $user = $rslt->user();
                if (!array_key_exists($user->Id(), $driver_rank_dict)) {
                    $driver_rank_dict[$user->Id()] = new DrvRnk($user);
                }
                $drv_rnk = $driver_rank_dict[$user->Id()];

                // ahead position
                $ahead_position = count($session->results()) - $rslt->position();
                if ($session->type() == 3)
                    $drv_rnk->RaceAheadPositions += $ahead_position;
                else if ($session->type() == 2)
                    $drv_rnk->QualifyingAheadPositions += $ahead_position;

                // save driver ranking object
                $driver_rank_dict[$user->Id()] = $drv_rnk;
            }


            // best race time position
            if ($session->type() == 3) {

                $results = $session->results();
                if (count($results) > 0) {

                    // sort by laptime
                    function compare_result_laptime($r1, $r2) {
                        return ($r1->bestlap() < $r2->bestlap()) ? -1 : 1;
                    }
                    usort($results, "compare_result_laptime");

                    // get driver ranking object
                    $rslt = $results[0];
                    $user = $rslt->user();
                    if (!array_key_exists($user->Id(), $driver_rank_dict)) {
                        $driver_rank_dict[$user->Id()] = new DrvRnk($user);
                    }
                    $drv_rnk = $driver_rank_dict[$user->Id()];

                    // add best race time
                    $drv_rnk->BestRaceTime += 1;

                    // save driver ranking object
                    $driver_rank_dict[$user->Id()] = $drv_rnk;
                }

            }

        }


        // scan car class records
        $file_path = $acswuiConfig->AcsContent . "/stats_carclass_records.json";
        $class_records = json_decode(file_get_contents($file_path), TRUE);
        foreach ($class_records as $car_class_id=>$records) {
            foreach ($records as $track_id=>$best_lap_ids) {
                for ($pos=0; $pos<count($best_lap_ids); ++$pos) {

                    $lap = new Lap($best_lap_ids[$pos]);
                    $user = $lap->user();

                    // get driver ranking object
                    if (!array_key_exists($user->Id(), $driver_rank_dict)) {
                        $driver_rank_dict[$user->Id()] = new DrvRnk($user);
                    }
                    $drv_rnk = $driver_rank_dict[$user->Id()];

                    // calculate ahead positions
                    $ahead_position = count($best_lap_ids) - $pos - 1;
                    $drv_rnk->BestTimesAheadPositions += $ahead_position;

                    // save driver ranking object
                    $driver_rank_dict[$user->Id()] = $drv_rnk;
                }
            }
        }


        // list driver rankings
        $driver_rank_list = array();
        foreach ($driver_rank_dict as $user_id=>$drv_rnk) {
            $driver_rank_list[] = $drv_rnk;
        }

        // sort driver rankings
        function compare_rankings($r1, $r2) {
            return ($r1->getScore() < $r2->getScore()) ? 1 : -1;
        }
        usort($driver_rank_list, "compare_rankings");



        // --------------------------------------------------------------------
        //                             Ranking Table
        // --------------------------------------------------------------------

        // table head
        $html .= "<table>";
        $html .= "<tr>";
        $html .= "<th>" . _("Driver") . "</th>";
        $html .= "<th>XP</th>";
        $html .= "<th>SX</th>";
        $html .= "<th>SF</th>";
        $html .= "<th>" . _("Score") . "</th>";
        $html .= "</tr>";

        foreach ($driver_rank_list as $drv_rnk) {

            $html .= "<tr>";
            $html .= "<td>" . $drv_rnk->User->login() . "</td>";
            $html .= "<td>" . $drv_rnk->getScoreHtml("XP") . "</td>";
            $html .= "<td>" . $drv_rnk->getScoreHtml("SX", "RT") . "</td>";
            $html .= "<td>" . $drv_rnk->getScoreHtml("SF", "CUT") . "</td>";
            $html .= "<td>" . $drv_rnk->getScoreHtml() . "</td>";
            $html .= "</tr>";
        }

        $html .= "</table>";


        // --------------------------------------------------------------------
        //                             Description
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("Description") . "</h1>";

        $html .= "<table>";

        $html .= "<tr>";
        $html .= "<th colspan=\"2\">" . _("Symbol") . "</th>";
        $html .= "<th>" . _("Score") . "</th>";
        $html .= "<th>" . _("Description") . "</th>";
        $html .= "</tr>";

        // XP
        $html .= "<tr>";
        $html .= "<td rowspan=\"3\">XP</td>";
        $html .= "<td>R</td>";
        $html .= "<td>" . sprintf("%+0.2f/1Mm", $acswuiConfig->DriverRanking['XP']['R']) . "</td>";
        $html .= "<td>" . _("Experience from driven race length") . "<br><small>Experience Race</small></td>";
        $html .= "</tr>";

        $html .= "<tr>";
        $html .= "<td>Q</td>";
        $html .= "<td>" . sprintf("%+0.2f/1Mm", $acswuiConfig->DriverRanking['XP']['Q']) . "</td>";
        $html .= "<td>" . _("Experience from driven qualifying length") . "<br><small>Experience Qualifying</small></td>";
        $html .= "</tr>";

        $html .= "<tr>";
        $html .= "<td>P</td>";
        $html .= "<td>" . sprintf("%+0.2f/1Mm", $acswuiConfig->DriverRanking['XP']['P']) . "</td>";
        $html .= "<td>" . _("Experience from driven practice length") . "<br><small>Experience Practice</small></td>";
        $html .= "</tr>";


        // SX
        $html .= "<tr>";
        $html .= "<td rowspan=\"4\">SX</td>";
        $html .= "<td>R</td>";
        $html .= "<td>" . sprintf("%+0.2f/Position", $acswuiConfig->DriverRanking['SX']['R']) . "</td>";
        $html .= "<td>" . _("Success points for race positions (leading ahead another driver).") . "<br><small>Success Race</small></td>";
        $html .= "</tr>";

        $html .= "<tr>";
        $html .= "<td>Q</td>";
        $html .= "<td>" . sprintf("%+0.2f/Position", $acswuiConfig->DriverRanking['SX']['Q']) . "</td>";
        $html .= "<td>" . _("Success points for qualifying positions (leading ahead another driver).") . "<br><small>Success Qualifying</small></td>";
        $html .= "</tr>";

        $html .= "<tr>";
        $html .= "<td>RT</td>";
        $html .= "<td>" . sprintf("%+0.2f/Race", $acswuiConfig->DriverRanking['SX']['RT']) . "</td>";
        $html .= "<td>" . _("Success points for best time in a race session.") . "<br><small>Success Race Time</small></td>";
        $html .= "</tr>";

        $html .= "<tr>";
        $html .= "<td>BT</td>";
        $html .= "<td>" . sprintf("%+0.2f/Position", $acswuiConfig->DriverRanking['SX']['BT']) . "</td>";
        $html .= "<td>" . _("Success points for overall track time per car class (leading ahead another driver). This is independent of the lease time.") . "<br><small>Success Best Time</small></td>";
        $html .= "</tr>";


        // SF
        $html .= "<tr>";
        $html .= "<td rowspan=\"5\">SF</td>";
        $html .= "<td>CUT</td>";
        $html .= "<td>" . sprintf("%+0.2f/Cut/1Mm", $acswuiConfig->DriverRanking['SF']['CUT']) . "</td>";
        $html .= "<td>" . _("Safety deduction points for cuts.") . "<br><small>Safety Cuts</small></td>";
        $html .= "</tr>";

        $html .= "<tr>";
        $html .= "<td>CEH</td>";
        $html .= "<td>" . sprintf("%+0.2f/Collision/1Mm", $acswuiConfig->DriverRanking['SF']['CEH']) . "</td>";
        $html .= "<td>" . _("Safety deduction points for crashing with the environment at high speed") . "<br><small>Safety Collision Environment High</small></td>";
        $html .= "</tr>";

        $html .= "<tr>";
        $html .= "<td>CEL</td>";
        $html .= "<td>" . sprintf("%+0.2f/Collision/1Mm", $acswuiConfig->DriverRanking['SF']['CEL']) . "</td>";
        $html .= "<td>" . _("Safety deduction points for crashing with the environment at low speed") . "<br><small>Collision Environment Low</small></td>";
        $html .= "</tr>";

        $html .= "<tr>";
        $html .= "<td>CCH</td>";
        $html .= "<td>" . sprintf("%+0.2f/Collision/1Mm", $acswuiConfig->DriverRanking['SF']['CCH']) . "</td>";
        $html .= "<td>" . _("Safety deduction points for crashing with another car at high speed difference") . "<br><small>Collision Car High</small></td>";
        $html .= "</tr>";

        $html .= "<tr>";
        $html .= "<td>CCL</td>";
        $html .= "<td>" . sprintf("%+0.2f/Collision/1Mm", $acswuiConfig->DriverRanking['SF']['CCL']) . "</td>";
        $html .= "<td>" . _("Safety deduction points for crashing with another car at low speed difference") . "<br><small>Collision Car Low</small></td>";
        $html .= "</tr>";

        // DEF
        $html .= "<tr>";
        $html .= "<td rowspan=\"2\">DEF</td>";
        $html .= "<td>VHL</td>";
        $html .= "<td>" . sprintf("%dkm/h", $acswuiConfig->DriverRanking['DEF']['VHL']) . "</td>";
        $html .= "<td>" . _("Threshold velocity between high and low speed") . "<br><small>Velocity High Low</small></td>";
        $html .= "</tr>";

        $html .= "<tr>";
        $html .= "<td>LT</td>";
        $html .= "<td>" . sprintf("%dd", $acswuiConfig->DriverRanking['DEF']['LT']) . "</td>";
        $html .= "<td>" . _("Lease time for calculating the driver ranking.") . "<br><small>Lease Time</small></td>";
        $html .= "</tr>";

        $html .= "<table>";



        return $html;
    }
}

?>
