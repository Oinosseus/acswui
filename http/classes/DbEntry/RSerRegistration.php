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


    //! @return The CarSkin for this registration
    public function carSkin() : CarSkin {
        return CarSkin::fromId((int) $this->loadColumn("CarSkin"));
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

        //! @todo TBD Delete existing qualifications

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
            if (count($res) > 0) $id = (int) $res[0]['Id'];

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
            if (count($res) > 0) $id = (int) $res[0]['Id'];

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
     * @return A list of RSerRegistration objects
     */
    public static function listRegistrations(RSerSeason $season,
                                             RSerClass $class) : array {

        $list = array();

        $query = "SELECT Id FROM RSerRegistrations WHERE Season={$season->id()} AND Class={$class->id()}";
        $res = \Core\Database::fetchRaw($query);
        foreach ($res as $row) {
            $reg = RSerRegistration::fromId((int) $row['Id']);
            $list[] = $reg;
        }

        return $list;
    }


    //! @return The registered TeamCar
    public function teamCar() : ?TeamCar {
        return TeamCar::fromId((int) $this->loadColumn("TeamCar"));
    }


    //! @return The User (if not a teamcar registration)
    public function user() : ?User {
        return User::fromId((int) $this->loadColumn("User"));
    }
}
