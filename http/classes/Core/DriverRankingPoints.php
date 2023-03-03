<?php

declare(strict_types=1);
namespace Core;

class DriverRankingPoints {

    private static $GroupThresholds = NULL;
    private $RankingPoints = NULL;
    private $CountBt = 0;
    private $IdealGroup = NULL;


    /**
     * Create a new object
     * @param $json_string If set, the object will be initialized from this json string
     */
    public function __construct(string $json_string=NULL) {
        $this->RankingPoints = DriverRankingPoints::dataTemplate();

        // initialize from json
        if ($json_string !== NULL) {

            $initial_data = json_decode($json_string, TRUE);
            if (is_array($initial_data)) {
                foreach(array_keys($this->RankingPoints) as $key_group) {
                    if (!array_key_exists($key_group, $initial_data)) continue;
                    foreach(array_keys($this->RankingPoints[$key_group]) as $key_value) {
                        if (!array_key_exists($key_value, $initial_data[$key_group])) continue;
                        $this->RankingPoints[$key_group][$key_value] = (float) $initial_data[$key_group][$key_value];
                    }
                }
            }
        }
    }


    //! @param $other Another DriverRankingPoints object that shall be added to this
    public function add(DriverRankingPoints $other) {
        foreach (array_keys($this->RankingPoints) as $grp) {
            foreach (array_keys($this->RankingPoints[$grp]) as $key) {

                if (!array_key_exists($grp, $other->RankingPoints)) continue;
                if (!array_key_exists($key, $other->RankingPoints[$grp])) continue;
                $this->RankingPoints[$grp][$key] += $other->RankingPoints[$grp][$key];
            }
        }
    }


    //! @param $speed The speed at which the other car was hit
    public function addSfCc(float $speed) {
        $normspeed = $speed / \Core\Acswui::getPAram('DriverRankingCollNormSpeed');
        $this->RankingPoints['SF']['CC'] = \Core\Acswui::getPAram('DriverRankingSfCc') * $normspeed;
    }


    //! @param $speed The speed at which the environment was hit
    public function addSfCe(float $speed) {
        $normspeed = $speed / \Core\Acswui::getPAram('DriverRankingCollNormSpeed');
        $this->RankingPoints['SF']['CE'] = \Core\Acswui::getPAram('DriverRankingSfCe') * $normspeed;
    }


    //! @param $amount_cuts The amount of Cuts to be added
    public function addSfCt(int $amount_cuts) {
        $this->RankingPoints['SF']['CT'] += $amount_cuts * \Core\Acswui::getPAram('DriverRankingSfCt');
    }


    //! @param $penalty_points The amount of penalty points to be added
    public function addSfPen(int $penalty_points) {
        $this->RankingPoints['SF']['PEN'] += $penalty_points;
    }


    //! @param $leading_positions The amount of drivers which are worse in a records table
    public function addSxBt(int $leading_positions) {

        // revert averaging
        if (\Core\Acswui::getPAram('DriverRankingSxBtAvrg'))
                $this->RankingPoints['SX']['BT'] *= $this->CountBt;

        // increment points
        $this->RankingPoints['SX']['BT'] = \Core\Acswui::getPAram('DriverRankingSxBt') * $leading_positions;
        $this->CountBt += 1;

        // apply averaging
        if (\Core\Acswui::getPAram('DriverRankingSxBtAvrg'))
                $this->RankingPoints['SX']['BT'] /= $this->CountBt;
    }


    //! @param $leading_positions The amount of drivers which are worse in a race
    public function addSxR(int $leading_positions) {
        $this->RankingPoints['SX']['R'] = \Core\Acswui::getPAram('DriverRankingSxR') * $leading_positions;
    }


    //! Adding best race-time success
    public function addSxRt() {
        $this->RankingPoints['SX']['RT'] += \Core\Acswui::getPAram('DriverRankingSxRt');
    }


    //! @param $leading_positions The amount of drivers which are worse in a qualifying
    public function addSxQ(int $leading_positions) {
        $this->RankingPoints['SX']['Q'] = \Core\Acswui::getPAram('DriverRankingSxQ') * $leading_positions;
    }


    /**
     * @param $session_type Experience is rated according to the sessiont type
     * @param $driven_meters Teh distance that was passed
     */
    public function addXp(\Enums\SessionType $session_type,
                          float $driven_meters) {
        switch ($session_type) {
            case \Enums\SessionType::Race:
                $this->RankingPoints['XP']['R'] = \Core\Acswui::getPAram('DriverRankingXpR') * $driven_meters / 1e6;
                break;
            case \Enums\SessionType::Qualifying:
                $this->RankingPoints['XP']['Q'] = \Core\Acswui::getPAram('DriverRankingXpQ') * $driven_meters / 1e6;
                break;
            case \Enums\SessionType::Practice:
                $this->RankingPoints['XP']['P'] = \Core\Acswui::getPAram('DriverRankingXpP') * $driven_meters / 1e6;
                break;
            default:
                \Core\Log::warning("Unexpected session type'{$session_type->name}'");
        }
    }



