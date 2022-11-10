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


    //! @return The according CarClass object
    public function carClass() : CarClass{
        return CarClass::fromId((int) $this->loadColumn("CarClass"));
    }


    /**
     * Creates a new TeamCarClass
     * When the car class already is assinged, NULL will be returned
     * @param $team The which the car shall be assigned to
     * @param $car_class The CarClass
     * @return The new created TeamCarClass object (or NULL)
     */
    public static function createNew(Team $team, CarClass $car_class) : ?TeamCarClass {

        // check if class already exists
        $query = "SELECT Id FROM TeamCarClasses WHERE Team={$team->id()} AND CarClass={$car_class->id()};";
        $res = \Core\Database::fetchRaw($query);
        if (count($res) > 0) return NULL;

        // add class
        $columns = array();
        $columns['Team'] = $team->id();
        $columns['CarClass'] = $car_class->id();
        $id = (int) \Core\Database::insert("TeamCarClasses", $columns);
        return TeamCarClass::fromId($id);
    }


    //! Calling this function, will delete the team car class
    public function delete() {

        // delete car occupations
        $query = "DELETE TeamCarOccupations FROM TeamCarOccupations ";
        $query .= "INNER JOIN TeamCars ON TeamCars.Id=TeamCarOccupations.Car ";
        $query .= "INNER JOIN TeamCarClasses ON TeamCarClasses.Id=TeamCars.TeamCarClass ";
        $query .= "WHERE TeamCarClasses.Id={$this->id()}";
        \Core\Database::query($query);

        // delete cars
        $query = "DELETE TeamCars FROM TeamCars ";
        $query .= "INNER JOIN TeamCarClasses ON TeamCarClasses.Id=TeamCars.TeamCarClass ";
        $query .= "WHERE TeamCarClasses.Id={$this->id()}";
        \Core\Database::query($query);

        // delete this item
        $this->deleteFromDb();
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
        return TeamCar::listTeamCars($this);
    }


    /**
     * List all TeamCarClass objects from a certain team
     * @param $team The requested Team objects
     * @return A list of TeamCarClass objects
     */
    public static function listTeamCarClasses(Team $team) : array {
        $list = array();
        $query = "SELECT Id FROM TeamCarClasses WHERE Team={$team->id()} ORDER By Id ASC;";
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
}
