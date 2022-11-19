<?php
namespace DbEntry;

//! Cached wrapper to database DriverRanking table element
class DriverRanking extends DbEntry implements \JsonSerializable {

    private $RankingPoints = NULL;
    private $User = NULL;
    private $PointsIncrease = NULL;
    private static $LastRankings = NULL;
    private static $MinGroupPoints = NULL;
    private $Timestamp = NULL;
    private $GroupCurrent = NULL;
    private $GroupNext = NULL;
    private $LastHistory = NULL;

    //! @param $id Database table id
    protected function __construct($id) {
        parent::__construct("DriverRanking", $id);

        $this->RankingPoints = array();
        if ($id === NULL) {
            $this->RankingPoints['XP'] = array('P'=>0,  'Q'=>0,  'R'=>0);
            $this->RankingPoints['SX'] = array('RT'=>0, 'Q'=>0,  'R'=>0, 'BT'=>0);
            $this->RankingPoints['SF'] = array('CT'=>0, 'CE'=>0, 'CC'=>0);
        } else {
            $this->RankingPoints['XP'] = array('P'=>$this->loadColumn("XP_P"),
                                            'Q'=>$this->loadColumn("XP_Q"),
                                            'R'=>$this->loadColumn("XP_R"));
            $this->RankingPoints['SX'] = array('RT'=>$this->loadColumn("SX_RT"),
                                            'Q'=>$this->loadColumn("SX_Q"),
                                            'R'=>$this->loadColumn("SX_R"),
                                            'BT'=>$this->loadColumn("SX_BT"));
            $this->RankingPoints['SF'] = array('CT'=>$this->loadColumn("SF_CT"),
                                            'CE'=>$this->loadColumn("SF_CE"),
                                            'CC'=>$this->loadColumn("SF_CC"));
        }
    }


    /**
     * Compares to objects for better ranking points.
     * The ranking group is ignored
     * This is intended for usort() of arrays with Lap objects
     * @param $rnk1 DriverRanking object
     * @param $rnk2 DriverRanking object
     * @return 1 if $rnk1 is higher, -1 when $rnk2 is high, 0 if both are equal
     */
    public static function compareRankingPoints(DriverRanking $rnk1, DriverRanking $rnk2) {
        if ($rnk1->points() < $rnk2->points()) return 1;
        if ($rnk1->points() > $rnk2->points()) return -1;
        return 0;
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) {
        return parent::getCachedObject("DriverRanking", "DriverRanking", $id);
    }


    public function jsonSerialize() : mixed {
        $a = array();

        $a['UserId'] = $this->User->id();
        $a['Timestamp'] = $this->Timestamp->format('c');
        $a['GroupNext'] = $this->GroupNext;
        $a['GroupCurrent'] = $this->GroupCurrent;
        $a['RankingPoints'] = $this->RankingPoints;
//         $a['DebugSumPoints'] = $this->points(); // only for debug
//         $a['DebugUserName'] = $this->User->name(); // only for debug

        return $a;
    }


    /**
     * This searchest a DriverRanking object in the database for the same user, but which is older than the current object.
     * If the current object is not in the database it will find the newest object from the database.
     * If no database object was found, this returns NULL
     *
     * @return The previous DriverRanking object from the database
     */
    public function lastHistory() {
        if ($this->LastHistory === NULL) {
            if ($this->user() === NULL) {
                \Core\Log::error("No user given for DriverRanking Id={$this->id()}");
            } else {
                $query = "SELECT Id FROM DriverRanking WHERE User = {$this->user()->id()}";
                if ($this->id() !== NULL) {
                    $query .= " AND Id < {$this->id()}";
                }
                $query .= " ORDER BY Id DESC LIMIT 1;";
                $res = \Core\Database::fetchRaw($query);
                if (count($res) > 0) {
                    $this->LastHistory = DriverRanking::fromId((int) $res[0]['Id']);
                }
            }
        }

        return $this->LastHistory;
    }