    /**
     * Calculate the ranking group from a given amount of ranking points
     * @param $points The amount of ranking points
     * @return The ranking group
     */
    public static function calculateGroup(float $points) : int {
        $group = NULL;
        for ($g = \Core\Config::DriverRankingGroups - 1; $g >= 0; --$g) {
            $thld = self::groupThreshold($g);
            if ($points >= $thld) {
                $group = $g;
                break;
            }
        }
        return $group;
    }


    /**
     * Calculate Driver ranking points for all drivers since a certain date
     * @warning: Time intense function call!
     * @return An accociative array of UserId=>DriverRankingPoints
     */
    public static function calculateSince(\DateTime $since) : array {

        // \DbEntry\DriverRanking::calculateLatest();
        // User->id() => \Core\DriverRankingPoints
        $user_ranking = array();

        // initialize with all active drivers
        foreach (\DbEntry\User::listDrivers() as $u) {
            if (!$u->isCommunity()) continue; // only care for active drivers which are also part of the community
            $user_ranking[$u->id()] = new \Core\DriverRankingPoints();
        }

        // get results from relevant sessions
        $then = \Core\Database::timestamp($since);
        $query = "SELECT Id FROM Sessions WHERE Timestamp >= '$then' ORDER BY Id ASC;";
        foreach (\Core\Database::fetchRaw($query) as $row) {
            $session = \DbEntry\Session::fromId((int) $row['Id']);

            // walk through all session results
            foreach (\DbEntry\SessionResultFinal::listResults($session) as $rslt) {

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
                        $uid = \DbEntry\Lap::fromId((int) $lap_id)->user()->id();

                        if (array_key_exists($uid, $user_ranking)) {
                            $user_ranking[$uid]->addSxBt($leading_positions);
                        }

                        $leading_positions += 1;
                    }
                }
            }
        }

        // return result
        // $user_ranking_list = array();
        // foreach ($user_ranking as $uid=>$rnk) {
        //     $user_ranking_list[] = $rnk;
        // }
        return $user_ranking;
    }


    //! @return An array as template for ranking points
    public function dataTemplate() : array {
        $ret = array();
        $ret['XP'] = array('P'=>0,  'Q'=>0,  'R'=>0);
        $ret['SX'] = array('RT'=>0, 'Q'=>0,  'R'=>0, 'BT'=>0);
        $ret['SF'] = array('CT'=>0, 'CE'=>0, 'CC'=>0, 'PEN'=>0);
        return $ret;
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
        if (DriverRankingPoints::$GroupThresholds === NULL) {
            DriverRankingPoints::$GroupThresholds = array();

            $type = \Core\ACswui::getPAram("DriverRankingGroupType");
            switch ($type) {
                case "points":
                    $g = \Core\Config::DriverRankingGroups - 1;
                    for (; $g > 0; --$g) {
                        DriverRankingPoints::$GroupThresholds[$g] = \Core\ACswui::getPAram("DriverRankingGroup{$g}Thld");
                    }
                    DriverRankingPoints::$GroupThresholds[$g] = NULL;
                    break;

                case "percent":
                    $most_points = DriverRankingPoints::listLatest()[0]->points();
                    $g = \Core\Config::DriverRankingGroups - 1;
                    for (; $g > 0; --$g) {
                        DriverRankingPoints::$GroupThresholds[$g] = $most_points * \Core\ACswui::getPAram("DriverRankingGroup{$g}Thld") / 100;
                    }
                    DriverRankingPoints::$GroupThresholds[$g] = NULL;
                    break;

                case "drivers":
                    $ranking_list = DriverRankingPoints::listLatest();
                    $ranking_index = 0;//count($ranking_list);
                    $g = \Core\Config::DriverRankingGroups - 1;
                    for (; $g > 0; --$g) {

                        $ranking_index += \Core\ACswui::getPAram("DriverRankingGroup{$g}Thld");
                        if ($ranking_index <= 0) {
                            DriverRankingPoints::$GroupThresholds[$g] = $ranking_list[0]->points() + 1.0;
                        } else if ($ranking_index < count($ranking_list)) {
                            DriverRankingPoints::$GroupThresholds[$g] = $ranking_list[$ranking_index - 1]->points();
                        } else {
                            DriverRankingPoints::$GroupThresholds[$g] = NULL;
                        }
                    }
                    DriverRankingPoints::$GroupThresholds[$g] = NULL;
                    break;

                default:
                    \Core\Log::error("Unknown type '$type'!");
            }
        }

        // check-return
        if (array_key_exists($group, DriverRankingPoints::$GroupThresholds)) {
            return DriverRankingPoints::$GroupThresholds[$group];
        } else {
            \Core\Log::error("Undefined group '$group'!");
            return NULL;
        }
    }


    //! @return The json encoded data
    public function json() : string {
        return json_encode($this->RankingPoints);
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
}
