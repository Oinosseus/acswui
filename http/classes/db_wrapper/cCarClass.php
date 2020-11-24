<?php

/**
 * Cached wrapper to databse CarClasses table element
 */
class CarClass {

    private $Id = NULL;
    private $Name = NULL;
    private $Cars = NULL;

    private $Drivers = NULL;
    private $DrivenLaps = NULL;
    private $DrivenSeconds = NULL;
    private $DrivenMeters = NULL;
    private $Popularity = NULL;

    private $BallastMap = NULL;
    private $RestrictorMap = NULL;

    private static $CarClassesList = NULL;
    private static $LongestDrivenCarClass = NULL;

    /**
     * @param $id Database table id
     */
    public function __construct(int $id) {
        $this->Id = (int) $id;
    }

    /**
     * Add a new car to the car class
     * @param $car The new Car object
     */
    public function addCar(Car $car) {
        global $acswuiDatabase;
        $res = $acswuiDatabase->fetch_2d_array("CarClassesMap", ['Id'], ['Car'=>$car->id(), 'CarClass'=>$this->Id]);
        if (count($res) !== 0) {
            $acswuiLog->logWarning("Ignoring adding existing car map Car::Id=" . $car->id() . ", CarClass::Id=" . $this->Id);
            return;
        }
        $acswuiDatabase->insert_row("CarClassesMap", ['Car'=>$car->id(), 'CarClass'=>$this->Id]);
        $this->Cars[] = $car;
    }

    /**
     * @param $car The requeted Car object
     * @return The necessary ballast for a certain car in the class
     */
    public function ballast($car) {
        global $acswuiDatabase;

        if ($this->BallastMap !== NULL) return $this->BallastMap[$car->id()];

        $this->BallastMap = array();
        foreach ($acswuiDatabase->fetch_2d_array("CarClassesMap", ['Car', 'Ballast'], ['CarClass'=>$this->Id]) as $row) {
            $this->BallastMap[$row['Car']] = $row['Ballast'];
        }

        return $this->BallastMap[$car->id()];
    }

    //! @return A list of Car objects
    public function cars() {
        global $acswuiDatabase;

        // return cache
        if ($this->Cars !== NULL) return $this->Cars;

        // create cache
        $this->Cars = array();
        $query = "SELECT Cars.Id FROM CarClassesMap";
        $query .= " INNER JOIN Cars ON Cars.Id=CarClassesMap.Car";
        $query .= " WHERE CarClassesMap.CarClass=" . $this->Id;
        foreach ($acswuiDatabase->fetch_raw_select($query) as $row) {
            $this->Cars[] = new Car($row['Id']);
        }

        return $this->Cars;
    }

    private function clearCache() {
        $Name = NULL;
        $Cars = NULL;

        $Drivers = NULL;
        $DrivenLaps = NULL;
        $DrivenSeconds = NULL;
        $DrivenMeters = NULL;
        $Popularity = NULL;

        $BallastMap = NULL;
        $RestrictorMap = NULL;

        CarClass::$CarClassesList = NULL;
        CarClass::$LongestDrivenCarClass = NULL;
    }

    /**
     * Create a new car class in the database
     * @param $name An arbitrary name for the new class
     * @return The CarClass object of the new class
     */
    public static function createNew($name) {
        global $acswuiDatabase;
        $id = $acswuiDatabase->insert_row("CarClasses", ['Name'=>$name]);
        return new CarClass($id);
    }

    //! Delete this car class from the database
    public function delete() {
        global $acswuiDatabase;

        // delete maps
        $res = $acswuiDatabase->fetch_2d_array("CarClassesMap", ['Id'], ['CarClass'=>$this->Id]);
        foreach ($res as $row) {
            $acswuiDatabase->delete_row("CarClassesMap", $row['Id']);
        }

        // delete car class
        $acswuiDatabase->delete_row("CarClasses", $this->Id);
    }

