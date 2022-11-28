<?php

declare(strict_types=1);
namespace Core;

class DriverRankingPoints {

    private $RankingPoints = NULL;


    /**
     * Create a new object
     * @param $json_string If set, the object will be initialized from this json string
     */
    public function __construct(string $json_string=NULL) {
        $this->RankingPoints = DriverRankingPoints::dataTemplate();

        // initialize from json
        if ($json_string !== NULL) {

            $initial_data = json_decode($json_string, TRUE);

            foreach(array_keys($this->RankingPoints) as $key_group) {
                if (!array_key_exists($key_group, $initial_data)) continue;
                foreach(array_keys($this->RankingPoints[$key_group]) as $key_value) {
                    if (!array_key_exists($key_value, $initial_data[$key_group])) continue;
                    $this->RankingPoints[$key_group][$key_value] = (float) $initial_data[$key_group][$key_value];
                }
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


    //! @param $leading_positions The amount of drivers which are worse in a race
    public function addSxR(int $leading_positions) {
        $this->RankingPoints['SX']['R'] = \Core\Acswui::getPAram('DriverRankingSxR') * $leading_positions;
    }


    //! Adding best race-time success
    public function addSxRt() {
        $this->RankingPoints['SX']['BT'] += \Core\Acswui::getPAram('DriverRankingSxRt');
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


    //! @return An array as template for ranking points
    public function dataTemplate() : array {
        $ret = array();
        $ret['XP'] = array('P'=>0,  'Q'=>0,  'R'=>0);
        $ret['SX'] = array('RT'=>0, 'Q'=>0,  'R'=>0, 'BT'=>0);
        $ret['SF'] = array('CT'=>0, 'CE'=>0, 'CC'=>0, 'PEN'=>0);
        return $ret;
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