    /**
     * List the latest DriverRanking objects (not from DB)
     * @param $ranking_group Defines which ranking group shall be listed
     */
    public static function listLatest(int $ranking_group = NULL) {
        $ret = array();

        // load cache from file
        if (DriverRanking::$LastRankings == NULL) {
            DriverRanking::$LastRankings = array();

            // read driver ranking cache file
            $filepath = \Core\Config::AbsPathData . "/htcache/driver_ranking.json";
            if (!file_exists($filepath)) {
                \Core\Log::warning("Cannot find rankings at '$filepath'");
            } else {
                $driver_rankings = json_decode(file_get_contents($filepath), TRUE);

                // get the file modified time
                $filemtime = filemtime($filepath);
                if ($filemtime === FALSE) {
                    \Core\Log::error("Cannot retrieve filemtime from '$filepath'!");
                    $filemtime = NULL;
                } else {
                    $filemtime = date("c", $filemtime);
                    $filemtime = new \DateTime($filemtime);
                }

                // load each object from cache file
                foreach ($driver_rankings as $ranking_serialized) {
                    $rnk = new DriverRanking(NULL);
                    $rnk->User = \DbEntry\User::fromId($ranking_serialized['UserId']);
                    $rnk->Timestamp = new \DateTime($ranking_serialized['Timestamp']);
                    $rnk->GroupNext = (int) $ranking_serialized['GroupNext'];
                    $rnk->GroupCurrent = (int) $ranking_serialized['GroupCurrent'];
                    $rnk->RankingPoints = $ranking_serialized['RankingPoints'];
                    DriverRanking::$LastRankings[] = $rnk;
                }
            }
        }

        // filter groups
        if ($ranking_group === NULL) {
            $ret = DriverRanking::$LastRankings;
        } else {
            foreach (DriverRanking::$LastRankings as $rnk) {
                if ($rnk->group() == $ranking_group) $ret[] = $rnk;
            }
        }

        return $ret;
    }


    /**
     * How many points are required to reach a certain group.
     * @return a float (or NULL for the lowest group)
     */
    public static function minGroupPoints(int $group) {
        if (DriverRanking::$MinGroupPoints === NULL) {
            DriverRanking::$MinGroupPoints = array();

            $type = \Core\ACswui::getPAram("DriverRankingGroupType");
            switch ($type) {
                case "points":
                    $g = 1;
                    for (; $g < \Core\Config::DriverRankingGroups; ++$g) {
                        DriverRanking::$MinGroupPoints[$g] = \Core\ACswui::getPAram("DriverRankingGroup{$g}Thld");
                    }
                    DriverRanking::$MinGroupPoints[$g] = NULL;
                    break;

                case "percent":
                    $most_points = DriverRanking::listLatest()[0]->points();
                    $g = 1;
                    for (; $g < \Core\Config::DriverRankingGroups; ++$g) {
                        DriverRanking::$MinGroupPoints[$g] = $most_points * \Core\ACswui::getPAram("DriverRankingGroup{$g}Thld") / 100;
                    }
                    DriverRanking::$MinGroupPoints[$g] = NULL;
                    break;

                case "drivers":
                    $ranking_list = DriverRanking::listLatest();
                    $ranking_index = 0;
                    $g = 1;
                    for (; $g < \Core\Config::DriverRankingGroups; ++$g) {
                        $ranking_index += \Core\ACswui::getPAram("DriverRankingGroup{$g}Thld");
                        if (count($ranking_list) >= $ranking_index) {
                            DriverRanking::$MinGroupPoints[$g] = $ranking_list[$ranking_index - 1]->points();
                        } else {
                            DriverRanking::$MinGroupPoints[$g] = NULL;
                        }
                    }
                    DriverRanking::$MinGroupPoints[$g] = NULL;
                    break;

                default:
                    \Core\Log::error("Unknown type '$type'!");
            }
        }

        if (array_key_exists($group, DriverRanking::$MinGroupPoints)) {
            return DriverRanking::$MinGroupPoints[$group];
        } else {
            \Core\Log::error("Undefined group '$group'!");
            return NULL;
        }

    }


    /**
     * Returns the amount of ranking points.
     * @param grp If group is NULL, the sum of all points is returned.
     * @param $key If key is NULL, the sum of the group is returned.
     * @todo reviewed
     */
    public function points($grp=NULL, $key=NULL) {
        $ret = 0.0;

        // sum of all points
        if ($grp === NULL) {
            foreach (array_keys($this->RankingPoints) as $grp) {
                foreach (array_keys($this->RankingPoints[$grp]) as $key) {
                    $ret += $this->RankingPoints[$grp][$key];
                }
            }

        // sum of group
        } else if ($key === NULL) {
            foreach (array_keys($this->RankingPoints[$grp]) as $key) {
                $ret += $this->RankingPoints[$grp][$key];
            }

        // single points
        } else {
            $ret = $this->RankingPoints[$grp][$key];
        }

        return $ret;
    }


