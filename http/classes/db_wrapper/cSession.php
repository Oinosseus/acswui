<?php

//! Internal helper class
class SessionBestTime {
    public $UserId = NULL;
    public $BestLaptime = NULL;

    public static function compare($sbt1, $sbt2) {
        if ($sbt1->BestLaptime > $sbt2->BestLaptime) return 1;
        else if ($sbt1->BestLaptime < $sbt2->BestLaptime) return -1;
        else return 0;
    }
}



/**
 * Cached wrapper to databse Sessions table element
 */
class Session {
    private $Id = NULL;
    private $ProtocolVersion = NULL;
    private $SessionIndex = NULL;
    private $CurrentSessionIndex = NULL;
    private $SessionCount = NULL;
    private $ServerName = NULL;
    private $Track = NULL;
    private $Name = NULL;
    private $Type = NULL;
    private $Time = NULL;
    private $Laps = NULL;
    private $WaitTime = NULL;
    private $TempAmb = NULL;
    private $TempRoad = NULL;
    private $WheatherGraphics = NULL;
    private $Elapsed = NULL;
    private $Timestamp = NULL;
    private $Predecessor = NULL;

    private $DrivenLaps = NULL;
    private $FirstDrivenLap = NULL;
    private $Drivers = NULL;
    private $Collisions = NULL;
    private $Results = NULL;
    private $DynamicPositions = NULL;

    /**
     * @param $id Database table id
     */
    public function __construct($id) {
        $this->Id = $id;
    }

    public function __toString() {
        return "Session(Id=" . $this->Id . ")";
    }

    //! @return The database table id
    public function id() {
        return $this->Id;
    }

    //! @return A list of CollisionEnv and CollisionCar objects from this session
    public function collisions() {
        global $acswuiDatabase;

        // update cache
        if ($this->Collisions === NULL) {
            $this->Collisions = array();

            // ENV collisions
            $res = $acswuiDatabase->fetch_2d_array("CollisionEnv", ["Id"], ["Session"=>$this->Id]);
            foreach ($res as $row) {
                $cll = new CollisionEnv($row['Id'], $this);
                $this->Collisions[] = $cll;
            }

            // Car collisions
            $res = $acswuiDatabase->fetch_2d_array("CollisionCar", ["Id"], ["Session"=>$this->Id]);
            foreach ($res as $row) {
                $cll = new CollisionCar($row['Id'], $this);
                $this->Collisions[] = $cll;
            }
        }

        return $this->Collisions;
    }

    //! @todo Write a description
    public function currentSessionIndex() {
        if ($this->CurrentSessionIndex === NULL) $this->updateFromDb();
        return $this->CurrentSessionIndex;
    }

    //! @return A list of Lap objects, driven in this session (descending by lap ID)
    public function drivenLaps() {
        if ($this->DrivenLaps === NULL) $this->updateDrivenLaps();
         return $this->DrivenLaps;
    }

    //! @return A list of User objects that drove laps in this session
    public function drivers() {
        if ($this->Drivers === NULL) $this->updateDrivenLaps();
         return $this->Drivers;
    }


