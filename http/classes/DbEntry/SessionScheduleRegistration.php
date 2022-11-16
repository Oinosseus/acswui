<?php

namespace DbEntry;

/**
 * Cached wrapper to car databse Tracks table element
 */
class SessionScheduleRegistration extends DbEntry {


    /**
     * @param $id Database table id
     */
    public function __construct($id) {
        parent::__construct("SessionScheduleRegistrations", $id);
    }


    //! @return TRUE if the registration is active
    public function active() {
        $a = (int) $this->loadColumn("Active");
        return ($a == 0) ? FALSE : TRUE;
    }


    //! @return DateTime of when the registration was activated -> only valid if active()
    public function activated() {
        $t = $this->loadColumn("Activated");
        return new \DateTime($t);
    }


    /**
     * Add/Update a registration item.
     * If §car_skin is NULL, the registration will set as not active.
     * If the schedule/user combination already exists, it will be updated
     *
     * @param $schedule The related SessionSchedule object
     * @param $user The User object to be registered (can be NULL if $team_car is given)
     * @param $car_skin The CarSkin for the registration (can be NULL)
     * @param $team_car If set, $user is ignored and $car_skin will be overwritten from TeamCar->carSkin()
     * @return The created/updated SessionScheduleRegistration object
     */
    public static function register(SessionSchedule $schedule,
                                    User $user=NULL,
                                    CarSkin $car_skin=NULL,
                                    TeamCar $team_car=NULL) {
        $ret = NULL;

        // use CarSKin object from TeamCar (if given)
        if ($team_car) {
            $car_skin = $team_car->carSKin();
            $user_id = 0;
        } else {
            $user_id = $user->id();
        }

        // check if schedule is obsolete
        if ($schedule->obsolete() && $car_skin !== NULL) {
            \Core\Log::warning("Deny register User $user_id for obsolete schedule $schedule");
            return NULL;
        }

        // check if CarSkin is occupied
        if ($car_skin !== NULL) {
            $query = "SELECT Id FROM SessionScheduleRegistrations WHERE SessionSchedule = {$schedule->id()} AND User != $user_id AND Active != 0 AND CarSkin = {$car_skin->id()}";
            $res = \Core\Database::fetchRaw($query);
            if (count($res) > 0) {
                \Core\Log::error("Deny registration for User $user_id because duplicated CarSkin {$car_skin->id()} at SessionSchedule {$schedule->id()}!");
                return NULL;
            }
        }

        // unregister users from other cars than TeamCar
        if($team_car) {
            foreach ($team_car->drivers() as $tmm) {
                $query = "UPDATE SessionScheduleRegistrations SET Active=0 WHERE SessionSchedule={$schedule->id()} AND User={$tmm->user()->id()}";
                \Core\Database::query($query);
            }
        }

        $columns = array();
        $columns['User'] = $user_id;
        $columns['SessionSchedule'] = $schedule->id();
        $columns['CarSkin'] = ($car_skin === NULL) ? 0 : $car_skin->id();
        $columns['Active'] = ($car_skin === NULL) ? 0 : 1;
        $columns['TeamCar'] = ($team_car === NULL) ? 0 : $team_car->id();
        if ($car_skin !== NULL) $columns['Activated'] = (new \DateTime("now"))->format("Y-m-d H:i:s");

        // check if combination exists
        if ($team_car) {
            $query = "SELECT Id FROM SessionScheduleRegistrations WHERE SessionSchedule = {$schedule->id()} AND TeamCar = {$team_car->id()}";
        } else {
            $query = "SELECT Id FROM SessionScheduleRegistrations WHERE SessionSchedule = {$schedule->id()} AND User = $user_id";
        }
        $res = \Core\Database::fetchRaw($query);

        if (count($res) == 0) {
            $id = \Core\Database::insert("SessionScheduleRegistrations", $columns);
            $ret = SessionScheduleRegistration::fromId($id);
        } else {
            \Core\Database::update("SessionScheduleRegistrations", $res[0]['Id'], $columns);
            $ret = SessionScheduleRegistration::fromId($res[0]['Id']);
        }

        return $ret;
    }


    //! @return The additional extra ballast
    public function ballast() {
        return (int) $this->loadColumn("Ballast");
    }


    //! @return The CarSkin object of the registeration (can be NULL)
    public function carSkin() {
        $csid = (int) $this->loadColumn("CarSkin");
        if ($csid == 0) return NULL;
        return CarSkin::fromId($csid);
    }

    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) {
        return parent::getCachedObject("SessionScheduleRegistrations", "SessionScheduleRegistration", $id);
    }


    /**
     * List a registration for a certain user/schedule combination
     * @param $schedule The related SessionSchedule object
     * @param $user The requested User object
     * @return A SessionScheduleRegistration object or NULL
     */
    public static function getRegistration(SessionSchedule $schedule, User $user) {
        $query = "SELECT Id FROM SessionScheduleRegistrations WHERE SessionSchedule = {$schedule->id()} AND User = {$user->id()}";
        $res = \Core\Database::fetchRaw($query);
        if (count($res) == 0) return NULL;
        return SessionScheduleRegistration::fromId($res[0]['Id']);
    }


    /**
     * List all registrations from a certain SessionSchedule
     * @param $schedule The related SessionSchedule object
     * @param $only_active If TRUE (default) only active registrations are returned
     * @return A list of SessionScheduleRegistration objects
     */
    public static function listRegistrations(SessionSchedule $schedule, bool $only_active=TRUE) {
        $ret = array();
        if ($schedule->id()) {
            $query = "SELECT Id FROM SessionScheduleRegistrations WHERE SessionSchedule = {$schedule->id()}";
            if ($only_active) $query .= " AND Active != 0";
            $query .= " ORDER BY Activated ASC, Id ASC";
            foreach (\Core\Database::fetchRaw($query) as $row) {
                $ret[] = SessionScheduleRegistration::fromId($row['Id']);
            }
        }
        return $ret;
    }


    //! @return The additional extra restrictor
    public function restrictor() {
        return (int) $this->loadColumn("Restrictor");
    }


    //! @return The according SessionSchedule object
    public function sessionSchedule() {
        $ssid = (int) $this->loadColumn("SessionSchedule");
        return SessionSchedule::fromId($ssid);
    }


    //! @param $ballast The new ballast for this registration
    public function setBallast(int $ballast) {
        if ($ballast < 0 || $ballast > 999) {
            \Core\Log::warning("Ignoring wrong ballast '$ballast'!");
            return;
        }
        $this->storeColumns(["Ballast"=>$ballast]);
    }


    //! @param $restrictor The new ballast for this registration
    public function setRestrictor(int $restrictor) {
        if ($restrictor < 0 || $restrictor > 100) {
            \Core\Log::warning("Ignoring wrong restrictor '$restrictor'!");
            return;
        }
        $this->storeColumns(["Restrictor"=>$restrictor]);
    }


    //! @return The registered TeamCar object (can be NULL on direct driver registrations)
    public function teamCar() : ?TeamCar {
        $id = (int) $this->loadColumn("TeamCar");
        return ($id == 0) ? NULL : TeamCar::fromId($id);
    }


    //! @return The User object of the registration (can be NULL on Team registrations)
    public function user() : ?User {
        $id = (int) $this->loadColumn("User");
        return ($id == 0) ? NULL : User::fromId($id);
    }

}
