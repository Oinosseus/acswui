<?php

declare(strict_types=1);
namespace DbEntry;

//! Wrapper for database table element
class RSerStanding extends DbEntry {


    /**
     * Construct a new object
     * @param $id Database table id
     */
    protected function __construct(int $id) {
        parent::__construct("RSerStandings", $id);
    }


    /**
     * Calculating the results of an event
     * @param $season The RSerSeason
     */
    public static function calculateFromSeason(RSerSeason $season) {
        foreach ($season->series()->listClasses(active_only:FALSE) as $rs_class) {

            // scan all registrations for the class
            $registrations = array();
            foreach ($season->listRegistrations($rs_class) as $rs_reg) {
                // if (!$rs_reg->active()) continue;
                $registrations[$rs_reg->id()] = array();
                $registrations[$rs_reg->id()]['Pts'] = array();
                $registrations[$rs_reg->id()]['Reg'] = $rs_reg;
            }

            // list points of each event
            $event_list = $season->listEvents();
            foreach ($event_list as $event) {
                foreach ($event->listResults($rs_class) as $rslt) {
                    $registrations[$rslt->registration()->id()]['Pts'][] = $rslt->points();
                }
            }

            // strike results
            $event_count = $season->countResultedEvents();
            $strike_results = (int) $season->series()->getParam('PtsStrikeRslt');
            if ($strike_results >= $event_count) $strike_results = $event_count - 1;
            if ($strike_results > 0) {
                foreach ($registrations as $idx=>$data) {
                    for ($i=0; $i<$strike_results; ++$i) {
                        $min_pts = min($registrations[$idx]['Pts']);
                        $unset_idx = array_search($min_pts, $registrations[$idx]['Pts']);
                        unset($registrations[$idx]['Pts'][$unset_idx]);
                    }
                }
            }

            // save into database
            foreach ($registrations as $idx=>$data) {

                // find existing result
                $query = "SELECT Id FROM RSerStandings WHERE Season={$season->id()} AND Registration={$data['Reg']->id()} LIMIT 1;";
                $res = \Core\Database::fetchRaw($query);
                $id = NULL;
                if (count($res) > 0) $id = (int) $res[0]['Id'];

                // prepare data
                $columns = array();
                $columns['Season'] = $season->id();
                $columns['Registration'] = $data['Reg']->id();
                $columns['Position'] = 0;
                $columns['Points'] = array_sum($data['Pts']);

                // update DB
                if ($id) {
                    \Core\Database::update("RSerStandings", $id, $columns);
                } else {
                    \Core\Database::insert("RSerStandings", $columns);
                }
            }

            // update positions in database
            $query = "SELECT RSerStandings.Id, RSerStandings.Points FROM RSerStandings";
            $query .= " INNER JOIn RSerRegistrations ON RSerRegistrations.Id = RSerStandings.Registration";
            $query .= " WHERE RSerStandings.Season={$season->id()}";
            $query .= " AND RSerRegistrations.Class={$rs_class->id()}";
            $query .= " ORDER BY RSerStandings.Points DESC;";
            $next_position = 1;
            $last_points = NULL;
            $last_position = 1;
            foreach (\Core\Database::fetchRaw($query) as $row) {
                if ($row['Points'] == $last_points) {
                    \Core\Database::update("RSerStandings", (int) $row['Id'], ['Position'=>$last_position]);
                } else {
                    \Core\Database::update("RSerStandings", (int) $row['Id'], ['Position'=>$next_position]);
                    $last_position = $next_position;
                    $last_points = (int) $row['Points'];
                }
                ++$next_position;
            }
        }
    }


    //! @return The according RSerSeason object
    public function season() : RSerSeason {
        return RSerSeason::fromId((int) $this->loadColumn('Season'));
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) : ?RSerStanding {
        return parent::getCachedObject("RSerStandings", "RSerStanding", $id);
    }


    /**
     * Get a stading
     * @param $season The RSerSeason
     * @param $registration The RSerRegistration
     * @return A RSerStanding object
     */
    public static function getStanding(RSerSeason $season,
                                       RSerRegistration $registration) : ?RSerStanding {
        $query = "SELECT Id FROM RSerStandings";
        $query .= " WHERE Registration={$registration->id()}";
        $query .= " AND Season={$season->id()}";
        $query .= " LIMIT 1;";
        $res = \Core\Database::fetchRaw($query);
        if (count($res) > 0)
            return RSerStanding::fromId((int) $res[0]['Id']);
        else
            return NULL;
    }


    /**
     * List all stadings
     * @param $season The RSerSeason
     * @param $class The RSerClass
     * @return A list of RSerStanding objects, ordered by position
     */
    public static function listStandings(RSerSeason $season,
                                         RSerClass $class) : array {
        $list = array();

        $query = "SELECT RSerStandings.Id FROM RSerStandings";
        $query .= " INNER JOIN RSerRegistrations ON RSerRegistrations.Id = RSerStandings.Registration";
        $query .= " WHERE RSerRegistrations.Class={$class->id()}";
        $query .= " AND RSerStandings.Season={$season->id()}";
        $query .= " ORDER BY Position ASC";

        foreach (\Core\Database::fetchRaw($query) as $row) {
            $list[] = RSerStanding::fromId((int) $row['Id']);
        }

        return $list;
    }


    //! @return The earned points from the result
    public function points() : int {
        return (int) $this->loadColumn('Points');
    }


    //! @return The race position from the result
    public function position() : int {
        return (int) $this->loadColumn('Position');
    }


    //! @return The according RSerRegistration object
    public function registration() : RSerRegistration {
        return RSerRegistration::fromId((int) $this->loadColumn('Registration'));
    }
}
