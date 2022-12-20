<?php

declare(strict_types=1);
namespace DbEntry;

//! Wrapper for database table element
class RSerResult extends DbEntry {


    /**
     * Construct a new object
     * @param $id Database table id
     */
    protected function __construct(int $id) {
        parent::__construct("RSerResults", $id);
    }


    /**
     * Calculating the results of an event
     * @param $event The RSerEvent
     */
    public static function calculateFromEvent(RSerEvent $event) {

        // iterate over all car classes
        foreach ($event->season()->series()->listClasses() as $rs_class) {

            // start postion assignment
            $position = 1;

            // scan all registrations for the class
            $registrations = array();
            foreach ($event->season()->listRegistrations($rs_class) as $rs_reg) {
                $registrations[$rs_reg->id()] = array();
                $registrations[$rs_reg->id()]['Pos'] = 0;
                $registrations[$rs_reg->id()]['Pts'] = 0;
                $registrations[$rs_reg->id()]['Reg'] = $rs_reg;
            }

            // find all splits
            $split_nr = 0;
            $last_position_of_previous_split = 0;
            foreach ($event->listSplits() as $rs_split) {
                ++$split_nr;

                // remember highest position of this split
                $split_highest_position = 0;

                foreach ($rs_split->listRaces() as $session) {

                    $position = 1;
                    foreach (SessionResultFinal::listResults($session) as $srslt) {

                        $rs_reg = $srslt->rserRegistration();
                        if ($rs_reg) {

                            // ensure to have the registration
                            if (!array_key_exists($rs_reg->id(), $registrations)) {
                                $registrations[$rs_reg->id()] = array();
                                $registrations[$rs_reg->id()]['Pos'] = 0;
                                $registrations[$rs_reg->id()]['Pts'] = 0;
                                $registrations[$rs_reg->id()]['Reg'] = $rs_reg;
                            }

                            // add points
                            if (!$srslt->dnf() && !$srslt->dsq()) {
                                $registrations[$rs_reg->id()]['Pts'] += $event->season()->series()->raceResultPoints($position + $last_position_of_previous_split);
                            }
                            $registrations[$rs_reg->id()]['Pts'] += $srslt->penPts();
                            $position += 1;
                            if ($position > $split_highest_position)
                                    $split_highest_position = $position;
                        }
                    }
                }

                $last_position_of_previous_split = $split_highest_position;
            }

            // save into database
            foreach ($registrations as $idx=>$data) {

                // find existing result
                $query = "SELECT Id FROM RSerResults WHERE Event={$event->id()} AND Registration={$data['Reg']->id()} LIMIT 1;";
                $res = \Core\Database::fetchRaw($query);
                $id = NULL;
                if (count($res) > 0) $id = (int) $res[0]['Id'];

                // prepare data
                $columns = array();
                $columns['Event'] = $event->id();
                $columns['Registration'] = $data['Reg']->id();
                $columns['Position'] = 0;
                $columns['Points'] = $data['Pts'];

                // update DB
                if ($id) {
                    \Core\Database::update("RSerResults", $id, $columns);
                } else {
                    \Core\Database::insert("RSerResults", $columns);
                }
            }

            // update positions in database
            $query = "SELECT Id, Points FROM RSerResults WHERE Event={$event->id()} ORDER BY Points DESC;";
            $next_position = 1;
            $last_points = NULL;
            $last_position = 1;
            foreach (\Core\Database::fetchRaw($query) as $row) {
                if ($row['Points'] == $last_points) {
                    \Core\Database::update("RSerResults", (int) $row['Id'], ['Position'=>$last_position]);
                } else {
                    \Core\Database::update("RSerResults", (int) $row['Id'], ['Position'=>$next_position]);
                    $last_position = $next_position;
                    $last_points = (int) $row['Points'];
                }
                ++$next_position;
            }
        }
    }


    //! @return The according RSerEvent object
    public function event() : RSerEvent {
        return RSerEvent::fromId((int) $this->loadColumn('Event'));
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) : ?RSerResult {
        return parent::getCachedObject("RSerResults", "RSerResult", $id);
    }


    /**
     * List all results
     * @param $event The RSerEvent
     * @param $class The RSerClass
     * @return A list of RSerResult objects, ordered by position
     */
    public static function listResults(RSerEvent $event,
                                RSerClass $class) : array {
        $list = array();

        $query = "SELECT RSerResults.Id FROM RSerResults";
        $query .= " INNER JOIN RSerRegistrations ON RSerRegistrations.Id = RSerResults.Registration";
        $query .= " WHERE RSerRegistrations.Class={$class->id()}";
        $query .= " AND RSerResults.Event={$event->id()}";
        $query .= " ORDER BY Position ASC";

        foreach (\Core\Database::fetchRaw($query) as $row) {
            $list[] = RSerResult::fromId((int) $row['Id']);
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


    private static function usortRegistrationArrayByPoints($a, $b) {
        if ($a['Pts'] < $b['Pts']) return 1;
        else if ($a['Pts'] > $b['Pts']) return 1;
        else return 0;
    }
}
