<?php

// helper variable to calculate rankings
$__DriverRankingArray__ = array();



/**
 * Cached wrapper to database DriverRanking table element
 */
class DriverRanking {
    private $IsNewItem = FALSE;
    private $Id = NULL;
    private $User = NULL;
    private $Timestamp = NULL;
    private $Characteristics = NULL;

    // Amount of days in the past where the ranking is determined
    const ScanDays = 30;

    // The velocity to which collision speeds are normalized to
    const CollisionNormSpeed = 100;


    /**
     * Create a DriverRanking object
     * When the $id parameter is 0, it is assumed that a new object wants to be created.
     * @param $id The Id of the database table row (or 0 for a new object)
     * @param $u The according User object (only relevant when $id=0 / new object)
     */
    public function __construct(int $id, User $u=NULL) {
        if ($id !== 0) {
            $this->Id = $id;

        } else {
            $this->IsNewItem = TRUE;
            $this->Timestamp = new DateTimeImmutable();
            $this->Characteristics = DriverRanking::initCharacteristics();
            $this->User = $u;
        }
    }


    /**
     * Increae a characteristic by a given value
     * This function works only for new rankings (which are not already in database)
     * E.g. addValue("XP", "R", 1.2);
     * @param $group The name of the characteristic grouop (XP, SX, SF)
     * @param $char The requested characteristic (R, Q, CT, CC)
     * @param $value The value that shall be added to an existing characteristic
     */
    public function addValue(string $group, string $char, float $value) {
        global $acswuiLog;

        if ($this->IsNewItem !== TRUE) {
            $acswuiLog->logError("Not allowed to set user on existing item!");
            return;
        }

        $this->Characteristics[$group][$char] += $value;
    }



    /**
     * Calculating driver rankings on current data.
     * This function does take create DriverRanking obecjts from the DriverRanking database table.
     * This function creates new objects based on current drive data.
     * The returned objects are sorted by DriverRanking->getScore()
     * @return An array of DriverRanking objects
     */
    public static function calculateRanks() {
        global $acswuiDatabase;
        global $acswuiConfig;
        global $__DriverRankingArray__;

        $__DriverRankingArray__ = array();

        /**
        * Extend a characteristic value of a DriverRanking object
        * @param $user The according user
        * @param $group The groupname of the characteristics (XP, SX, SF)
        * @param $characteristic The characteristic name (R, Q, P, CT, ...)
        * @param $add_value The Value that shalöl be added to the characteristic
        */
        function ranklistAdd($user, string $group, string $characteristic, float $add_value) {
            global $__DriverRankingArray__;

            // append DriverRanking object for a User if not existent
            if (!array_key_exists($user->id(), $__DriverRankingArray__)) {
                $dr = new DriverRanking(0, $user);
                $__DriverRankingArray__[$user->id()] = $dr;
            }

            // add characteristic value
            $drvrnk = $__DriverRankingArray__[$user->id()];
            $drvrnk->addValue($group, $characteristic, $add_value);
            $__DriverRankingArray__[$user->id()] = $drvrnk;
        }

        // scan laps of sessions
        $now = new DateTime();
        $then = $now->sub(new DateInterval("P" . DriverRanking::ScanDays . "D"));
        $timestamp = $then->format("Y-m-d");
        $query = "SELECT Id FROM Sessions WHERE Timestamp >= '$timestamp'";
        foreach ($acswuiDatabase->fetch_raw_select($query) as $row) {
            $session = new Session($row['Id']);

            // collisions
            foreach ($session->collisions() as $cll) {

                if ($cll->secondary()) continue;

                // normalize speed to 100km/h
                $norm_speed = $cll->speed() / DriverRanking::CollisionNormSpeed;

                // CollisionCar
                if ($cll->type() == CollisionType::Car) {
                    ranklistAdd($cll->user(), "SF", "CC", $norm_speed);
                } else if ($cll->type() == CollisionType::Env) {
                    ranklistAdd($cll->user(), "SF", "CE", $norm_speed);
                } else {
                    $acswuiLog->logError("Unknown Collision type!");
                }
            }


            // laps (driven length)
            foreach ($session->drivenLaps() as $lap) {

                // store driven length
                if ($session->type() == 1) {
                    ranklistAdd($lap->user(), "XP", "P", $session->track()->length());
                } else if ($session->type() == 2) {
                    ranklistAdd($lap->user(), "XP", "Q", $session->track()->length());
                } else if ($session->type() == 3) {
                    ranklistAdd($lap->user(), "XP", "R", $session->track()->length());
                }

                // cuts
                ranklistAdd($lap->user(), "SF", "CT", $lap->cuts());
            }


            // results (race/qualifying position)
            foreach ($session->results() as $rslt) {

                // ahead position
                $ahead_position = count($session->results()) - $rslt->position();
                if ($session->type() == 3)
                    ranklistAdd($rslt->user(), "SX", "R", $ahead_position);
                else if ($session->type() == 2)
                    ranklistAdd($rslt->user(), "SX", "Q", $ahead_position);
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

                    // add best race time
                    ranklistAdd($results[0]->user(), "SX", "RT", 1);
                }
            }
        }


