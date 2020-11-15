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

    private static $LongestDrivenCarClass = NULL;

    /**
     * @param $id Database table id
     */
    public function __construct($id) {
        global $acswuiLog;
        global $acswuiDatabase;

        $this->Id = $id;

        // get basic information
        $res = $acswuiDatabase->fetch_2d_array("CarClasses", ['Name'], ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find CarClasses.Id=" . $this->Id);
            return;
        }

        $this->Name = $res[0]['Name'];
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

    //! @return The unique Database row ID of the CarClass
    public function id() {
        return $this->Id;
    }

    //! @return Name of the CarClass
    public function name() {
        return $this->Name;
    }

    //! @return An array of all available CarClass objects in alphabetical order
    public static function listClasses() {
        global $acswuiDatabase;

        $list = array();

        foreach ($acswuiDatabase->fetch_2d_array("CarClasses", ['Id'], [], 'Name') as $row) {
            $list[] = new CarClass($row['Id']);
        }

        return $list;
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
}

?>
