<?php
namespace DbEntry;

//! Cached wrapper to database DriverRanking table element
class DriverRanking extends DbEntry {

    private $RankingPoints = NULL;
    private static $LastRankings = NULL;
    private static $GroupThresholds = NULL;
    private $GroupNext = NULL;

    private $PointsIncrease = NULL;
    private $Timestamp = NULL;

    //! @param $id Database table id
    protected function __construct($id) {
        parent::__construct("DriverRanking", $id);

        if ($id === NULL) {
            $this->RankingPoints = new \Core\DriverRankingPoints();
        } else {
            $this->RankingPoints = new \Core\DriverRankingPoints($this->loadColumn('RankingData'));
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


    /**
     * List the latest DriverRanking objects (not from DB)
     * @param $ranking_group Defines which ranking group shall be listed
     */
    public static function listLatest(int $ranking_group = NULL) {
        $ret = array();

        // create cache
        if (DriverRanking::$LastRankings == NULL) {

            DriverRanking::$LastRankings = array();

            // find latest ranking for each active driver
            foreach (User::listDrivers() as $user) {
                $query = "SELECT Id FROM DriverRanking WHERE User={$user->id()} ORDER BY Id DESC LIMIT 1;";
                $res = \Core\Database::fetchRaw($query);
                if (count($res) > 0) {
                    DriverRanking::$LastRankings[] = DriverRanking::fromId((int) $res[0]['Id']);
                }
            }

            // sort by ranking
            usort(DriverRanking::$LastRankings, "\\DbEntry\DriverRanking::compareRankingPoints");
        }

        // filter groups
        if ($ranking_group === NULL) {
            $ret = DriverRanking::$LastRankings;
        } else {
            foreach (DriverRanking::$LastRankings as $rnk) {
                if ($rnk->user()->rankingGroup() == $ranking_group) $ret[] = $rnk;
            }
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
     * @return The driver ranking group at next assignment
     */
    public function groupNext() {

        // update cache
        if ($this->GroupNext === NULL) {
            $current_group = $this->user()->rankingGroup();
            $current_points = $this->points();
            $next_group = NULL;

            // promotion
            if ($current_group < (\Core\Config::DriverRankingGroups - 1) &&
                $current_points >= \DbEntry\DriverRanking::groupThreshold($current_group + 1)
            ) {
                    $next_group = $current_group + 1;

            // demotion
            } else if ($current_group > 0 &&
                        $current_points < \DbEntry\DriverRanking::groupThreshold($current_group)
            ) {
                    $next_group = $current_group - 1;
            }

            $this->GroupNext = ($next_group !== NULL) ? $next_group : $current_group;
        }

        // return from cache
        return $this->GroupNext;
    }


    /**
     * Returns the minimum required ranking-points threshold of a certain group
     * If a driver is below this threshold he will be down rated,
     * if he is above he will be promoted
     * @param $group The requested ranking group
     * @return The points threshold
     */
    public static function groupThreshold(int $group) : ?float {

        // update cache
        if (DriverRanking::$GroupThresholds === NULL) {
            DriverRanking::$GroupThresholds = array();

            $type = \Core\ACswui::getPAram("DriverRankingGroupType");
            switch ($type) {
                case "points":
                    $g = \Core\Config::DriverRankingGroups - 1;
                    for (; $g > 0; --$g) {
                        DriverRanking::$GroupThresholds[$g] = \Core\ACswui::getPAram("DriverRankingGroup{$g}Thld");
                    }
                    DriverRanking::$GroupThresholds[$g] = NULL;
                    break;

                case "percent":
                    $most_points = DriverRanking::listLatest()[0]->points();
                    $g = \Core\Config::DriverRankingGroups - 1;
                    for (; $g > 0; --$g) {
                        DriverRanking::$GroupThresholds[$g] = $most_points * \Core\ACswui::getPAram("DriverRankingGroup{$g}Thld") / 100;
                    }
                    DriverRanking::$GroupThresholds[$g] = NULL;
                    break;

                case "drivers":
                    $ranking_list = DriverRanking::listLatest();
                    $ranking_index = 0;//count($ranking_list);
                    $g = \Core\Config::DriverRankingGroups - 1;
                    for (; $g > 0; --$g) {

                        $ranking_index += \Core\ACswui::getPAram("DriverRankingGroup{$g}Thld");
                        if ($ranking_index <= 0) {
                            DriverRanking::$GroupThresholds[$g] = $ranking_list[0]->points() + 1.0;
                        } else if ($ranking_index < count($ranking_list)) {
                            DriverRanking::$GroupThresholds[$g] = $ranking_list[$ranking_index - 1]->points();
                        } else {
                            DriverRanking::$GroupThresholds[$g] = NULL;
                        }
                    }
                    DriverRanking::$GroupThresholds[$g] = NULL;
                    break;

                default:
                    \Core\Log::error("Unknown type '$type'!");
            }
        }

        // check-return
        if (array_key_exists($group, DriverRanking::$GroupThresholds)) {
            return DriverRanking::$GroupThresholds[$group];
        } else {
            \Core\Log::error("Undefined group '$group'!");
            return NULL;
        }
    }


    /**
     * Returns the amount of ranking points.
     * Forwards to \Core\DriverRankingPoints::points()
     * @param grp If group is NULL, the sum of all points is returned.
     * @param $key If key is NULL, the sum of the group is returned.
     * @todo reviewed
     */
    public function points($grp=NULL, $key=NULL) {
        return $this->RankingPoints->points($grp, $key);
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
     * Calculates the latest driver ranking and stores it into database
     * @warning This function is very time consuming!
     */
    public static function calculateLatest() {

        // User->id() => \Core\DriverRankingPoints
        $user_ranking = array();

        // initialize with all active drivers
        foreach (\DbEntry\User::listDrivers() as $u) {
            if (!$u->isCommunity()) continue; // only care for active drivers which are also part of the community
            $user_ranking[$u->id()] = new \Core\DriverRankingPoints();
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
            foreach (SessionResultFinal::listResults($session) as $rslt) {

                // apply session result to all drivers
                foreach ($rslt->driver()->users() as $user) {

                    // skip user if not an active driver
                    $uid = $user->id();
                    if (!array_key_exists($uid, $user_ranking)) continue;

                    // add results
                    $rp = $rslt->rankingPoints();
                    $user_ranking[$uid]->add($rp);
                }
            }
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
                            $user_ranking[$uid]->addSxBt($leading_positions);
                        }

                        $leading_positions += 1;
                    }
                }
            }
        }

        // store into database
        foreach ($user_ranking as $uid=>$drp) {
            $columns = array();
            $columns['User'] = $uid;
            $columns['RankingData'] = $drp->json();
            $columns['RankingPoints'] = $drp->points();
            $columns['RankingGroup'] = User::fromId($uid)->rankingGroup();
            \Core\Database::insert("DriverRanking", $columns);
        }
    }


    //! @return The related User object
    public function user() {
        return User::fromId((int) $this->loadColumn("User"));
    }
}
