<?php

declare(strict_types=1);
namespace Compound;

/**
 * Race series standings with team grouping
 */
class RSerTeamStanding {

    private $Entry = NULL;
    private $Points = 0;

    /**
     */
    private function __construct(\DbEntry\User|\DbEntry\Team $entry) {
        $this->Entry = $entry;
    }


    public function addPoints(int|float $points) {
        $this->Points += $points;
    }


    public function entry() : \DbEntry\User | \DbEntry\Team {
        return $this->Entry;
    }


    public function id() : string {
        $id = "???";
        if (is_a($this->Entry, "\\DbEntry\\User")) $id = "User={$this->Entry->id()}";
        if (is_a($this->Entry, "\\DbEntry\\Team")) $id = "Team={$this->Entry->id()}";
        return "RSerTeamStanding[{$id}]";
    }


    public static function listStadnings(\DbEntry\RSerSeason $season) {
        $obj_map = array();

        // summarize points per class
        foreach ($season->series()->listClasses(active_only:FALSE) as $rs_class) {

            $points_per_team = array();  // key=Team.Id, value= points
            $points_per_user = array();  // key=User.Id, value= points
            $points_per_class_per_team = array();  // key=Team.Id, value= list of points

            foreach ($season->listStandings($rs_class) as $rs_sdg) {

                // the following line would only list standings from active registrations
                //if (!$rs_sdg->registration()->active()) continue;

                // add team result
                if ($rs_sdg->registration()->teamCar()) {
                    $team = $rs_sdg->registration()->teamCar()->team();
                    $points = $rs_sdg->points();
                    if (!array_key_exists($team->id(), $points_per_class_per_team))
                        $points_per_class_per_team[$team->id()] = array();
                    $points_per_class_per_team[$team->id()][] = $points;

                // add user result
                } else if ($rs_sdg->registration()->user()) {
                    $user = $rs_sdg->registration()->user();
                    $points = $rs_sdg->points();
                    if (array_key_exists($user->id(), $points_per_user)) {
                        \Core\Log::error("$user has more than one standing in $rs_class");
                    } else {
                        $points_per_user[$user->id()] = $points;
                    }

                // unknown type
                } else {
                    \Core\Log::error("Unexpected type of {$rs_sdg->registration()}");
                }

            }

            // summarize team points
            $method = $season->series()->getParam("PtsClassSum");
            foreach ($points_per_class_per_team as $team_id=>$poin_list) {

                if (!array_key_exists($team_id, $points_per_team))
                    $points_per_team[$team_id] = 0;

                switch ($method) {
                    case "B4":
                        $points_per_team[$team_id] += \Core\Helper::maxNSum(4, $poin_list);
                        break;

                    case "B3":
                        $points_per_team[$team_id] += \Core\Helper::maxNSum(3, $poin_list);
                        break;

                    case "B2":
                        $points_per_team[$team_id] += \Core\Helper::maxNSum(2, $poin_list);
                        break;

                    case "B1":
                        $points_per_team[$team_id] += \Core\Helper::maxNSum(1, $poin_list);
                        break;

                    case "L1":
                        $points_per_team[$team_id] += \Core\Helper::minNSum(1, $poin_list);
                        break;

                    case "L2":
                        $points_per_team[$team_id] += \Core\Helper::minNSum(2, $poin_list);
                        break;

                    case "L3":
                        $points_per_team[$team_id] += \Core\Helper::minNSum(3, $poin_list);
                        break;

                    case "L4":
                        $points_per_team[$team_id] += \Core\Helper::minNSum(4, $poin_list);
                        break;

                    case "AVRG":
                        $points_per_team[$team_id] += array_sum($poin_list) / count($poin_list);
                        break;

                    default:
                        \Core\Log::error("Unexpected method $method!");
                }
            }

            // instantiate objects
            foreach ($points_per_team as $team_id=>$points) {
                $team = \DbEntry\Team::fromId($team_id);
                $obj = new RSerTeamStanding($team);
                if (array_key_exists($obj->id(), $obj_map)) $obj = $obj_map[$obj->id()];
                else $obj_map[$obj->id()] = $obj;
                $obj_map[$obj->id()]->addPoints($points);
            }
            foreach ($points_per_user as $user_id=>$points) {
                $user = \DbEntry\User::fromId($user_id);
                $obj = new RSerTeamStanding($user, $points);
                if (array_key_exists($obj->id(), $obj_map)) $obj = $obj_map[$obj->id()];
                else $obj_map[$obj->id()] = $obj;
                $obj_map[$obj->id()]->addPoints($points);
            }
        }

        // serialize, sort and return
        $obj_list = array();
        foreach ($obj_map as $id=>$obj) {
            if ($obj->points() > 0)
                $obj_list[] = $obj;
        }
        usort($obj_list, "\\Compound\\RSerTeamStanding::usortByPoints");
        return $obj_list;
    }


    //! @return The points of this standing
    public function points() : int|float {
        return $this->Points;
    }


    public static function usortByPoints($a, $b) : int {
        if ($a->points() < $b->points()) return 1;
        else if ($a->points() > $b->points()) return -1;
        else return 0;
    }

}
