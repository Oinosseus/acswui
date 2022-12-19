<?php

declare(strict_types=1);
namespace DbEntry;

//! Wrapper for database table element
class RSerQualification extends DbEntry {

    /**
     * Construct a new object
     * @param $id Database table id
     */
    protected function __construct(int $id) {
        parent::__construct("RSerQualifications", $id);
    }


    //! @return The RSerEvent of this qualification
    public function event() : RSerEvent {
        return RSerEvent::fromId((int) $this->loadColumn('Event'));
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) : ?RSerQualification {
        return parent::getCachedObject("RSerQualifications", "RSerQualification", $id);
    }


    //! @return The qualification Lap object
    public function lap() : Lap {
        return Lap::fromId((int) $this->loadColumn('BestLap'));
    }


    /**
     * Add a qualification lap
     *
     * If for the Event and Registration already a qualification lap exists,
     * it the new lap is only taken if it is faster than the old qualification lap.
     *
     * @param $event The RSerEvent
     * @param $registration The RSerRegistration
     * @param $lap The Lap which shall be checked for qualification
     */
    public static function qualify(RSerEvent $event,
                                   RSerRegistration $registration,
                                   Lap $lap) {

        if ($lap->cuts() > 0) return;

        // check if already existing
        $query = "SELECT Id FROM RSerQualifications WHERE Event={$event->id()} AND Registration={$registration->id()}";
        $res = \Core\Database::fetchRaw($query);

        // update existing qualification
        if (count($res)) {
            $qual = RSerQualification::fromId((int) $res[0]['Id']);
            if ($lap->laptime() < $qual->lap()->laptime()) {
                \Core\Database::update("RSerQualifications", $qual->id(), ['BestLap'=>$lap->id()]);
            }

        // add new qualification
        } else {
            \Core\Database::insert("RSerQualifications", ['Event'=>$event->id(),
                                                          'Registration'=>$registration->id(),
                                                          'BestLap'=>$lap->id()]);
        }
    }


    //! @return The registration of this qualification
    public function registration() : RSerRegistration {
        return RSerRegistration::fromId((int) $this->loadColumn('Registration'));
    }
}
