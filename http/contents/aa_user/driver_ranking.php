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
    public $BestTimesAheadPositions = 0;
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
                $result *= 0;

            } else if ($value == "Q") {
                $result = $acswuiConfig->DriverRanking['SX']['Q'];
                $result *= 0;

            } else if ($value == "RT") {
                $result = $acswuiConfig->DriverRanking['SX']['RT'];
                $result *= 0;

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

            // laps
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


        // --------------------------------------------------------------------
        //                             Ranking Table
        // --------------------------------------------------------------------

        // table head
        $html .= "<table>";
        $html .= "<tr>";
        $html .= "<th rowspan=\"2\">" . _("Driver") . "</th>";
        $html .= "<th colspan=\"3\">XP</th>";
        $html .= "<th colspan=\"4\">SX</th>";
        $html .= "<th colspan=\"5\">SF</th>";
        $html .= "<th rowspan=\"2\">" . _("Score") . "</th>";
        $html .= "</tr>";

        $html .= "<tr>";
        $html .= "<th>R</th>";
        $html .= "<th>Q</th>";
        $html .= "<th>P</th>";
        $html .= "<th>R</th>";
        $html .= "<th>Q</th>";
        $html .= "<th>RT</th>";
        $html .= "<th>BT</th>";
        $html .= "<th>CUT</th>";
        $html .= "<th>CEH</th>";
        $html .= "<th>CEL</th>";
        $html .= "<th>CCH</th>";
        $html .= "<th>CCL</th>";
        $html .= "</tr>";

        foreach ($driver_rank_list as $drv_rnk) {

            $html .= "<tr>";
            $html .= "<td>" . $drv_rnk->User->login() . "</td>";
            $html .= "<td>" . $drv_rnk->getScoreStr("XP", "R") . "</td>";
            $html .= "<td>" . $drv_rnk->getScoreStr("XP", "Q") . "</td>";
            $html .= "<td>" . $drv_rnk->getScoreStr("XP", "P") . "</td>";
            $html .= "<td>" . $drv_rnk->getScoreStr("SX", "R") . "</td>";
            $html .= "<td>" . $drv_rnk->getScoreStr("SX", "Q") . "</td>";
            $html .= "<td>" . $drv_rnk->getScoreStr("SX", "RT") . "</td>";
            $html .= "<td>" . $drv_rnk->getScoreStr("SX", "BT") . "</td>";
            $html .= "<td>" . $drv_rnk->getScoreStr("SF", "CUT") . "</td>";
            $html .= "<td>" . $drv_rnk->getScoreStr("SF", "CEH") . "</td>";
            $html .= "<td>" . $drv_rnk->getScoreStr("SF", "CEL") . "</td>";
            $html .= "<td>" . $drv_rnk->getScoreStr("SF", "CCH") . "</td>";
            $html .= "<td>" . $drv_rnk->getScoreStr("SF", "CCL") . "</td>";
            $html .= "<td><strong>" . $drv_rnk->getScoreStr() . "</strong></td>";
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
        $html .= "<td>" . _("Success points for having better track time for a certain car class (leading ahead another driver). This is independent of the lease time.") . "<br><small>Success Best Time</small></td>";
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
