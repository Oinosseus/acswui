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
                            $registrations[$rs_reg]['Pts'] += $event->season()->series()->raceResultPoints($postion + $last_position_of_previous_split);
                            $position += 1;
                            if ($position > $split_highest_position)
                                    $split_highest_position = $position;
                        }
                    }
                }

                $last_position_of_previous_split = $split_highest_position;
            }

            // assign positions
            usort($registrations, "\\DbEntry\\RSerResult::usortRegistrationArrayByPoints");
            for ($idx=0; $idx < count($registrations); ++$idx)
                $registrations[$idx]['Pos'] = $idx + 1;
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
