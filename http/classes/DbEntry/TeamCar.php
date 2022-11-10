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


    /**
     * Assign a team member as driver of this car.
     * Will do nothing if member is already driver
     * @param $tm The TemMember that shall drive this car
     */
    public function addDriver(TeamMember $tm) {

        // check for existence
        $query = "SELECT Id FROM TeamCarOccupations WHERE Member={$tm->id()} AND Car={$this->id()};";
        $res = \Core\Database::fetchRaw($query);
        if (count($res) > 0) return;


        // add new driver
        \Core\Database::insert("TeamCarOccupations", ['Car'=>$this->id(), "Member"=>$tm->id()]);
        $this->Drivers = NULL;
    }


    //! @return The according CarSKin object
    public function carSkin() : CarSkin {
        return CarSkin::fromId((int) $this->loadColumn("CarSkin"));
    }


    /**
     * Creates a new TeamCar that is owned by a certain user
     * @param $tcc The CarClass of the team where this carskin shall be assigned to
     * @param $car_skin The carskin
     * @return The new created TeamCar object
     */
    public static function createNew(TeamCarClass $tcc, CarSkin $car_skin) : ?TeamCar {

        // check if carskin already exists
        $query = "SELECT Id FROM TeamCars WHERE TeamCarClass={$tcc->id()} AND CarSKin={$car_skin->id()}";
        $res = \Core\Database::fetchRaw($query);
        if (count($res) > 0) return NULL;

        // add carskin
        $columns = array();
        $columns['TeamCarClass'] = $tcc->id();
        $columns['CarSkin'] = $car_skin->id();
        $id = (int) \Core\Database::insert("TeamCars", $columns);
        return TeamCar::fromId($id);
    }


    //! Calling this function, will delete the team car
    public function delete() {

        // delete car occupations
        $query = "DELETE TeamCarOccupations FROM TeamCarOccupations ";
        $query .= "INNER JOIN TeamCars ON TeamCars.Id=TeamCarOccupations.Car ";
        $query .= "WHERE TeamCars.Id={$this->id()}";
        \Core\Database::query($query);

        // delete this item
        $this->deleteFromDb();
    }


    //! @return A list of TeamMember objects that are drivers oif this car
    public function drivers() : array {
        if ($this->Drivers === NULL) {
            $this->Drivers = array();
            $query = "SELECT Member FROM TeamCarOccupations WHERE Car={$this->id()} ORDER BY Id ASC";
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
        $query = "SELECT Id FROM TeamCars WHERE TeamCarClass={$tcc->id()} ORDER By Id ASC;";
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
        $query = "DELETE FROM TeamCarOccupations WHERE Car={$this->id()} AND Member={$tmm->id()}";
        \Core\Database::query($query);
    }


    //! @return The according Team object
    public function team() : Team {
        return Team::fromId((int) $this->loadColumn("Team"));
    }
}