    /**
     * Calculates an array for the requested driver with his session positions.
     * Every entry in the array represents the curent position.
     *
     * For race session the array element [0] represents the start position (qualifying result).
     * Every following element represents his position after completing a lap.
     * The second last element represents the position in the final lap.
     *
     * For Practice and Qualifying, the elements represent session minutes (time).
     * The first element contains the position from the previous session.
     *
     * An element containing '0' as position indicates,
     * that the driver did not complete this lap (in racing) or,
     * that the driver did not drive at this minute (for practice and qualifying).
     * The last element represents the session result.
     */
    public function dynamicPositions(User $user) {
        global $acswuiLog;

        // update cache
        if ($this->DynamicPositions === NULL) {


            ///////////////
            // Initialize

            // initialize cache with all drivers
            $this->DynamicPositions = array();
            foreach ($this->drivers() as $d) {
                $uid = $d->id();
                $this->DynamicPositions[$uid] = array();
                $this->DynamicPositions[$uid][] = 0;
            }

            //////////////////
            // race sessions

            // determine positions based on laps
            if ($this->type() == 3) {

                // get positions of predecessor session
                $predec = $this->predecessor();
                $predec_results = $predec->results();
                usort($predec_results, "SessionResult::comparePosition");
                foreach ($predec_results as $rslt) {
                    $uid = $rslt->user()->id();
                    if (!array_key_exists($uid, $this->DynamicPositions)) {
                        $this->DynamicPositions[$uid] = array();
                        $this->DynamicPositions[$uid][] = 0;
                    }
                    $this->DynamicPositions[$uid][0] = $rslt->position();
                }

                // get position of race laps
                $lap_positions = array();
                $driver_laps_amount = array(); // stores the amount of driven laps for a certain user
                foreach (array_reverse($this->drivenLaps()) as $lap) {
                    $uid = $lap->user()->id();

                    // find current lap number of user
                    if (!array_key_exists($uid, $driver_laps_amount))
                        $driver_laps_amount[$uid] = 0;
                    else
                        $driver_laps_amount[$uid] += 1;
                    $user_lap_nr = $driver_laps_amount[$uid];

                    // grow lap position information
                    while ($user_lap_nr >= count($lap_positions))
                        $lap_positions[] = array();

                    // put user into lap position array
                    $lap_positions[$user_lap_nr][] = $uid;
                }

                // extrace race positions
                foreach ($lap_positions as $lap_nr=>$pos_array) {
                    foreach (array_keys($this->DynamicPositions) as $uid) {
                        $pos = array_search($uid, $pos_array);
                        if ($pos === FALSE) $pos = 0;
                        else $pos += 1;
                        $this->DynamicPositions[$uid][] = $pos;
                    }
                }


            //////////////////////
            // non-race sessions

            // determine positions based on minutes
            } else {
                $positions = array(); // array of SessionBestTime objects
                $driver_besttimes = array(); // stores current best laptime for each driver
                $current_positions_uid = array(); // stores the current user-id ordered by their best time

                foreach (array_reverse($this->drivenLaps()) as $lap) {
                    if ($lap->cuts() > 0) continue;
                    $uid = $lap->user()->id();

                    $user_minute = $lap->sessionMinutes();

                    // grow lap position information
                    while ($user_minute > count($positions))
                        $positions[] = $current_positions_uid;

                    // update best times
                    if (!array_key_exists($uid, $driver_besttimes)
                        || $lap->laptime() < $driver_besttimes[$uid]) {

                        // save best times
                        $driver_besttimes[$uid] = $lap->laptime();

                        // determine current positions
                        $current_positions = array();
                        foreach ($driver_besttimes as $uid_bt=>$bt) {
                            $sbt = new SessionBestTime();
                            $sbt->UserId = $uid_bt;
                            $sbt->BestLaptime = $bt;
                            $current_positions[] = $sbt;
                        }
                        usort($current_positions, "SessionBestTime::compare");

                        // translate to user-id array
                        $current_positions_uid = array();
                        foreach ($current_positions as $sbt) {
                            $current_positions_uid[] = $sbt->UserId;
                        }
                    }

                    // put user into lap position array
                    $positions[$user_minute] = $current_positions_uid;
                }

                // extract session positions
                foreach ($positions as $minute=>$pos_array) {
                    foreach (array_keys($this->DynamicPositions) as $uid) {
                        $pos = array_search($uid, $pos_array);
                        if ($pos === FALSE) $pos = 0;
                        else $pos += 1;
                        $this->DynamicPositions[$uid][] = $pos;
                    }
                }

            }


            ///////////////////////
            // Add Session Result

            $results = $this->results();
            usort($results, "SessionResult::comparePosition");
            foreach (array_keys($this->DynamicPositions) as $uid) {
                $pos = 0;
                foreach ($results as $rslt) {
                    if ($rslt->user()->id() == $uid) {
                        $pos = $rslt->position();
                        break;
                    }
                }
                $this->DynamicPositions[$uid][] = $pos;
            }
        }

        // return result
        if (!array_key_exists($user->id(), $this->DynamicPositions)) {
            $acswuiLog->logError("No entry for user->id()=" . $user->id() . "!");
        }
        return $this->DynamicPositions[$user->id()];
    }

    //! @todo Write a description
    public function elapsed() {
        if ($this->Elapsed === NULL) $this->updateFromDb();
        return $this->Elapsed;
    }

    /**
     * This is intended to be used to determine session relative lap numbers:
     * $lap_number = $lap->id() - $session->firstDrivenLap()->id() + 1;
     * @return The Lap object of the first driven lap in this session
     */
    public function firstDrivenLap() {
        if ($this->FirstDrivenLap === NULL) $this->updateDrivenLaps();
         return $this->FirstDrivenLap;
    }

    //! @todo Write a description
    public function laps() {
        if ($this->Laps === NULL) $this->updateFromDb();
        return $this->Laps;
    }

    //! @todo Write a description
    public function name() {
        if ($this->Name === NULL) $this->updateFromDb();
        return $this->Name;
    }


    //! @return The predecessing session
    public function predecessor() {
        if ($this->Predecessor === NULL) $this->updateFromDb();
        return $this->Predecessor;
    }


    //! @todo Write a description
    public function protocolVersion() {
        if ($this->ProtocolVersion === NULL) $this->updateFromDb();
        return $this->ProtocolVersion;
    }


