<?php

namespace DbEntry;


/**
 * Cached wrapper to databse Sessions table element
 */
class Session extends DbEntry {

    //! Definition of race session type
    const TypeRace = 3;

    //! Definition of qualifying session type
    const TypeQualifying = 2;

    //! Definition of practice session type
    const TypePractice = 1;

    //! Definition of practice session type
    const TypeBooking = 0;

    //! Invalid Session type
    const TypeInvalid = -1;

    private $Collisions = NULL;
    private $DrivenLength = NULL;
    private $Laps = NULL;
    private $Users = NULL;


    /**
     * @param $id Database table id
     */
    protected function __construct($id) {
        parent::__construct("Sessions", $id);
    }


    //! @return A list of Collision objects from this session, ordered by timestamp
    public function collisions() {
        if ($this->Collisions === NULL) {
            $this->Collisions = array();

            // CollisionEnv
            foreach (\Core\Database::fetch("CollisionEnv", ["Id"], ["Session"=>$this->id()]) as $row) {
                $this->Collisions[] = CollisionEnv::fromId($row['Id']);
            }
            foreach (\Core\Database::fetch("CollisionCar", ["Id"], ["Session"=>$this->id()]) as $row) {
                $this->Collisions[] = CollisionCar::fromId($row['Id']);
            }

            usort($this->Collisions, "\DbEntry\Collision::compareTimestamp");
        }
        return $this->Collisions;
    }


    /**
     * Will return '0' if the requested user did not drive any lap in this session
     * @param $user The requested User
     * @return The distance a driver passed during this session [m]
     */
    public function drivenDistance(User $user) {
        if ($this->DrivenLength == NULL) {
            $this->DrivenLength = array();
            $tracklength = $this->track()->length();
            foreach ($this->users() as $user) {
                $distance = $tracklength * count($this->laps($user));
                $this->DrivenLength[$user->id()] = $distance;
            }
        }
        return (array_key_exists($user->id(), $this->DrivenLength)) ? $this->DrivenLength[$user->id()] : 0;
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) {
        return parent::getCachedObject("Sessions", "Session", $id);
    }


    //! @return The youngest Session object
    public static function latestSession(bool $show_races=TRUE,
                                         bool $show_qualifying=TRUE,
                                         bool $show_practice=TRUE) {

        // create query
        $where = array();
        if ($show_races == FALSE) $where[] = "Type != " . Session::TypeRace;
        if ($show_qualifying == FALSE) $where[] = "Type != " . Session::TypeQualifying;
        if ($show_practice == FALSE) $where[] = "Type != " . Session::TypePractice;
        $query = "SELECT Id FROM Sessions ";
        if (count($where) > 0) $query .= "WHERE " . (implode(" AND ", $where));
        $query .= " ORDER BY Id DESC LIMIT 1";

        $res = \Core\Database::fetchRaw($query);
        if (count($res) > 0) {
            return Session::fromId($res[0]['Id']);
        } else {
            return NULL;
        }
    }


    //! @return The Lap object with the best, valid laptime (can be NULL)
    public function lapBest() {
        $query = "SELECT Id from Laps WHERE Session = " . $this->id() . " AND Cuts = 0 ORDER BY Laptime ASC LIMIT 1;";
        $res = \Core\Database::fetchRaw($query);
        return (count($res) == 1) ? Lap::fromId($res[0]['Id']) : NULL;
    }


    /**
     * @param $user If not NULL, only the laps of this user are returned
     * @param $valid_only If TRUE (default FALSE) only laps without cuts are listed
     * @return An array of Lap objects
     */
    public function laps(User $user = NULL, bool $valid_only=FALSE) {
        if ($user !== NULL) {
            $laps = array();
            $query = "SELECT Id from Laps WHERE Session = " . $this->id() . " AND User = " . $user->id();
            if ($valid_only) $query .= " AND Cuts = 0";
            $query .= " ORDER BY Id ASC";
            $res = \Core\Database::fetchRaw($query);
            foreach ($res as $row) {
                $laps[] = Lap::fromId($row['Id']);
            }
            return $laps;

        } if ($this->Laps === NULL) {
            $this->Laps = array();
            $query = "SELECT Id from Laps WHERE Session = " . $this->id();
            if ($valid_only) $query .= " AND Cuts = 0";
            $query .= " ORDER BY Id ASC";
            $res = \Core\Database::fetchRaw($query);
            foreach ($res as $row) {
                $this->Laps[] = Lap::fromId($row['Id']);
            }
        }
        return $this->Laps;
    }


