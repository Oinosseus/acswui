<?php

declare(strict_types=1);
namespace DbEntry;

//! Wrapper for database table element
class RSerResultDriver extends DbEntry {


    /**
     * Construct a new object
     * @param $id Database table id
     */
    protected function __construct(int $id) {
        parent::__construct("RSerResultsDriver", $id);
    }


    /**
     * Calculating the results of an event
     * @param $rser_event The RSerEvent
     */
    public static function calculateFromEvent(RSerEvent $rser_event, $update_season_standings=TRUE) {

        // iterate over all car classes
        foreach ($rser_event->season()->series()->listClasses(active_only:FALSE) as $rs_class) {

            // result data
            $results_per_class = array();

            // scan all races of that event and check results of each user
            foreach ($rser_event->listSplits() as $rser_split) {
                foreach ($rser_split->listRaces() as $race_session) {

                    // race result data
                    $results_per_race = array();

                    foreach (SessionResultFinal::listResults($race_session) as $session_result) {

                        // skip results that are not from this class
                        if (!$rs_class->carClass()->validCar($session_result->carSkin()->car())) continue;

                        // check all drivers of that result
                        foreach ($session_result->driver()->users() as $user) {

                            // check if user has really driven laps with that car
                            $query = "SELECT Id FROM Laps WHERE Session={$race_session->id()} AND User={$user->id()} AND CarSkin={$session_result->carSkin()->id()}";
                            $res = \Core\Database::fetchRaw($query);
                            if (count($res) == 0) continue;

                            // get data of user
                            if (!array_key_exists($user->id(), $results_per_race)) {
                                $d = array();
                                $d['Position'] = $session_result->position();
                                $d['PointsPenaly'] = $session_result->penPts();
                                $d['PenaltyDnfDsq'] = $session_result->dnf() | $session_result->dsq();
                                $results_per_race[$user->id()] = $d;
                            } else {
                                if ($session_result->position() < $results_per_race[$user->id()]['Position']) {
                                    $results_per_race[$user->id()]['Position'] = $session_result->position();
                                }
                                $results_per_race[$user->id()]['PointsPenaly'] += $session_result->penPts();
                                $results_per_race[$user->id()]['PenaltyDnfDsq'] |= $session_result->dnf() | $session_result->dsq();
                            }
                        }
                    }

                    // sum race result points
                    foreach ($results_per_race as $user_id=>$data) {

                        // ensure user exists
                        if (!array_key_exists($user_id, $results_per_class)) {
                            $results_per_class[$user_id] = array();
                            $results_per_class[$user_id]['Position'] = 0;
                            $results_per_class[$user_id]['Points'] = 0;
                        }

                        // summing points
                        if (!$data['PenaltyDnfDsq']) {
                            $pts = $rser_event->season()->series()->raceResultPoints($data['Position']);
                            $pts *= $rser_event->valuation();
                            $results_per_class[$user_id]['Points'] += $pts;
                        }
                        $results_per_class[$user_id]['Points'] += $data['PointsPenaly'];
                    }
                }
            }

            // create position sorted list
            $results_list = array();
            foreach ($results_per_class as $user_id => $d) {
                $r = array();
                $r['UserId'] = $user_id;
                $r['Points'] = $d['Points'];
                $results_list[] = $r;
            }
            usort($results_list, function($a, $b) {
                if ($a['Points'] < $b['Points']) return 1;
                if ($a['Points'] > $b['Points']) return -1;
                return 0;
            });

            // delete old results
            \Core\Database::query("DELETE FROM RSerResultsDriver WHERE Event={$rser_event->id()} AND Class={$rs_class->id()}");

            // add to database
            $position_linear = 0;
            $last_points = NULL;
            $last_position = NULL;
            foreach ($results_list as $rslt) {

                // determine position
                ++$position_linear;
                $position_current = ($last_points === $rslt['Points']) ? $last_position : $position_linear;
                $last_position = $position_current;
                $last_points = $rslt['Points'];

                // prepare data
                $columns = array();
                $columns['Class'] = $rs_class->id();
                $columns['User'] = $rslt['UserId'];
                $columns['Event'] = $rser_event->id();
                $columns['Position'] = $position_current;
                $columns['Points'] = $rslt['Points'];
                $columns['StrikeResult'] = 0;

                // wrie to db
                \Core\Database::insert("RSerResultsDriver", $columns);
            }
        }

        // update standings
        if ($update_season_standings) RSerStandingDriver::calculateFromSeason($rser_event->season());
    }


    //! @return The according RSerClass
    public function class() : RSerClass {
        return RSerClass::fromId((int) $this->loadColumn('Class'));
    }


    //! @return The according RSerEvent object
    public function event() : RSerEvent {
        return RSerEvent::fromId((int) $this->loadColumn('Event'));
    }


    //! @return Finding the result of a certain user for a certain event
    public static function findResult(User $user, RSerEvent $event, RSerClass $class) : ?RSerResultDriver {
        $query = "SELECT Id FROM RSerResultsDriver WHERE User={$user->id()} AND Event={$event->id()} AND Class={$class->id()}";
        $res = \Core\Database::fetchRaw($query);
        if (count($res) > 1) {
            \Core\Log::error("Multiple RSerResultsDriver, where User={$user->id()} and Event={$event->id()} and Class={$class->id()}");
            return NULL;
        } else if (count($res) == 0) {
            return NULL;
        }

        return Self::fromId((int) $res[0]['Id']);
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) : ?RSerResultDriver {
        return parent::getCachedObject("RSerResultsDriver", "RSerResultDriver", $id);
    }


    /**
     * List all results
     * @param $event The RSerEvent
     * @param $class The RSerClass
     * @return A list of RSerResultDriver objects, ordered by position
     */
    public static function listResults(RSerEvent $event,
                                       RSerClass $class) : array {
        $list = array();

        $query = "SELECT Id FROM RSerResultsDriver";
        $query .= " WHERE Class={$class->id()}";
        $query .= " AND Event={$event->id()}";
        $query .= " ORDER BY Position ASC";

        foreach (\Core\Database::fetchRaw($query) as $row) {
            $list[] = RSerResultDriver::fromId((int) $row['Id']);
        }

        return $list;
    }


    //! @return The earned points from the result (without any event valuation)
    public function points() : int {
        return (int) $this->loadColumn('Points');
    }


    //! @return The earned points from the result, including event valuation
    public function pointsValuated() : float {
        $pts = (int) $this->loadColumn('Points');
        $pts *= $this->event()->valuation();
        return $pts;
    }


    //! @return The race position from the result
    public function position() : int {
        return (int) $this->loadColumn('Position');
    }


    //! @param $value When TRUE, this result is not evaluated for standings
    public function SetStrikeResult(bool $value) {
        $this->storeColumns(["StrikeResult" => (($value) ? 1 : 0)]);
    }


    //! @return If this result is a strike result (set by RSerStandingDriver::calculateFromSeason() )
    public function strikeResult() : bool {
        return ($this->loadColumn("StrikeResult") == 0) ? FALSE : TRUE;
    }


    //! @return the user/driver of this result
    public function user() : User {
        return User::fromId((int) $this->loadColumn("User"));
    }
}

?>
