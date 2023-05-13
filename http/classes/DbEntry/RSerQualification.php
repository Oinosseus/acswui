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


    //! Deletes this qualification
    public function delete() {
        $this->deleteFromDb();
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
     * List all qualifications
     * @param $event The RSerEvent
     * @param $class The RSerClass
     * @return A list of RSerQualification objects
     */
    public static function listQualifications(RSerEvent $event,
                                              RSerClass $class) : array {

        $query = "SELECT RSerQualifications.Id FROM RSerQualifications";
        $query .= " INNER JOIN RSerRegistrations ON RSerQualifications.Registration = RSerRegistrations.Id";
        $query .= " INNER JOIN Laps ON RSerQualifications.BestLap = Laps.Id";
        $query .= " WHERE RSerQualifications.Event={$event->id()} ";
        $query .= " AND RSerRegistrations.Class={$class->id()}";
        $query .= " AND RSerRegistrations.Active!=0";
        $query .= " ORDER BY Laps.Laptime ASC";

        $list = array();
        foreach (\Core\Database::fetchRaw($query) as $row) {
            $list[] = RSerQualification::fromId((int) $row['Id']);
        }
        return $list;
    }


    /**
     * Add a qualification lap
     *
     * @param $event The RSerEvent
     * @param $registration The RSerRegistration
     * @param $lap The Lap which shall be checked for qualification
     */
    public static function qualify(RSerEvent $event,
                                   RSerRegistration $registration,
                                   Lap $lap) {

        // verify correct BOP (ballast/restrictor)
        if ($lap->ballast() < $registration->bopBallast()) {
            \Core\Log::warning("Ignore qualification of $registration for $event with lap $lap, because of BOP ballast mismatch");
            return;
        }
        if ($lap->restrictor() < $registration->bopRestrictor()) {
            \Core\Log::warning("Ignore qualification of $registration for $event with lap $lap, because of BOP restrictor mismatch");
            return;
        }
        if ($lap->cuts() > 0) {
            \Core\Log::warning("Ignore qualification of $registration for $event with lap $lap, because of cuts in lap");
            return;
        }
        if ($lap->session()->track() != $event->track()) {
            \Core\Log::warning("Ignore qualification of $registration for $event with lap $lap, because of wrong track");
            return;
        }
        if ($lap->session()->serverPreset() != $event->season()->series()->parameterCollection()->child('SessionPresetQual')->serverPreset()) {
            \Core\Log::warning("Ignore qualification of $registration for $event with lap $lap, because of wrong server preset");
            return;
        }

        // check if already existing
        $query = "SELECT Id FROM RSerQualifications WHERE Event={$event->id()} AND Registration={$registration->id()}";
        $res = \Core\Database::fetchRaw($query);

        // update existing qualification
        if (count($res)) {
            \Core\Database::update("RSerQualifications", (int) $res[0]['Id'], ['BestLap'=>$lap->id()]);

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