    //! @return An array with Session objects
    public static function listSessions(bool $show_races=TRUE,
                                        bool $show_qualifying=TRUE,
                                        bool $show_practice=TRUE) {
        $sessions = array();

        // create query
        $where = array();
        if ($show_races == FALSE) $where[] = "Type != " . Session::TypeRace;
        if ($show_qualifying == FALSE) $where[] = "Type != " . Session::TypeQualifying;
        if ($show_practice == FALSE) $where[] = "Type != " . Session::TypePractice;
        $query = "SELECT Id FROM Sessions ";
        if (count($where) > 0) $query .= "WHERE " . (implode(" AND ", $where));
        $query .= " ORDER BY Id DESC";

        // execute query
        $res = \Core\Database::fetchRaw($query);
        foreach ($res as $row) {
            $sessions[] = Session::fromId($row['Id']);
        }

        return $sessions;
    }


    public function name() {
        return $this->loadColumn("Name");
    }

    //! @return A DateTime object in server Timezone
    public function timestamp() {
        $t = $this->loadColumn("Timestamp");
        return \Core\Database::timestamp2DateTime($t);
    }


    //! @return The Track object of this session
    public function track() {
        return Track::fromId($this->loadColumn("Track"));
    }


    //! @return either Session:TypeRace, Session::TypeQualifying or Session::TypePractice
    public function type() {
        return (int) $this->loadColumn("Type");
    }

    //! @return 'P', 'Q' or 'R', depending on the session type
    public function typeChar() {
        return Session::type2Char($this->type());
    }


    /**
     * Converty any Session Type identifier to a indetifier char
     * @return A char
     */
    public static function type2Char($type) {
        switch ($type) {
            case Session::TypeRace:
                return "R";
                break;
            case Session::TypeQualifying:
                return "Q";
                break;
            case Session::TypePractice:
                return "P";
                break;
            case Session::TypeBooking:
                return "B";
                break;
            case Session::TypeInvalid:
                return "I";
                break;
            default:
                \Core\Log::warning("Unknown session type '$type'!");
                return "?";
                break;
        }
    }


