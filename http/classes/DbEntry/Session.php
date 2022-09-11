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
    private $Results = NULL;
    private $DynamicPositions = NULL;
    private $Grip = NULL;
//     private static $LatestSession = NULL;
    private static $LastCompletedSession = NULL;
    private static $LastFinishedSession = NULL;


    /**
     * @param $id Database table id
     */
    protected function __construct($id) {
        parent::__construct("Sessions", $id);
    }


    //! @return The CarCLass object that was used (might be NULL if invalid
    public function carClass() {
        $id = (int) $this->loadColumn("CarClass");
        if ($id < 1) return NULL;
        return CarClass::fromId($id);
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


    //! @return An array of User objects
    public function drivers() {
        //! @todo Duplicates users()
        $drivers = array();
        $res = \Core\Database::fetchRaw("SELECT DISTINCT User FROM Laps WHERE Session = " . $this->id());
        foreach ($res as $row) $drivers[] = User::fromId($row['User']);
        return $drivers;
    }


    /**
     * Tries to find A certain Session
     * @param $max_id When not NULL, the Session with the Id lower or equal than this is returned
     * @return The requested Session (can be NULL)
     */
    public static function find(int $max_id = NULL) {
        $session = NULL;

        // find by max ID
        if ($max_id !== NULL) {

            $query = "SELECT Id FROM Sessions WHERE Id <= $max_id ORDER BY Id DESC LIMIT 1;";
            $res = \Core\Database::fetchRaw($query);
            if (count($res) > 0) {
                $session = Session::fromId($res[0]['Id']);
            }

        // no search specified
        } else {
            \Core\Logg::warning("No find criterias specified.");
        }

        return $session;
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * When $id is NULL or 0, NULL is returned
     * @return An object by its database Id
     */
    public static function fromId(int $id) {
        if ($id === NULL or $id == 0) return NULL;
        return parent::getCachedObject("Sessions", "Session", $id);
    }


    /**
     * The Session that is safely completed.
     * Which means there can be a newer Session also being finished,
     * but then also a newer Session exist which is currently running.
     *
     * Completed means no older Session which is active (running) exists.
     *
     * To prevent race conditions the last completed session must be older than one minutie.
     *
     * @return The Session object of the last completed Session (can be NULL)
     */
    public static function fromLastCompleted() {
        if (Session::$LastCompletedSession === NULL) {

            // find lowest Session-Id of any current running slot
            $lowest_online_session_id = NULL;
            for ($id = 1; $id <= \Core\Config::ServerSlotAmount; ++$id) {
                $slot = \Core\ServerSlot::fromId($id);
                if ($slot->online()) {
                    $session = $slot->currentSession();
                    if ($session) {
                        if ($lowest_online_session_id === NULL || $session->id() < $lowest_online_session_id)
                            $lowest_online_session_id = $session->id();
                    }
                }
            }

            // find Session-Id that is lower than current running session
            $minutes_ago = \Core\Database::timestamp((new \DateTime("now"))->sub(new \DateInterval("PT1M")));
            $query = "SELECT Id FROM Sessions WHERE Timestamp <= '$minutes_ago'";
            if ($lowest_online_session_id !== NULL)
                $query .= " AND Id < $lowest_online_session_id";
            $query .= "  ORDER BY Id DESC LIMIT 1;";
            $res = \Core\Database::fetchRaw($query);
            if (count($res) > 0) {
                $session = \DbEntry\Session::fromId($res[0]['Id']);
                Session::$LastCompletedSession = $session;
            }
        }

        return Session::$LastCompletedSession;
    }


    /**
     * The latest Session that is completed (not more online).
     * To prevent race conditions the last completed session must be older than one minute.
     *
     * @warning There can exist older Sessions which still running.
     * See fromLastCompleted() alo.
     *
     * @return The Session object of the newest offline Session (can be NULL)
     */
    public static function fromLastFinished() {

        if (Session::$LastFinishedSession === NULL) {

            // get list of session which are currently online
            $online_sessions = array();
            for ($i=1; $i <= \Core\Config::ServerSlotAmount; ++$i) {
                $slot = \Core\ServerSlot::fromId($i);
                $session = $slot->currentSession();
                if ($session !== NULL) {
                    $online_sessions[] = $session->id();
                }
            }

            // get latest Session Id as starting point
            $minutes_ago = \Core\Database::timestamp((new \DateTime("now"))->sub(new \DateInterval("PT1M")));
            $query = "SELECT Id FROM Sessions WHERE Timestamp <= '$minutes_ago' ORDER BY Id DESC LIMIT 1;";
            $res = \Core\Database::fetchRaw($query);
            if (count($res) > 0) {

                // recursivly find the highest session which is offline
                $session = Session::fromId($res[0]['Id']);
                while ($session !== NULL && in_array($session->id(), $online_sessions)) {
                    $session = Session::find($session->id() - 1);
                }
                Session::$LastFinishedSession = $session;
            }
        }

        return Session::$LastFinishedSession;
    }



//     /**
//      * @return The newest Session (can be NULL)
//      */
//     public static function fromLatest() {
//         if (Session::$LatestSession === NULL) {
//             $query = "SELECT Id FROM Sessions ORDER BY Id DESC LIMIT 1;";
//             $res = \Core\Database::fetchRaw($query);
//             if (count($res) > 0) {
//                 Session::$LatestSession = Session::fromId($res[0]['Id']);
//             }
//         }
//
//         return Session::$LatestSession;
//     }



    //! @return A two-element array with minimum and maximum grip at the session
    public function grip() {
        if ($this->Grip === NULL) {
            $this->Grip = array(NULL, NULL);
            foreach ($this->laps() as $lap) {
                if ($this->Grip[0] === NULL || $lap->grip() < $this->Grip[0]) $this->Grip[0] = $lap->grip();
                if ($this->Grip[1] === NULL || $lap->grip() > $this->Grip[1]) $this->Grip[1] = $lap->grip();
            }
        }

        return $this->Grip;
    }


    //! @return An html string with a link to the session overview
    public function htmlName() {
        $url = "index.php?HtmlContent=SessionOverview&SessionId=" . $this->id();
        $html = "<a href=\"$url\">" . $this->name() . "</a>";
        return $html;
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


    //! @return The session object which was directly followed by this session
    public function predecessor() {
        return Session::fromId($this->loadColumn("Predecessor"));
    }


    //! @return A list of SessionResult objects from this session
    public function results() {
        if ($this->Results === NULL) {
            $this->Results = SessionResult::listSessionResults($this);
        }

        return $this->Results;
    }


    //! @return The ServerPreset object that was used (might be NULL if invalid
    public function serverPreset() {
        $id = (int) $this->loadColumn("ServerPreset");
        if ($id < 1) return NULL;
        return ServerPreset::fromId($id);
    }


    //! @return The corresponding \Core\ServerSlot object (can be NULL if slots has been reduced after Session was driven)
    public function serverSlot() {
        $slot_id = $this->loadColumn("ServerSlot");
        $slot_obj = NULL;
        if ($slot_id <= \Core\Config::ServerSlotAmount) $slot_obj = \Core\ServerSlot::fromId($slot_id);
        return $slot_obj;
    }


    //! @return The session object which directly followed afterthis session
    public function successor() {
        $res = \Core\Database::fetchRaw("SELECT Id FROM Sessions WHERE Predecessor = " . $this->id());
        if (count($res) == 1) {
            return Session::fromId($res[0]['Id']);
        } else {
            return NULL;
        }
    }


    //! @return Ambient temperature
    public function tempAmb() {
        return $this->loadColumn("TempAmb");
    }


    //! @return Road temperature
    public function tempRoad() {
        return $this->loadColumn("TempRoad");
    }


    //! @return A DateTime object in server Timezone
    public function timestamp() {
        $t = $this->loadColumn("Timestamp");
        return new \DateTime($t);
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
        //! @todo Duplicates drivers()
        if ($this->Users === NULL) {
            $this->Users = array();
            $res = \Core\Database::fetchRaw("SELECT DISTINCT User FROM `Laps` WHERE Session = " . $this->id());
            foreach ($res as $row) {
                $this->Users[] = User::fromId($row['User']);
            }
        }
        return $this->Users;
    }


    //! @return The name of the weather graphics
    public function weather() {
        return $this->loadColumn("WheatherGraphics");
    }



    /**
     * Calculates an array with session positions.
     * For Practice and Qualifying, the elements represent session minutes (time).
     * For Race session, the elements represent lap numbers.
     *
     * return Array:
     * 'Info' -+- 'MaxPlace'
     *         +- 'MaxGap'
     * 'Data' -+- <UserId> - <LapNr/Minute> -+- 'Place'
     *                                       +- 'Gap'       [ms]
     *                                       +- 'Laptime'   [ms]
     */
    public function dynamicPositions() {

        // update cache
        if ($this->DynamicPositions === NULL) {

            ///////////////
            // Initialize

            // initialize cache
            $this->DynamicPositions = array();
            $this->DynamicPositions['Info'] = array();
            $this->DynamicPositions['Info']['MaxPlace'] = 0;
            $this->DynamicPositions['Info']['MaxGap'] = 0;
            $this->DynamicPositions['Data'] = array();
            foreach ($this->drivers() as $d) {
                $this->DynamicPositions['Data'][$d->id()] = array();
            }

            //////////////////
            // race sessions

            // determine positions based on laps
            if ($this->type() == Session::TypeRace) {

                // get position of race laps
                $leader_laptimes = array();      // laptimes of the race leader
                $driver_laps_amount = array();   // stores the amount of driven laps for a certain user
                $driver_laps_timesum = array();  // stores the sum of all laptimes per driver
                foreach ($this->laps() as $lap) {
                    $uid = $lap->user()->id();

                    // cumulate laptimes
                    if (!array_key_exists($uid, $driver_laps_timesum)) $driver_laps_timesum[$uid] = 0;
                    $driver_laps_timesum[$uid] += $lap->laptime();

                    // find current lap number of user
                    if (!array_key_exists($uid, $driver_laps_amount))
                        $driver_laps_amount[$uid] = 1;
                    else
                        $driver_laps_amount[$uid] += 1;
                    $user_lap_nr = $driver_laps_amount[$uid];

                    // grow data
                    $next_place = 1;
                    $leading_user_id = $uid;
                    $leader_lap_nr = $user_lap_nr;
                    foreach ($this->DynamicPositions['Data'] as $driver_id => $driver_data) {

                        // grow driver laps
                        if (!array_key_exists($user_lap_nr, $this->DynamicPositions['Data'][$driver_id])) {
                            $this->DynamicPositions['Data'][$driver_id][$user_lap_nr] = array();
                            $this->DynamicPositions['Data'][$driver_id][$user_lap_nr]['Place'] = 0;
                            $this->DynamicPositions['Data'][$driver_id][$user_lap_nr]['Gap'] = 0;
                            $this->DynamicPositions['Data'][$driver_id][$user_lap_nr]['Laptime'] = 0;
                        }

                        // find leader
                        if ($this->DynamicPositions['Data'][$driver_id][$user_lap_nr]['Place'] == 1) $leading_user_id = $driver_id;

                        // find next assignable position
                        if ($this->DynamicPositions['Data'][$driver_id][$user_lap_nr]['Place'] >= $next_place) $next_place = $this->DynamicPositions['Data'][$driver_id][$user_lap_nr]['Place'] + 1;
                    }

                    // assign laptime
                    $this->DynamicPositions['Data'][$uid][$user_lap_nr]['Laptime'] = $lap->laptime();

                    // assign position
                    $this->DynamicPositions['Data'][$uid][$user_lap_nr]['Place'] = $next_place;
                    if ($next_place > $this->DynamicPositions['Info']['MaxPlace']) $this->DynamicPositions['Info']['MaxPlace'] = $next_place;
                    if ($next_place == 1) $leader_laptimes[] = $lap->laptime();

                    // calculate gap if lapped
                    $gap_lapped = 0;
                    for ($lapidx = $user_lap_nr; $lapidx < count($leader_laptimes); ++$lapidx) {
                        $gap_lapped += $leader_laptimes[$lapidx];
                    }

                    // assign gap
                    $gap = $driver_laps_timesum[$uid] - $driver_laps_timesum[$leading_user_id] + $gap_lapped;
                    $this->DynamicPositions['Data'][$uid][$user_lap_nr]['Gap'] = $gap;
                    if ($this->DynamicPositions['Data'][$uid][$user_lap_nr]['Gap'] > $this->DynamicPositions['Info']['MaxGap']) $this->DynamicPositions['Info']['MaxGap'] = $this->DynamicPositions['Data'][$uid][$user_lap_nr]['Gap'];
                }


            //////////////////////
            // non-race sessions

            // determine positions based on minutes
            } else {
                $driver_besttimes = array(); // stores current best laptime for each driver: key=user-id, value=laptime
                $current_positions_uid = array(); // stores the current user-id ordered by their best time
                $absolute_best_laptime = NULL;
                $outlap_drivers = array(); // list of user-ids (to identify first lap / outlap)

                foreach ($this->laps() as $lap) {
                    $uid = $lap->user()->id();

                    // filter outlaps
                    $is_outlap = !in_array($uid, $outlap_drivers);
                    if (!in_array($uid, $outlap_drivers)) $outlap_drivers[] = $uid;

                    // ignore invalid laps
                    if ($lap->cuts() > 0 || $is_outlap) continue;

                    $user_minute = "{$lap->sessionMinutes()}";

                    // update best times
                    if (!array_key_exists($uid, $driver_besttimes) || $lap->laptime() < $driver_besttimes[$uid]) {

                        // save best times
                        $driver_besttimes[$uid] = $lap->laptime();
                        if ($absolute_best_laptime === NULL || $lap->laptime() < $absolute_best_laptime) {
                            $absolute_best_laptime = $lap->laptime();
                        }

                        // grow driver laps
                        foreach ($this->DynamicPositions['Data'] as $driver_id => $driver_data) {

                            // skip if driver has currently no lap
                            if (!array_key_exists($driver_id, $driver_besttimes)) continue;

                            // initialize driver array
                            if (!array_key_exists($user_minute, $this->DynamicPositions['Data'][$driver_id])) {
                                $this->DynamicPositions['Data'][$driver_id][$user_minute] = array();
                                $this->DynamicPositions['Data'][$driver_id][$user_minute]['Place'] = 0;
                                $this->DynamicPositions['Data'][$driver_id][$user_minute]['Gap'] = 0;
                                $this->DynamicPositions['Data'][$driver_id][$user_minute]['Laptime'] = 0;
                            }

                            // assign gap
                            $this->DynamicPositions['Data'][$driver_id][$user_minute]['Gap'] = $driver_besttimes[$driver_id] - $absolute_best_laptime;
                        }

                        // assign laptime
                        $this->DynamicPositions['Data'][$uid][$user_minute]['Laptime'] = $lap->laptime();

                        // determine current positions
                        $current_positions = array();
                        foreach ($driver_besttimes as $uid_bt=>$bt) {
                            $sbt = new \Core\SessionBestTime();
                            $sbt->UserId = $uid_bt;
                            $sbt->BestLaptime = $bt;
                            $current_positions[] = $sbt;
                        }
                        usort($current_positions, "\Core\SessionBestTime::compare");

                        // assign place
                        for ($pos = 1; $pos <= count($current_positions); ++$pos) {
                            if (!array_key_exists($pos, $current_positions)) continue;
                            $sbt = $current_positions[$pos];
                            $this->DynamicPositions['Data'][$sbt->UserId][$user_minute]['Place'] = $pos;
                        }
                    }
                }
            }
        }

        // return result
        return $this->DynamicPositions;
    }
}