    //! @return A list of SessionResult objects from this session (If not available an empty list is returned)
    public function results() {
        global $acswuiDatabase;

        // update cache
        if ($this->Results === NULL) {
            $this->Results = array();

            $res = $acswuiDatabase->fetch_2d_array("SessionResults", ["Id", "TotalTime"], ["Session"=>$this->Id]);
            foreach ($res as $row) {
                if ($row['TotalTime'] == 0) continue;
                $cll = new SessionResult($row['Id'], $this);
                $this->Results[] = $cll;
            }
        }

        return $this->Results;
    }


    //! @todo Write a description
    public function sessionIndex() {
        if ($this->SessionIndex === NULL) $this->updateFromDb();
        return $this->SessionIndex;
    }

    //! @todo Write a description
    public function sessionCount() {
        if ($this->SessionCount === NULL) $this->updateFromDb();
        return $this->SessionCount;
    }

    //! @todo Write a description
    public function serverName() {
        if ($this->ServerName=== NULL) $this->updateFromDb();
        return $this->ServerName;
    }

    //! @return A Track object
    public function track() {
        if ($this->Track === NULL) $this->updateFromDb();
        return $this->Track;
    }

    //! @todo Write a description
    public function tempAmb() {
        if ($this->TempAmb === NULL) $this->updateFromDb();
        return $this->TempAmb;
    }

    //! @todo Write a description
    public function tempRoad() {
        if ($this->TempRoad === NULL) $this->updateFromDb();
        return $this->TempRoad;
    }

    //! @todo Write a description
    public function time() {
        if ($this->Time === NULL) $this->updateFromDb();
        return $this->Time;
    }


    //! @return The timestamp of session begin
    public function timestamp() {
        if ($this->Timestamp === NULL) $this->updateFromDb();
        return $this->Timestamp;
    }


    //! @todo Write a description
    public function type() {
        if ($this->Type === NULL) $this->updateFromDb();
        return $this->Type;
    }


    private function updateDrivenLaps() {
        global $acswuiDatabase;
        $this->DrivenLaps = array();
        $this->Drivers = array();
        $lap = NULL;
        foreach ($acswuiDatabase->fetch_2d_array("Laps", ['Id'], ['Session'=>$this->Id], 'Id', FALSE) as $lap) {
            $lap = new Lap($lap['Id']);
            $this->DrivenLaps[] = $lap;

            $driver_already_listed = FALSE;
            foreach ($this->Drivers as $d) {
                if ($d->id() == $lap->user()->id()) {
                    $driver_already_listed = TRUE;
                    break;
                }
            }
            if ($driver_already_listed === FALSE) $this->Drivers[] = $lap->user();

        }
        $this->FirstDrivenLap = $lap;
    }


    private function updateFromDb() {
        global $acswuiDatabase, $acswuiLog;

        // request from db
        $columns = array();
        $columns[] = 'ProtocolVersion';
        $columns[] = 'SessionIndex';
        $columns[] = 'CurrentSessionIndex';
        $columns[] = 'SessionCount';
        $columns[] = 'ServerName';
        $columns[] = 'Track';
        $columns[] = 'Name';
        $columns[] = 'Type';
        $columns[] = 'Time';
        $columns[] = 'Laps';
        $columns[] = 'WaitTime';
        $columns[] = 'TempAmb';
        $columns[] = 'TempRoad';
        $columns[] = 'WheatherGraphics';
        $columns[] = 'Elapsed';
        $columns[] = 'Timestamp';
        $columns[] = 'Predecessor';

        $res = $acswuiDatabase->fetch_2d_array("Sessions", $columns, ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find Sessions.Id=" . $this->Id);
            return;
        }

        $this->ProtocolVersion = $res[0]['ProtocolVersion'];
        $this->SessionIndex = $res[0]['SessionIndex'];
        $this->CurrentSessionIndex = $res[0]['CurrentSessionIndex'];
        $this->SessionCount = $res[0]['SessionCount'];
        $this->ServerName = $res[0]['ServerName'];
        $this->Track = new Track($res[0]['Track']);
        $this->Name = $res[0]['Name'];
        $this->Type = $res[0]['Type'];
        $this->Time = $res[0]['Time'];
        $this->Laps = $res[0]['Laps'];
        $this->WaitTime = $res[0]['WaitTime'];
        $this->TempAmb = $res[0]['TempAmb'];
        $this->TempRoad = $res[0]['TempRoad'];
        $this->WheatherGraphics = $res[0]['WheatherGraphics'];
        $this->Elapsed = $res[0]['Elapsed'];
        $this->Timestamp = new DateTime($res[0]['Timestamp']);
        $this->Predecessor = new Session($res[0]['Predecessor']);
    }


    //! @todo Write a description
    public function waitTime() {
        if ($this->WaitTime === NULL) $this->updateFromDb();
        return $this->WaitTime;
    }

    //! @todo Write a description
    public function wheatherGraphics() {
        if ($this->WheatherGraphics === NULL) $this->updateFromDb();
        return $this->WheatherGraphics;
    }
}

?>