    /**
     * Pushes this DriverRanking object into the database.
     * This is only allowed on a latest driver ranking object and will fail on objects retrieved from database.
     * Applies the next group as current group
     */
    public function pushHistory() {
        if ($this->id() !== NULL) {
            \Core\Log::error("Cannot re-save existing ranking '" . $this->id() . "'!");

        } else {
            $columns = array();
            $columns['User'] = $this->user()->id();
            $columns['Timestamp'] = \Core\Database::timestamp($this->timestamp());
            foreach (array_keys($this->RankingPoints) as $grp) {
                foreach (array_keys($this->RankingPoints[$grp]) as $key) {
                    $columns["$grp" . "_" . $key] = $this->RankingPoints[$grp][$key];
                }
            }
            $columns['RankingGroup'] = $this->group();
            $this->storeColumns($columns);
        }
    }


    /**
     * @return The current driver ranking group
     */
    public function group() {
        if ($this->GroupCurrent === NULL) {
            $this->GroupCurrent = (int) $this->loadColumn("RankingGroup");
        }
        return $this->GroupCurrent;
    }


    /**
     * @return The driver ranking group at next assignment
     */
    public function groupNext() {
        return $this->GroupNext;
    }


    //! @return A DateTime object from when this driver ranking was updated
    public function timestamp() {

        // update cache
        if ($this->Timestamp === NULL) {

            if ($this->id() === NULL) {
                /* when the latest ranking is loaded from file cache,
                   the timestamp should already be set to the file-modified-time.
                   Otherwise a database entry with valid Id is expected. */
                \Core\Log::debug("Did not expect to end here");
                $this->Timestamp = new \DateTime("now");

            } else {
                $this->Timestamp = new \DateTime($this->loadColumn("Timestamp"));
            }
        }

        return $this->Timestamp;
    }