    //! @return A list of User objects of all users that have driven a lap in this session
    public function users() {
        if ($this->Users === NULL) {
            $this->Users = array();
            $res = \Core\Database::fetchRaw("SELECT DISTINCT User FROM `Laps` WHERE Session = " . $this->id());
            foreach ($res as $row) {
                $this->Users[] = User::fromId($row['User']);
            }
        }
        return $this->Users;
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
//     public function dynamicPositions(User $user) {
//         global $acswuiLog;
//
//         // update cache
//         if ($this->DynamicPositions === NULL) {
//
//
//             ///////////////
//             // Initialize
//
//             // initialize cache with all drivers
//             $this->DynamicPositions = array();
//             foreach ($this->drivers() as $d) {
//                 $uid = $d->id();
//                 $this->DynamicPositions[$uid] = array();
//                 $this->DynamicPositions[$uid][] = 0;
//             }
//
//             //////////////////
//             // race sessions
//
//             // determine positions based on laps
//             if ($this->type() == 3) {
//
//                 // get positions of qualifying session
//                 $predec = $this->predecessor();
//                 if ($predec !== NULL && $predec->type() == 2) {
//                     $predec_results = $predec->results();
//                     usort($predec_results, "SessionResult::comparePosition");
//                     foreach ($predec_results as $rslt) {
//                         $uid = $rslt->user()->id();
//                         if (!array_key_exists($uid, $this->DynamicPositions)) {
//                             $this->DynamicPositions[$uid] = array();
//                             $this->DynamicPositions[$uid][] = 0;
//                         }
//                         $this->DynamicPositions[$uid][0] = $rslt->position();
//                     }
//                 }
//
//                 // get position of race laps
//                 $lap_positions = array();
//                 $driver_laps_amount = array(); // stores the amount of driven laps for a certain user
//                 foreach (array_reverse($this->drivenLaps()) as $lap) {
//                     $uid = $lap->user()->id();
//
//                     // find current lap number of user
//                     if (!array_key_exists($uid, $driver_laps_amount))
//                         $driver_laps_amount[$uid] = 0;
//                     else
//                         $driver_laps_amount[$uid] += 1;
//                     $user_lap_nr = $driver_laps_amount[$uid];
//
//                     // grow lap position information
//                     while ($user_lap_nr >= count($lap_positions))
//                         $lap_positions[] = array();
//
//                     // put user into lap position array
//                     $lap_positions[$user_lap_nr][] = $uid;
//                 }
//
//                 // extrace race positions
//                 foreach ($lap_positions as $lap_nr=>$pos_array) {
//                     foreach (array_keys($this->DynamicPositions) as $uid) {
//                         $pos = array_search($uid, $pos_array);
//                         if ($pos === FALSE) $pos = 0;
//                         else $pos += 1;
//                         $this->DynamicPositions[$uid][] = $pos;
//                     }
//                 }
//
//
//             //////////////////////
//             // non-race sessions
//
//             // determine positions based on minutes
//             } else {
//                 $positions = array(); // array of SessionBestTime objects
//                 $driver_besttimes = array(); // stores current best laptime for each driver
//                 $current_positions_uid = array(); // stores the current user-id ordered by their best time
//
//                 foreach (array_reverse($this->drivenLaps()) as $lap) {
//                     if ($lap->cuts() > 0) continue;
//                     $uid = $lap->user()->id();
//
//                     $user_minute = $lap->sessionMinutes();
//
//                     // grow lap position information
//                     while ($user_minute > count($positions))
//                         $positions[] = $current_positions_uid;
//
//                     // update best times
//                     if (!array_key_exists($uid, $driver_besttimes)
//                         || $lap->laptime() < $driver_besttimes[$uid]) {
//
//                         // save best times
//                         $driver_besttimes[$uid] = $lap->laptime();
//
//                         // determine current positions
//                         $current_positions = array();
//                         foreach ($driver_besttimes as $uid_bt=>$bt) {
//                             $sbt = new SessionBestTime();
//                             $sbt->UserId = $uid_bt;
//                             $sbt->BestLaptime = $bt;
//                             $current_positions[] = $sbt;
//                         }
//                         usort($current_positions, "SessionBestTime::compare");
//
//                         // translate to user-id array
//                         $current_positions_uid = array();
//                         foreach ($current_positions as $sbt) {
//                             $current_positions_uid[] = $sbt->UserId;
//                         }
//                     }
//
//                     // put user into lap position array
//                     $positions[$user_minute] = $current_positions_uid;
//                 }
//
//                 // extract session positions
//                 foreach ($positions as $minute=>$pos_array) {
//                     foreach (array_keys($this->DynamicPositions) as $uid) {
//                         $pos = array_search($uid, $pos_array);
//                         if ($pos === FALSE) $pos = 0;
//                         else $pos += 1;
//                         $this->DynamicPositions[$uid][] = $pos;
//                     }
//                 }
//
//             }
//
//
//             ///////////////////////
//             // Add Session Result
//
//             $results = $this->results();
//             usort($results, "SessionResult::comparePosition");
//             foreach (array_keys($this->DynamicPositions) as $uid) {
//                 $pos = 0;
//                 foreach ($results as $rslt) {
//                     if ($rslt->user()->id() == $uid) {
//                         $pos = $rslt->position();
//                         break;
//                     }
//                 }
//                 $this->DynamicPositions[$uid][] = $pos;
//             }
//         }
//
//         // return result
//         if (!array_key_exists($user->id(), $this->DynamicPositions)) {
//             $acswuiLog->logError("No entry for user->id()=" . $user->id() . "!");
//         }
//         return $this->DynamicPositions[$user->id()];
//     }
}

?>