    //! @return The amount of driven laps with this car class
    public function drivenLaps() {
        if ($this->DrivenLaps === NULL) $this->updateDriven();
        return $this->DrivenLaps;
    }

    //! @return The amount of driven meters with this car class
    public function drivenMeters() {
        if ($this->DrivenMeters === NULL) $this->updateDriven();
        return $this->DrivenMeters;
    }

    //! @return The amount of driven seconds with this car class
    public function drivenSeconds() {
        if ($this->DrivenSeconds === NULL) $this->updateDriven();
        return $this->DrivenSeconds;
    }

    //! @return A list of User objects that drove at least one lap in this car class
    public function drivers() {
        if ($this->Drivers === NULL) $this->updateDriven();
        return $this->Drivers;
    }

    private function getCarMapId($car) {
        global $acswuiDatabase;
        $res = $acswuiDatabase->fetch_2d_array("CarClassesMap", ['Id'], ['Car'=>$car->id(), 'CarClass'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Invalid request for Car::Id=" . $car->id() . ", CarClass::Id=" . $this->Id);
            return;
        }
        return $res[0]['Id'];
    }

    //! @return The unique Database row ID of the CarClass
    public function id() {
        return $this->Id;
    }

    //! @return An array of all available CarClass objects in alphabetical order
    public static function listClasses() {
        global $acswuiDatabase;

        if (CarClass::$CarClassesList !== NULL) return CarClass::$CarClassesList;

        CarClass::$CarClassesList = array();

        foreach ($acswuiDatabase->fetch_2d_array("CarClasses", ['Id'], [], 'Name') as $row) {
            CarClass::$CarClassesList[] = new CarClass($row['Id']);
        }

        return CarClass::$CarClassesList;
    }

    //! @return The longest track
    public static function longestDrivenCarClass() {
        global $acswuiDatabase;
        if (CarClass::$LongestDrivenCarClass !== NULL) return CarClass::$LongestDrivenCarClass;

        $ldcc = NULL;
        $ldcc_driven_length = 0;
        foreach (CarClass::listClasses() as $cc) {
            $driven_length = $cc->drivenMeters();
            if ($ldcc === NULL || $driven_length > $ldcc_driven_length) {
                $ldcc = $cc;
                $ldcc_driven_length = $driven_length;
            }
        }
        CarClass::$LongestDrivenCarClass = $ldcc;

        return CarClass::$LongestDrivenCarClass;
    }

    //! @return Name of the CarClass
    public function name() {
        global $acswuiDatabase;
        if ($this->Name !== NULL) return $this->Name;

        $res = $acswuiDatabase->fetch_2d_array("CarClasses", ['Name'], ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find CarClasses.Id=" . $this->Id);
            return;
        }
        $this->Name = $res[0]['Name'];

        return $this->Name;
    }

    /**
     * Remove a car from the car class
     * @param $car The Car object to be removed
     */
    public function removeCar(Car $car) {
        global $acswuiDatabase;
        $res = $acswuiDatabase->fetch_2d_array("CarClassesMap", ['Id'], ['Car'=>$car->id(), 'CarClass'=>$this->Id]);
        $map_id = $this->getCarMapId($car);
        $acswuiDatabase->delete_row("CarClassesMap", $map_id);
        $this->clearCache();
    }

    /**
     * Change the name of the car class
     * @param $new_name The new name of the car class
     */
    public function rename($new_name) {
        global $acswuiDatabase;
        $acswuiDatabase->update_row("CarClasses", $this->Id, ["Name"=>$new_name]);
        $this->Name = $new_name;
    }

    /**
     * @param $car The requeted Car object
     * @return The necessary restrictor for a certain car in the class
     */
    public function restrictor($car) {
        global $acswuiDatabase;

        if ($this->RestrictorMap !== NULL) return $this->RestrictorMap[$car->id()];

        $this->RestrictorMap = array();
        foreach ($acswuiDatabase->fetch_2d_array("CarClassesMap", ['Car', 'Restrictor'], ['CarClass'=>$this->Id]) as $row) {
            $this->RestrictorMap[$row['Car']] = $row['Restrictor'];
        }

        return $this->RestrictorMap[$car->id()];
    }


    //! @return A floating point Number [0,1] that represents the popularity of the car class
    public function popularity() {
        global $acswuiDatabase;

        if ($this->Popularity !== NULL) return $this->Popularity;

        // determine longest track
        $this->Popularity = 1.0;
        if (CarClass::LongestDrivenCarClass()->drivenMeters() > 0) {
            $this->Popularity *= $this->drivenMeters() / (CarClass::LongestDrivenCarClass()->drivenMeters());
        } else {
            $this->Popularity = 0;
        }
        if (User::listDrivers() > 0) {
            $this->Popularity *= count($this->drivers()) / count(User::listDrivers());
        } else {
            $this->Popularity = 0;
        }

        return $this->Popularity;
    }

    /**
     * Set A new ballast value for a cetain car in the class
     * @param $car The requested Car object
     * @param $ballast The new ballast value (0...9999)
     */
    public function setBallast(Car $car, int $ballast) {
        global $acswuiDatabase;
        global $acswuiLog;

        // invalidate cache
        $this->BallastMap = NULL;

        if ($ballast < 0 || $ballast > 9999) {
            $acswuiLog->logError("Invalid ballast value: " . $ballast);
            return;
        }

        $map_id = $this->getCarMapId($car);
        $acswuiDatabase->update_row("CarClassesMap", $map_id, ['Ballast'=>$ballast]);
    }

    /**
     * Set A new restrictor value for a cetain car in the class
     * @param $car The requested Car object
     * @param $restrictor The new restrictor value (0...100)
     */
    public function setRestrictor(Car $car, int $restrictor) {
        global $acswuiDatabase;
        global $acswuiLog;

        // invalidate cache
        $this->BallastMap = NULL;

        if ($restrictor < 0 || $restrictor > 100) {
            $acswuiLog->logError("Invalid restrictor value: " . $restrictor);
            return;
        }

        $map_id = $this->getCarMapId($car);
        $acswuiDatabase->update_row("CarClassesMap", $map_id, ['Restrictor'=>$restrictor]);
    }

    //! Update the caches for DrivenMeters and DrivenSeconds
    private function updateDriven() {
        global $acswuiDatabase;

        // list allowed CarIds
        $allowed_car_ids = array();
        foreach ($this->cars() as $car) {
            if (!in_array($car->id(), $allowed_car_ids)) $allowed_car_ids[] = $car->id();
        }

        $this->DrivenLaps = 0;
        $this->DrivenSeconds = 0;
        $this->DrivenMeters = 0;
        $this->Drivers = array();
        $driver_ids = array();

        $query = "SELECT Laps.Laptime, CarSkins.Car, Laps.User, Tracks.Length FROM Laps";
        $query .= " INNER JOIN Sessions ON Sessions.Id=Laps.Session";
        $query .= " INNER JOIN CarSkins ON CarSkins.Id=Laps.CarSkin";
        $query .= " INNER JOIN Tracks ON Tracks.Id=Sessions.Track";
        foreach ($acswuiDatabase->fetch_raw_select($query) as $row) {
            if (!in_array($row['Car'], $allowed_car_ids)) continue;

            $this->DrivenLaps += 1;
            $this->DrivenSeconds += $row['Laptime'] / 1000;
            $this->DrivenMeters += $row['Length'];
            if (!in_array($row['User'], $driver_ids)) $driver_ids[] = $row['User'];
        }

        foreach ($driver_ids as $uid) {
            $this->Drivers[] = new User($uid);
        }
    }

    //! @return True if the requested car object is part of this car class
    public function validCar(Car $car) {
        foreach ($this->cars() as $c) {
            if ($c->id() === $car->id()) return TRUE;
        }
        return FALSE;
    }
}

?>