    /**
     * Calculates the latest driver ranking and stores it into a cache file.
     * @warning This function is very time consuming!
     */
    public static function calculateLatest() {

        $user_ranking = array();

        // initialize with all active drivers
        foreach (\DbEntry\User::listDrivers() as $u) {

            if (!$u->isCommunity()) continue; // only care for active drivers which are also part of the community

            $user_ranking[$u->id()] = array();
            $user_ranking[$u->id()] = array();
            $user_ranking[$u->id()]['XP'] = array('P'=>0,  'Q'=>0,  'R'=>0);
            $user_ranking[$u->id()]['SX'] = array('RT'=>0, 'Q'=>0,  'R'=>0, 'BT'=>0);
            $user_ranking[$u->id()]['SF'] = array('CT'=>0, 'CE'=>0, 'CC'=>0);
        }

        // get timestamp where this ranking starts
        $days = \Core\ACswui::getParam("DriverRankingDays");
        $now = new \Datetime("now");
        $now->sub(new \DateInterval("P$days" . "D"));
        $then = $now->format("c");

        // get results from relevant sessions
        $scanned_sessions = 0;
        $query = "SELECT Id FROM Sessions WHERE Timestamp >= '$then' ORDER BY Id ASC;";
        foreach (\Core\Database::fetchRaw($query) as $row) {
            $session = \DbEntry\Session::fromId($row['Id']);
            $scanned_sessions += 1;

            // walk through all session results
            foreach ($session->results() as $rslt) {

                // apply session result to all drivers
                foreach ($rslt->drivers() as $user) {

                    $uid = $user->id();
                    $ranking_points = $rslt->rankingPoints();

                    // skip user if not an active driver
                    if (!array_key_exists($uid, $user_ranking)) continue;

                    // add results
                    foreach (array_keys($user_ranking[$uid]) as $grp) {
                        foreach (array_keys($user_ranking[$uid][$grp]) as $key) {
                            if ($grp == "SX" && $key == "BT") continue;  // skip, because not existing in session results

                            $user_ranking[$uid][$grp][$key] += $ranking_points[$grp][$key];
                        }
                    }
                }
            }
        }

        // remember for each driver in how many best time tables is tooks place
        $records_table_count = array();
        foreach (array_keys($user_ranking) as $uid) {
            $records_table_count[$uid] = 0;
        }

        // count best time positions
        $scanned_records = 0;
        $records_file = \Core\Config::AbsPathData . "/htcache/records_carclass.json";
        if (file_exists($records_file)) {
            $records = json_decode(file_get_contents($records_file), TRUE);

            foreach (array_keys($records) as $carclass_id) {
                foreach (array_keys($records[$carclass_id]) as $track_id) {
                    $scanned_records += 1;

                    $leading_positions = 0;
                    foreach (array_reverse($records[$carclass_id][$track_id]) as $lap_id) {
                        $uid = \DbEntry\Lap::fromId($lap_id)->user()->id();

                        if (array_key_exists($uid, $user_ranking)) {
                            $user_ranking[$uid]['SX']['BT'] += $leading_positions * \Core\Acswui::getPAram('DriverRankingSxBt');
                            $records_table_count[$uid] += 1;
                        }

                        $leading_positions += 1;
                    }
                }
            }
        }

        // apply averaging of best time points
        if (\Core\Acswui::getPAram('DriverRankingSxBtAvrg')) {
            foreach (array_keys($user_ranking) as $uid) {
                if ($records_table_count[$uid] > 0)
                    $user_ranking[$uid]['SX']['BT'] /= $records_table_count[$uid];
            }
        }


        // create DriverRanking objects
        $driver_rankings = array();
        foreach (array_keys($user_ranking) as $uid) {
            $rnk = new DriverRanking(NULL);
            $rnk->User = \DbEntry\User::fromId($uid);
            $rnk->Timestamp = new \DateTime("now");
            $rnk->RankingPoints = $user_ranking[$uid];
            $rnk->GroupNext = \Core\Config::DriverRankingGroups;
            $rnk->GroupCurrent = \Core\Config::DriverRankingGroups;
            if ($rnk->lastHistory() !== NULL) {
                $rnk->GroupCurrent = $rnk->lastHistory()->group();
            }
            $driver_rankings[] = $rnk;
        }
        usort($driver_rankings, "\DbEntry\DriverRanking::compareRankingPoints");

        // calculate next group assignment
        switch (\Core\ACswui::getPAram("DriverRankingGroupType")) {
            case "points":
                $driverranking_index = 0;
                for ($g=1; $g < \Core\Config::DriverRankingGroups; ++$g) {
                    $min_points = \Core\ACswui::getPAram("DriverRankingGroup{$g}Thld");
                    for (; $driverranking_index < count($driver_rankings) && $driver_rankings[$driverranking_index]->points() > $min_points; ++$driverranking_index) {
                        $driver_rankings[$driverranking_index]->GroupNext = $g;
                    }
                }
                break;

            case "percent":
                $max_point_sum = (count($driver_rankings) > 0) ? $driver_rankings[0]->points() : 0;
                $driverranking_index = 0;
                for ($g=1; $g < \Core\Config::DriverRankingGroups; ++$g) {
                    $min_points = $max_point_sum * \Core\ACswui::getPAram("DriverRankingGroup{$g}Thld") / 100;
                    for (; $driverranking_index < count($driver_rankings) && $driver_rankings[$driverranking_index]->points() > $min_points; ++$driverranking_index) {
                        $driver_rankings[$driverranking_index]->GroupNext = $g;
                    }
                }
                break;

            case "drivers":
                $driverranking_index = 0;
                $max_drivers = 0;
                for ($g=1; $g < \Core\Config::DriverRankingGroups; ++$g) {
                    $max_drivers = $driverranking_index + \Core\ACswui::getPAram("DriverRankingGroup{$g}Thld");
                    for (; $driverranking_index < count($driver_rankings) && $driverranking_index < $max_drivers; ++$driverranking_index) {
                        $driver_rankings[$driverranking_index]->GroupNext = $g;
                    }
                }
            break;

            default:
                \Core\Log::error("Unknown group assignment type '$group_assignment_type'!");
                break;
        }

        // store ranking
        $filepath = \Core\Config::AbsPathData . "/htcache/driver_ranking.json";
        $f = fopen($filepath, "w");
        fwrite($f, json_encode($driver_rankings, JSON_PRETTY_PRINT));
        fclose($f);
        chmod($filepath, 0660);
    }


    //! @return The related User object
    public function user() {
        if ($this->User === NULL) {
            $this->User = User::fromId($this->loadColumn("User"));
        }
        return $this->User;
    }
}