        // scan car class records
        $file_path = $acswuiConfig->AcsContent . "/stats_carclass_records.json";
        $class_records = json_decode(file_get_contents($file_path), TRUE);
        foreach ($class_records as $car_class_id=>$records) {
            foreach ($records as $track_id=>$best_lap_ids) {
                for ($pos=0; $pos<count($best_lap_ids); ++$pos) {

                    // calculate ahead positions
                    $lap = new Lap($best_lap_ids[$pos]);
                    $ahead_position = count($best_lap_ids) - $pos - 1;
                    ranklistAdd($lap->user(), "SX", "BT", $ahead_position);
                }
            }
        }


        // return list
        $retlist = array();
        foreach ($__DriverRankingArray__ as $user_id=>$drvrnk) {
            $retlist[] = $drvrnk;
        }
        usort($retlist, "DriverRanking::compareScore");
        return $retlist;
    }

    /**
     * Compare two DriverRanking objects according to their score.
     * This can be used with array sort functions: usort($driver_ranking_list, DriverRanking::compareScore)
     * @param $dr1 A DriverRanking object
     * @param $dr2 A DriverRanking object
     * @return 1 if score of $dr1 is less than $dr2, 0 if the score is equal and -1 if score of $dr2 is less than $dr1
     */
    public static function compareScore($dr1, $dr2) {
        if ($dr1->getScore() < $dr2->getScore()) return 1;
        else if ($dr1->getScore() > $dr2->getScore()) return -1;
        else return 0;
    }


    public function getScore($group=NULL, $value=NULL) {
        global $acswuiConfig;

        //$Characteristics
        if ($this->Characteristics === NULL) $this->updateFromDb();
        $driven = $this->Characteristics['XP']['R'] + $this->Characteristics['XP']['Q'] + $this->Characteristics['XP']['P'];

        if ($group == NULL) {
            $result  = $this->getScore("XP", "R");
            $result += $this->getScore("XP", "Q");
            $result += $this->getScore("XP", "P");
            $result += $this->getScore("SX", "R");
            $result += $this->getScore("SX", "Q");
            $result += $this->getScore("SX", "RT");
            $result += $this->getScore("SX", "BT");
            $result += $this->getScore("SF", "CT");
            $result += $this->getScore("SF", "CE");
            $result += $this->getScore("SF", "CC");

        } else if ($group == "XP") {

            if ($value == NULL) {
                $result  = $this->getScore($group, 'R');
                $result += $this->getScore($group, 'Q');
                $result += $this->getScore($group, 'P');

            } else if ($value == "R") {
                $result = $acswuiConfig->DriverRanking['XP']['R'];
                $result *= $this->Characteristics['XP']['R'] * 1e-6;

            } else if ($value == "Q") {
                $result = $acswuiConfig->DriverRanking['XP']['Q'];
                $result *= $this->Characteristics['XP']['Q'] * 1e-6;

            } else if ($value == "P") {
                $result = $acswuiConfig->DriverRanking['XP']['P'];
                $result *= $this->Characteristics['XP']['P'] * 1e-6;
            }


        } else if ($group == "SX") {

            if ($value == NULL) {
                $result  = $this->getScore($group, 'R');
                $result += $this->getScore($group, 'Q');
                $result += $this->getScore($group, 'RT');
                $result += $this->getScore($group, 'BT');

            } else if ($value == "R") {
                $result = $acswuiConfig->DriverRanking['SX']['R'];
                $result *= $this->Characteristics['SX']['R'];

            } else if ($value == "Q") {
                $result = $acswuiConfig->DriverRanking['SX']['Q'];
                $result *= $this->Characteristics['SX']['Q'];

            } else if ($value == "RT") {
                $result = $acswuiConfig->DriverRanking['SX']['RT'];
                $result *= $this->Characteristics['SX']['RT'];

            } else if ($value == "BT") {
                $result = $acswuiConfig->DriverRanking['SX']['BT'];
                $result *= $this->Characteristics['SX']['BT'];
            }


        } else if ($group == "SF") {

            if ($value == NULL) {
                $result  = $this->getScore($group, 'CT');
                $result += $this->getScore($group, 'CE');
                $result += $this->getScore($group, 'CC');

            } else if ($value == "CT") {
                $result = $acswuiConfig->DriverRanking['SF']['CT'];
                $result *= $this->Characteristics['SF']['CT'];
                $result /= 1e-6 * $driven;

            } else if ($value == "CE") {
                $result = $acswuiConfig->DriverRanking['SF']['CE'];
                $result *= $this->Characteristics['SF']['CE'];
                $result /= 1e-6 * $driven;

            } else if ($value == "CC") {
                $result = $acswuiConfig->DriverRanking['SF']['CC'];
                $result *= $this->Characteristics['SF']['CC'];
                $result /= 1e-6 * $driven;
            }

        }

        return $result;
    }


    //! @return The database row Id
    public function id() {
        return $this->Id;
    }


    /**
     * Generate an array initialized with characteristic values with 0
     * @return An array with keys that represent characteristics
     */
    public static function initCharacteristics() {
        return array( "XP"=>["R"=>0, "Q"=>0, "P"=>0],
                      "SX"=>["R"=>0, "Q"=>0, "RT"=>0, "BT"=>0],
                      "SF"=>["CT"=>0, "CE"=>0, "CC"=>0]);
    }


    //! @return A List of the latest DriverRanking objects for each driver (ordered by score)
    public static function listLatest() {
        global $acswuiDatabase, $acswuiUser;

        $retlist = array();

        // get latest ranking for each driver
        foreach (User::listDrivers() as $u) {
            $user_id = $u->id();
            $query = "SELECT Id FROM DriverRanking WHERE User = '$user_id' ORDER BY Id DESC LIMIT 1";
            $res = $acswuiDatabase->fetch_raw_select($query);
            if (count($res) != 0) {
                $dr = new DriverRanking($res[0]['Id']);
                $retlist[] = $dr;
            }
        }

        // order by score
        usort($retlist, "DriverRanking::compareScore");

        return $retlist;
    }


    //! This function works only for new rankings (which are not already in database)
    public function save() {
        global $acswuiLog;
        global $acswuiDatabase;

        if ($this->IsNewItem !== TRUE) {
            $acswuiLog->logError("Not allowed to set user on existing item!");
            return;
        }

        // db fields
        $columns = array();
        $columns['User'] = $this->User->id();
        $columns['Timestamp'] = $this->Timestamp->format("Y-m-d H:i:s");
        foreach ($this->Characteristics as $group=>$values) {
            foreach ($values as $value=>$v) {
                $columns[$group . "_" . $value] = $v;
            }
        }

        // save
        $this->Id = $acswuiDatabase->insert_row("DriverRanking", $columns);
        $this->IsNewItem = FALSE;
    }


    public function timestamp() {
        if ($this->Timestamp === NULL) $this->updateFromDb();
        return $this->Timestamp;
    }


    public function user() {
        if ($this->User === NULL) $this->updateFromDb();
        return $this->User;
    }


    //! load db entry
    private function updateFromDb() {
        global $acswuiDatabase;
        global $acswuiLog;

        // prepare target values
        $this->Characteristics = DriverRanking::initCharacteristics();

        // db columns
        $columns = array();
        $columns[] = 'User';
        $columns[] = 'Timestamp';
        foreach ($this->Characteristics as $group=>$values) {
            foreach ($values as $value=>$v) {
                $columns[] = $group . "_" . $value;
            }
        }

        // request from db
        $res = $acswuiDatabase->fetch_2d_array("DriverRanking", $columns, ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find DriverRanking.Id=" . $this->Id);
            return;
        }

        // gather data
        $this->User = new User($res[0]['User']);
        $this->Timestamp = new DateTime($res[0]['Timestamp']);
        foreach ($this->Characteristics as $group=>$values) {
            foreach ($values as $value=>$v) {
                $colname = $group . "_" . $value;
                $this->Characteristics[$group][$value] = $res[0][$colname];
            }
        }
    }
}

?>
