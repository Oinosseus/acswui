<?php

namespace DbEntry;

//! Cached wrapper to database DriverRanking table element
class DriverRanking extends DbEntry {

    private $RankingPoints = NULL;
    private $RankingGroup = NULL;
    private $RankingLast = NULL;
    private $User = NULL;
    private $PointsIncrease = NULL;
    private static $LastRankings = NULL;
    private static $MinGroupPoints = NULL;

    //! @param $id Database table id
    public function __construct($id) {
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
     * Adding ranking points.
     * Wrong group or key will be ignored
     */
    public function addPoints($group, $key, float $points) {
        if (array_key_exists($group, $this->RankingPoints)) {
            if (array_key_exists($key, $this->RankingPoints[$group])) {
                $this->RankingPoints[$group][$key] += $points;
            }
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
     * Check if this ranking is equal to another one.
     * Compared are ranking points and ranking group
     * @return TRUE if equal, FALSE if not
     */
    public function equalRankingTo(DriverRanking $other) {

        // round decimal places
        $rnd = 1;

        // check ranking points
        foreach (array_keys($this->RankingPoints) as $grp) {
            foreach (array_keys($this->RankingPoints[$grp]) as $key) {
                if (round($this->RankingPoints[$grp][$key], $rnd) != round($other->RankingPoints[$grp][$key], $rnd)) {
                    return FALSE;
                }
            }
        }

        return TRUE;
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

        // build cache
        if (DriverRanking::$LastRankings == NULL) {
            DriverRanking::$LastRankings = array();

            $filepath = \Core\Config::AbsPathData . "/htcache/driver_ranking.json";
            if (!file_exists($filepath)) {
                \Core\Log::warning("Cannot find rankings at '$filepath'");
            } else {
                $user_rankings = json_decode(file_get_contents($filepath), TRUE);

                foreach (array_keys($user_rankings) as $user_id) {
                    $rnk = new \DbEntry\DriverRanking(NULL);
                    $rnk->setUser(\DbEntry\User::fromId($user_id));

                    // walk over all available points in json
                    foreach (array_keys($user_rankings[$user_id]) as $grp) {
                        foreach (array_keys($user_rankings[$user_id][$grp]) as $key) {

                            // store if json keys match
                            if (array_key_exists($grp, $rnk->RankingPoints)) {
                                if (array_key_exists($key, $rnk->RankingPoints[$grp])) {
                                    $rnk->RankingPoints[$grp][$key] += $user_rankings[$user_id][$grp][$key];
                                }
                            }
                        }
                    }

                    // get last ranking
                    $query = "SELECT Id FROM DriverRanking WHERE User = $user_id ORDER BY Id DESC LIMIT 1;";
                    $res = \Core\Database::fetchRaw($query);
                    $rnk_old = (count($res) == 0) ? NULL : \DbEntry\DriverRanking::fromId($res[0]['Id']);

                    // update ranking group
                    if ($rnk_old !== NULL) {
                        $rnk->RankingGroup = $rnk_old->rankingGroup();
                        $rnk->RankingLast = $rnk_old->rankingLast();
                    }

                    DriverRanking::$LastRankings[] = $rnk;
                }

            }

            usort(DriverRanking::$LastRankings, "\DbEntry\DriverRanking::compareRankingPoints");
        }

        // filter groups
        if ($ranking_group === NULL) {
            $ret = DriverRanking::$LastRankings;
        } else {
            foreach (DriverRanking::$LastRankings as $rnk) {
                if ($rnk->rankingGroup() == $ranking_group) $ret[] = $rnk;
            }
        }

        return $ret;
    }


    /**
     * How many points are required to reach a certain group.
     * @return a float (or NULL)
     */
    public static function minGroupPoints(int $group) {
        if (DriverRanking::$MinGroupPoints === NULL) {
            DriverRanking::$MinGroupPoints = array();

            $type = \Core\ACswui::getPAram("DriverRankingGroupType");
            switch ($type) {
                case "points":
                    $g = 1;
                    for (; $g < \Core\Config::DriverRankingGroups; ++$g) {
                        DriverRanking::$MinGroupPoints[$g] = \Core\ACswui::getPAram("DriverRankingGroup$g" . "Thld");
                    }
                    DriverRanking::$MinGroupPoints[$g] = NULL;
                    break;

                case "percent":
                    $most_points = DriverRanking::listLatest()[0]->points();
                    $g = 1;
                    for (; $g < \Core\Config::DriverRankingGroups; ++$g) {
                        DriverRanking::$MinGroupPoints[$g] = $most_points * \Core\ACswui::getPAram("DriverRankingGroup$g" . "Thld") / 100;
                    }
                    DriverRanking::$MinGroupPoints[$g] = NULL;
                    break;

                case "drivers":
                    $ranking_list = DriverRanking::listLatest();
                    $ranking_index = 0;
                    $g = 1;
                    for (; $g < \Core\Config::DriverRankingGroups; ++$g) {
                        $ranking_index += \Core\ACswui::getPAram("DriverRankingGroup$g" . "Thld");
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
     * If group is NULL, the sum of all points is returned.
     * If key is NULL, the sum of the group is returned.
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

    //! @return The increase of points since the last group assignment
    public function pointsIncrease() {
        if ($this->PointsIncrease === NULL) {
            if ($this->id() !== NULL) {
                $this->PointsIncrease = $this->points() - ((float) $this->loadColumn("RankingLast"));
            } else {
                $query = "SELECT RankingLast FROM DriverRanking WHERE User = {$this->user()->id()} ORDER BY Id DESC LIMIT 1;";
                $res = \Core\Database::fetchRaw($query);
                if (count($res) == 0) $this->PointsIncrease = $this->points();
                else $this->PointsIncrease = $this->points() - ((float) $res[0]['RankingLast']);
            }
        }
        return $this->PointsIncrease;
    }


    //! @return The driver ranking group
    public function rankingGroup() {
        if ($this->RankingGroup === NULL) {

            if ($this->id() === NULL) {
                $this->RankingGroup = \Core\Config::DriverRankingGroups;

            } else {
                $this->RankingGroup = (int) $this->loadColumn("RankingGroup");

                // Since '0' is a DB-Column default value, the groups are stored incremented
                if ($this->RankingGroup <= 0 || $this->RankingGroup > \Core\Config::DriverRankingGroups) {
                    $this->RankingGroup = \Core\Config::DriverRankingGroups;
                }
            }
        }

        return $this->RankingGroup;
    }


    //! @return The sum of driver ranking points at the last group assignment
    public function rankingLast() {
        if ($this->RankingLast === NULL) {
            if ($this->id() === NULL)
                $this->RankingLast = 0.0;
            else
                $this->RankingLast = $this->loadColumn("RankingLast");
        }
        return $this->RankingLast;
    }


    /**
     * Calculating which ranking group should be assigned according to the ranking points
     */
    public function rankingGroupCalculated() {
        $this_points = $this->points();
        $group_calculated = \Core\Config::DriverRankingGroups;
        $type = \Core\ACswui::getPAram("DriverRankingGroupType");
        switch ($type) {
            case "points":
                for ($g=1; $g < \Core\Config::DriverRankingGroups; ++$g) {
                    $min_points = \Core\ACswui::getPAram("DriverRankingGroup$g" . "Thld");
                    if ($this_points >= $min_points) {
                        $group_calculated = $g;
                        break;
                    }
                }
                break;

            case "percent":
                $most_points = DriverRanking::listLatest()[0]->points();
                for ($g=1; $g < \Core\Config::DriverRankingGroups; ++$g) {
                    $min_percent = \Core\ACswui::getPAram("DriverRankingGroup$g" . "Thld");
                    if (($this_points*100/$most_points) >= $min_percent) {
                        $group_calculated = $g;
                        break;
                    }
                }
                break;

            case "drivers":
                $my_position = 0;
                foreach (DriverRanking::listLatest() as $rnk) {
                    ++$my_position;
                    if ($rnk->user()->id() == $this->user()->id()) break;
                }
                $max_drivers = 0;
                for ($g=1; $g < \Core\Config::DriverRankingGroups; ++$g) {
                    $max_drivers += \Core\ACswui::getPAram("DriverRankingGroup$g" . "Thld");
                    if ($my_position <= $max_drivers) {
                        $group_calculated = $g;
                        break;
                    }
                }
                break;

            default:
                \Core\Log::error("Unknown type '$type'!");
        }
        return $group_calculated;
    }


    //! save a new ranking (this will fail on existing rankings!)
    public function save() {
        if ($this->id() !== NULL) {
            \Core\Log::error("Cannot re-save existing ranking '" . $this->id() . "'!");
        } else {
            $columns = array();
            $columns['User'] = $this->user()->id();
            $columns['Timestamp'] = \Core\Database::timestamp(new \DateTime("now"));
            foreach (array_keys($this->RankingPoints) as $grp) {
                foreach (array_keys($this->RankingPoints[$grp]) as $key) {
                    $columns["$grp" . "_" . $key] = $this->RankingPoints[$grp][$key];
                }
            }
            $columns['RankingGroup'] = $this->rankingGroup();
            $columns['RankingLast'] = $this->rankingLast();
            $this->storeColumns($columns);
        }
    }


    public function setUser(User $u) {
        if ($this->id() !== NULL) {
            \Core\Log::error("Cannot change existing ranking '" . $this->id() . "'!");
        } else {
            $this->User = $u;
        }
    }


    //! @return A DateTime object in server Timezone
    public function timestamp() {
        $t = $this->loadColumn("Timestamp");
        return new \DateTime($t);
    }


    //! @return The related User object
    public function user() {
        if ($this->User === NULL) {
            $this->User = User::fromId($this->loadColumn("User"));
        }
        return $this->User;
    }
}
