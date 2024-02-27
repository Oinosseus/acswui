<?php

declare(strict_types=1);
namespace DbEntry;

//! Wrapper for database table element
class RSerStandingDriver extends DbEntry {


    /**
     * Construct a new object
     * @param $id Database table id
     */
    protected function __construct(int $id) {
        parent::__construct("RSerStandingsDriver", $id);
    }


    /**
     * Calculating the results of an event
     * @param $rser_season The RSerSeason
     */
    public static function calculateFromSeason(RSerSeason $rser_season) {

        // calculate maximum strike-able results
        $strike_results_overall_count = $rser_season->series()->getParam('PtsStrikeRslt');
        $events_driven = $rser_season->countResultedEvents();
        $strike_results_applicable = ($strike_results_overall_count >= $events_driven) ? $events_driven-1 : $strike_results_overall_count;

        // evaluate for each class
        foreach ($rser_season->series()->listClasses(active_only:FALSE) as $rser_class) {

            // determine all drivers of that class and all events
            $list_of_all_user_ids = array();
            $list_of_all_events = array();
            $last_event_idx_with_results = NULL;
            $event_idx = 0;
            foreach ($rser_season->listEvents() as $rser_event) {

                $list_of_all_events[] = $rser_event;

                $rser_results_driver = $rser_event->listResultsDriver($rser_class);
                foreach ($rser_results_driver as $rser_rslt) {
                    if ($rser_rslt->class() !== $rser_class) continue;
                    if (!in_array($rser_rslt->user()->id(), $list_of_all_user_ids)) {
                        $list_of_all_user_ids[] = $rser_rslt->user()->id();
                    }
                }

                if (count($rser_results_driver) > 0) $last_event_idx_with_results = $event_idx++;
            }

            // analyze results for each driver
            $driver_results = array();
            foreach ($list_of_all_user_ids as $user_id) {
                $user = User::fromId($user_id);
                $rslt = array();
                // $rslt['EventPoints'] = array();
                $rslt['EventResults'] = array();
                $rslt['EventPoints'] = array();
                $rslt['StrikeResult'] = array();
                foreach ($list_of_all_events as $rser_event) {
                    $rser_rslt_drvr = RSerResultDriver::findResult($user, $rser_event, $rser_class);
                    $rslt['EventResults'][] = $rser_rslt_drvr;
                    $rslt['EventPoints'][] = ($rser_rslt_drvr === NULL) ? 0 : $rser_rslt_drvr->points();
                    $rslt['StrikeResult'][] = FALSE;
                    if ($rser_rslt_drvr !== NULL) $rser_rslt_drvr->setStrikeResult(FALSE); // will be updated later
                }

                // find strike results
                for ($strk_rslt_idx=0; $strk_rslt_idx<$strike_results_applicable; ++$strk_rslt_idx) {
                    // if ($strk_rslt_idx >= count($rslt['EventPoints'])) break;

                    // find lowest non-striked points
                    $min_points = NULL;
                    for ($event_idx=0; $event_idx<count($rslt['EventPoints']) && $event_idx<=$last_event_idx_with_results; ++$event_idx) {
                        if ($rslt['StrikeResult'][$event_idx] !== FALSE) continue;  // ignore already striked results

                        if ($min_points === NULL || $rslt['EventPoints'][$event_idx] < $min_points) {
                            $min_points = $rslt['EventPoints'][$event_idx];
                        }
                    }
                    echo "Strike Result {$user} min_pts={$min_points}<br>";

                    // assign event with lowest points as strike result
                    if ($min_points !== NULL) {
                        for ($event_idx=0; $event_idx<count($rslt['EventPoints']); ++$event_idx) {
                            if ($rslt['StrikeResult'][$event_idx] !== False) continue;  // ignore already striked results
                            if ($min_points == $rslt['EventPoints'][$event_idx]) {
                                echo "Strike Result {$user} event_idx=$event_idx<br>";
                                $rslt['StrikeResult'][$event_idx] = TRUE;
                                $rslt['EventPoints'][$event_idx] = 0;
                                if ($rslt['EventResults'][$event_idx]!==NULL) $rslt['EventResults'][$event_idx]->setStrikeResult(True);
                                break;
                            }
                        }
                    }
                }

                // remember user result
                $driver_results[$user_id] = $rslt;
            }

            // create position sorted list
            $driver_result_list = array();
            foreach ($driver_results as $user_id => $d) {
                $r = array();
                $r['UserId'] = $user_id;
                $r['Points'] = array_sum($d['EventPoints']);
                $driver_result_list[] = $r;
            }
            usort($driver_result_list, function($a, $b) {
                if ($a['Points'] < $b['Points']) return 1;
                if ($a['Points'] > $b['Points']) return -1;
                return 0;
            });

            // delete old standings
            \Core\Database::query("DELETE FROM RSerStandingsDriver WHERE Season={$rser_season->id()} AND Class={$rser_class->id()}");

            // add to database
            $position_linear = 0;
            $last_points = NULL;
            $last_position = NULL;
            foreach ($driver_result_list as $rslt) {

                // determine position
                ++$position_linear;
                $position_current = ($last_points === $rslt['Points']) ? $last_position : $position_linear;
                $last_position = $position_current;
                $last_points = $rslt['Points'];

                // prepare data
                $columns = array();
                $columns['Class'] = $rser_class->id();
                $columns['User'] = $rslt['UserId'];
                $columns['Season'] = $rser_season->id();
                $columns['Position'] = $position_current;
                $columns['Points'] = $rslt['Points'];

                // wrie to db
                \Core\Database::insert("RSerStandingsDriver", $columns);
            }
        }
    }

    //! @return The according RSerClass
    public function class() : RSerClass {
        return RSerClass::fromId((int) $this->loadColumn('Class'));
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) : ?Self {
        return parent::getCachedObject("RSerStandingsDriver", "RSerStandingDriver", $id);
    }


    /**
     * List all results
     * @param $season The RSerSeason
     * @param $class The RSerClass
     * @return A list of RSerStandingDriver objects, ordered by position
     */
    public static function listResults(RSerSeason $season,
                                       RSerClass $class) : array {
        $list = array();

        $query = "SELECT Id FROM RSerStandingsDriver";
        $query .= " WHERE Class={$class->id()}";
        $query .= " AND Season={$season->id()}";
        $query .= " ORDER BY Position ASC";

        foreach (\Core\Database::fetchRaw($query) as $row) {
            $list[] = Self::fromId((int) $row['Id']);
        }

        return $list;
    }


    //! @return The according RSerSeason object
    public function season() : RSerSeason {
        return RSerSeason::fromId((int) $this->loadColumn('Season'));
    }


    //! @return The earned points from the standing
    public function points() : float {
        return (float) $this->loadColumn('Points');
    }


    //! @return The race position from the standing
    public function position() : int {
        return (int) $this->loadColumn('Position');
    }


    //! @return the user/driver of this standing
    public function user() : User {
        return User::fromId((int) $this->loadColumn("User"));
    }
}
?>
