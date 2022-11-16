<?php

declare(strict_types=1);
namespace DbEntry;

//! Wrapper for database table element
class TeamCarClass extends DbEntry {

    /**
     * Construct a new object
     * @param $id Database table id
     */
    protected function __construct(int $id) {
        parent::__construct("TeamCarClasses", $id);
    }


    //! @return TRUE if this is an active item, else FALSE
    public function active() : bool {
        return ($this->loadColumn("Active") == 0) ? FALSE : TRUE;
    }


    //! @return The according CarClass object
    public function carClass() : CarClass{
        return CarClass::fromId((int) $this->loadColumn("CarClass"));
    }


    /**
     * Creates a new TeamCarClass
     *
     * When the car-class/team-combination exists, the according object will be returned.
     * If the existing object is not active, it will be reactivated.
     *
     * @param $team The which the car shall be assigned to
     * @param $car_class The CarClass
     * @return The new created TeamCarClass object
     */
    public static function createNew(Team $team, CarClass $car_class) : TeamCarClass {

        // check if class already exists
        $query = "SELECT Id FROM TeamCarClasses WHERE Team={$team->id()} AND CarClass={$car_class->id()};";
        $res = \Core\Database::fetchRaw($query);
        if (count($res) > 0) {
            $tcc = TeamCarClass::fromId((int) $res[0]['Id']);
            $tcc->setActive(TRUE);
            return $tcc;
        }

        // add class
        $columns = array();
        $columns['Team'] = $team->id();
        $columns['CarClass'] = $car_class->id();
        $columns['Active'] = 1;
        $id = (int) \Core\Database::insert("TeamCarClasses", $columns);
        return TeamCarClass::fromId($id);
    }


    /**
     * Retrieve an existing object from database.
     * This function is cached and returns for same IDs the same object.
     * @return An object by its database Id
     */
    public static function fromId(int $id) : ?TeamCarClass {
        return parent::getCachedObject("TeamCarClasses", "TeamCarClass", $id);
    }


    //! @return All available TeamCar objects in this class
    public function listCars() : array {
        return TeamCar::listTeamCars(teamcarclass: $this);
    }


    /**
     * List all TeamCarClass objects from a certain team
     * @param $team The requested Team objects
     * @return A list of TeamCarClass objects
     */
    public static function listTeamCarClasses(Team $team) : array {
        $list = array();
        $query = "SELECT Id FROM TeamCarClasses WHERE Team={$team->id()} AND Active=1 ORDER By Id ASC;";
        $res = \Core\Database::fetchRaw($query);
        foreach ($res as $row) {
            $list[] = TeamCarClass::fromId((int) $row['Id']);
        }
        return $list;
    }


    //! @return The according Team object
    public function team() : Team {
        return Team::fromId((int) $this->loadColumn("Team"));
    }


    //! @param $active TRUE if this object shall be active, else FALSE
    public function setActive(bool $active) {

        // inactivate TeamCars
        if (!$active) {
            foreach ($this->listCars() as $tc) {
                $tc->setActive(FALSE);
            }
        }

        // set this (in)active
        $columns = array();
        $columns['Active'] = ($active) ? 1:0;
        $this->storeColumns($columns);
    }
}
