<?php

declare(strict_types=1);
namespace DbEntry;

//! Wrapper for database table element
class RSerRegistration extends DbEntry {


    /**
     * Construct a new object
     * @param $id Database table id
     */
    protected function __construct(int $id) {
        parent::__construct("RSerRegistrations", $id);
    }


    //! @return TRUE if this registration is active
    public function active() : bool {
        return ($this->loadColumn("Active") != 0) ? TRUE : FALSE;
    }


    /**
     * @param $include_class_offset If TRUE (default) then the full BOP is returned, else the class offset is ignored.
     * @return The ballast for the next race
     */
    public function bopBallast(bool $include_class_offset=TRUE) {
        $bop = 0;
        $standing = $this->season()->getStanding($this);
        if ($standing) {
            $position = $standing->position();

            // slow incremental BOP
            if ($this->season()->series()->getParam("BopIncremental")) {
                $event_count = $this->season()->countResultedEvents();
                $bop_start_pos = $this->class()->getParam("BopBallastPosition");
                if ($bop_start_pos > $event_count) {
                    $position = $position - $event_count + $bop_start_pos;
                }
            }

            $bop = $this->class()->bopBallast($position, $include_class_offset);
        }

        return $bop;
    }


    /**
     * @param $include_class_offset If TRUE (default) then the full BOP is returned, else the class offset is ignored.
     * @return The restrictor for the next race
     */
    public function bopRestrictor(bool $include_class_offset=TRUE) {
        $bop = 0;
        $standing = $this->season()->getStanding($this);
        if ($standing) {
            $position = $standing->position();

            // slow incremental BOP
            if ($this->season()->series()->getParam("BopIncremental")) {
                $event_count = $this->season()->countResultedEvents();
                $bop_start_pos = $this->class()->getParam("BopRestrictorPosition");
                if ($bop_start_pos > $event_count) {
                    $position = $position - $event_count + $bop_start_pos;
                }
            }

            $bop = $this->class()->bopRestrictor($position, $include_class_offset);
        }

        return $bop;
    }


    //! @return The CarSkin for this registration
    public function carSkin() : ?CarSkin {
        $id = (int) $this->loadColumn("CarSkin");
        $skin = CarSkin::fromId($id);
        if ($skin === NULL)
                \Core\Log::warning("Cannot find CarSKin[Id=$id] for $this!");
        return $skin;
    }


    //! @return The RSerClass which has been registered for
    public function class() : ?RSerClass {
        $id = (int) $this->loadColumn("Class");
        $class = RSerClass::fromId($id);
        if ($class === NULL)
                \Core\Log::warning("Cannot find Class[Id=$id] for $this!");
        return $class;
    }


    /**
     * Register for a season
     *
     * If $teamcar is given then $user and $carskin are ignored.
     * If $teamcar is NULL, then both $user and $carskin must be given.
     *
     * If a similar but inactive registration already exists, it will be reactived.
     *
     * @param $season The season for which the registration shall be done
     * @param $class The class for which the registration shall be done
     * @param $teamcar If not NULL, then this TeamCar is registered ($user and $carskin are ignored)
     * @param $user If $teamcar is NULL, then this defines which User shall be registered
     * @param $carskin If $teamcar is NULL, then this defines which CarSkin shall be registered
     * @return The new created RSerRegistration object
     */
    public static function createNew(RSerSeason $season,
                                     RSerClass $class,
                                     ?TeamCar $teamcar,
                                     User $user=NULL,
                                     CarSkin $carskin=NULL) : ?RSerRegistration {

        // register TeamCar
        if ($teamcar !== NULL) {

            // check if car is allowed for the class
            if ($class->carClass() != $teamcar->carClass()->carClass()) {
                \Core\Log::error("Deny registering {$teamcar->carClass()->carClass()} for {$class->carClass()}");
                return NULL;
            }

            // check if already existing
            $query = "SELECT Id FROM RSerRegistrations WHERE Season={$season->id()} AND TeamCar={$teamcar->id()}";
            $res = \Core\Database::fetchRaw($query);
            $id = NULL;
            if (count($res) > 0) {
                $id = (int) $res[0]['Id'];
                \Core\Database::query("DELETE FROM RSerQualifications WHERE Registration=$id");
            }

            // update database
            $cols = array();
            $cols['User'] = 0;
            $cols['TeamCar'] = $teamcar->id();
            $cols['CarSkin'] = $teamcar->carSkin()->id();
            $cols['Class'] = $class->id();
            $cols['Season'] = $season->id();
            $cols['Active'] = 1;
            if ($id == NULL) {
                $id = \Core\Database::insert("RSerRegistrations", $cols);
            } else {
                \Core\Database::update("RSerRegistrations", $id, $cols);
            }

            return RSerRegistration::fromId($id);

        // register single user
        } else {

            // check if car is allowed for the class
            $is_valid = FALSE;
            foreach ($class->carClass()->cars() as $car) {
                if ($carskin->car() == $car) {
                    $is_valid = TRUE;
                    break;
                }
            }
            if (!$is_valid) {
                \Core\Log::error("Deny registering $carskin for $class");
                return NULL;
            }

            // check if already existing
            $query = "SELECT Id FROM RSerRegistrations WHERE Season={$season->id()} AND User={$user->id()};";
            $res = \Core\Database::fetchRaw($query);
            $id = NULL;
            if (count($res) > 0) {
                $id = (int) $res[0]['Id'];
                \Core\Database::query("DELETE FROM RSerQualifications WHERE Registration=$id");
            }

            // update database
            $cols = array();
            $cols['User'] = $user->id();
            $cols['TeamCar'] = 0;
            $cols['CarSkin'] = $carskin->id();
            $cols['Class'] = $class->id();
            $cols['Season'] = $season->id();
            $cols['Active'] = 1;
            if ($id == NULL) {
                $id = \Core\Database::insert("RSerRegistrations", $cols);
            } else {
                \Core\Database::update("RSerRegistrations", $id, $cols);
            }

            return RSerRegistration::fromId($id);
        }
    }


    //! deactivates this registration  (can be re-activated with createNew()
    public function deactivate() {
        $this->storeColumns(["Active"=>0]);
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) : ?RSerRegistration {
        return parent::getCachedObject("RSerRegistrations", "RSerRegistration", $id);
    }


    /**
     * List all registrations for a season of a certain class
     * @param $season The RSerSeason
     * @param $class The RSerClass
     * @param $active_only If TRUE (default=FALSE), then only actrive registrations are returned
     * @return A list of RSerRegistration objects
     */
    public static function listRegistrations(RSerSeason $season,
                                             ?RSerClass $class,
                                             bool $active_only=FALSE) : array {

        $list = array();

        $query = "SELECT Id FROM RSerRegistrations WHERE Season={$season->id()}";
        if ($class) $query .= " AND Class={$class->id()}";
        if ($active_only) $query .= " AND Active!=0";
        $res = \Core\Database::fetchRaw($query);
        foreach ($res as $row) {
            $reg = RSerRegistration::fromId((int) $row['Id']);
            $list[] = $reg;
        }

        return $list;
    }


    //! @return THe registered RSerSeason object
    public function season() : RSerSeason {
        return RSerSeason::fromId((int) $this->loadColumn("Season"));
    }


    //! @return The registered TeamCar
    public function teamCar() : ?TeamCar {
        return TeamCar::fromId((int) $this->loadColumn("TeamCar"));
    }


    //! @return The User (if not a teamcar registration)
    public function user() : ?User {
        $id = (int) $this->loadColumn("User");
        return ($id == 0) ? NULL : User::fromId();
    }
}
