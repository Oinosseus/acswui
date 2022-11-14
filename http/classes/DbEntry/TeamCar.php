<?php

declare(strict_types=1);
namespace DbEntry;

//! Wrapper for database table element
class TeamCar extends DbEntry {

    // cache that stores all drivers of this car
    private $Drivers = NULL;

    /**
     * Construct a new object
     * @param $id Database table id
     */
    protected function __construct(int $id) {
        parent::__construct("TeamCars", $id);
    }


    //! @return TRUE if this is an active item, else FALSE
    public function active() : bool {
        return ($this->loadColumn("Active") == 0) ? FALSE : TRUE;
    }


    /**
     * Assign a team member as driver of this car.
     * Will do nothing if member is already driver
     * @param $tm The TemMember that shall drive this car
     */
    public function addDriver(TeamMember $tm) {

        // check for existence
        $query = "SELECT Id FROM TeamCarOccupations WHERE Member={$tm->id()} AND Car={$this->id()};";
        $res = \Core\Database::fetchRaw($query);

        if (count($res) > 0) {
            $query = "UPDATE TeamCarOccupations SET Active=1 WHERE Car={$this->id()} AND Member={$tm->id()}";
            \Core\Database::query($query);
        } else {
            \Core\Database::insert("TeamCarOccupations", ['Car'=>$this->id(), "Member"=>$tm->id(), 'Active'=>1]);
        }

        $this->Drivers = NULL;
    }


    //! @return The according CarSKin object
    public function carSkin() : CarSkin {
        return CarSkin::fromId((int) $this->loadColumn("CarSkin"));
    }


    /**
     * Creates a new TeamCar that is owned by a certain user
     *
     * When the car-class/team-combination exists, the according object will be returned.
     * If the existing object is not active, it will be reactivated.
     *
     * @param $tcc The CarClass of the team where this carskin shall be assigned to
     * @param $car_skin The carskin
     * @return The new created TeamCar object
     */
    public static function createNew(TeamCarClass $tcc, CarSkin $car_skin) : TeamCar {

        // check if carskin already exists
        $query = "SELECT Id FROM TeamCars WHERE TeamCarClass={$tcc->id()} AND CarSKin={$car_skin->id()}";
        $res = \Core\Database::fetchRaw($query);
        if (count($res) > 0) {
            $tc = TeamCar::fromId((int) $res[0]['Id']);
            $tc->setActive(TRUE);
            return $tc;
        }

        // add carskin
        $columns = array();
        $columns['TeamCarClass'] = $tcc->id();
        $columns['CarSkin'] = $car_skin->id();
        $id = (int) \Core\Database::insert("TeamCars", $columns);
        return TeamCar::fromId($id);
    }


    //! @return A list of TeamMember objects that are drivers oif this car
    public function drivers() : array {
        if ($this->Drivers === NULL) {
            $this->Drivers = array();
            $query = "SELECT Member FROM TeamCarOccupations WHERE Car={$this->id()} AND Active=1 ORDER BY Id ASC";
            $res = \Core\Database::fetchRaw($query);
            foreach ($res as $row) {
                $tmm = TeamMember::fromId((int) $row['Member']);
                if ($tmm) $this->Drivers[] = $tmm;
            }
        }
        return $this->Drivers;
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) : ?TeamCar {
        return parent::getCachedObject("TeamCars", "TeamCar", $id);
    }


    /**
     * List all TeamCar objects from a certain TeamCarClass
     * @param $tcc The requested TeamCarClass objects
     * @return A list of TeamCar objects
     */
    public static function listTeamCars(TeamCarClass $tcc) : array {
        $list = array();
        $query = "SELECT Id FROM TeamCars WHERE TeamCarClass={$tcc->id()} AND Active=1 ORDER By Id ASC;";
        $res = \Core\Database::fetchRaw($query);
        foreach ($res as $row) {
            $list[] = TeamCar::fromId((int) $row['Id']);
        }
        return $list;
    }


    /**
     * Removes a team member as car driver
     * @param $tmm The TeamMember to be removed
     */
    public function removeDriver(TeamMember $tmm) {
        $query = "UPDATE TeamCarOccupations SET Active=0 WHERE Car={$this->id()} AND Member={$tmm->id()}";
        \Core\Database::query($query);
    }


    //! @param $active TRUE if this object shall be active, else FALSE
    public function setActive(bool $active) {

        // inactivate TeamCarOccupations
        if (!$active) {
            $query = "UPDATE TeamCarOccupations SET Active=0 WHERE Car={$this->id()}";
            \Core\Database::query($query);
        }

        // set this (in)active
        $columns = array();
        $columns['Active'] = ($active) ? 1:0;
        $this->storeColumns($columns);
    }


    //! @return The according Team object
    public function team() : Team {
        return Team::fromId((int) $this->loadColumn("Team"));
    }
}
