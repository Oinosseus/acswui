<?php

/**
 * Cached wrapper to car databse Cars table element
 */
class Car {

    private $Id = NULL;
//     private $CarClass = NULL;
    private $Model = NULL;
    private $Name = NULL;
    private $Brand = NULL;
    private $Skins = NULL;
    private static $AllCarsList = NULL;

    /**
     * @param $id Database table id
     * @param $car_class An optional CarClass object
     */
    public function __construct($id) {//, $car_class = NULL) {
        $this->Id = $id;
//         $this->CarClass = $car_class;
    }

    //! @return User friendly brand name of the car
    public function brand() {
        if ($this->Brand === NULL) $this->updateFromDb();
        return $this->Brand;
    }

    //! @return The database table id
    public function id() {
        return $this->Id;
    }

    //! @return An array of all available Car objects, ordered by name
    public static function listCars() {
        global $acswuiDatabase;

        if (Car::$AllCarsList !== NULL) return Car::$AllCarsList;

        Car::$AllCarsList = array();

        foreach ($acswuiDatabase->fetch_2d_array("Cars", ['Id'], [], 'Name') as $row) {
            Car::$AllCarsList[] = new Car($row['Id']);
        }

        return Car::$AllCarsList;
    }

    //! @return model name of the car
    public function model() {
        if ($this->Model === NULL) $this->updateFromDb();
        return $this->Model;
    }

    //! @return User friendly name of the car
    public function name() {
        if ($this->Name === NULL) $this->updateFromDb();
        return $this->Name;
    }

    /**
     * @return A List of according CarSkin objects
     */
    public function skins() {
        global $acswuiDatabase;

        // return cache
        if ($this->Skins !== NULL) return $this->Skins;

        // create cache
        $this->Skins = array();
        foreach ($acswuiDatabase->fetch_2d_array("CarSkins", ['Id'], ['Car'=>$this->Id]) as $row) {
            $this->Skins[] = new CarSkin($row['Id']);
        }

        return $this->Skins;
    }

    private function updateFromDb() {
        global $acswuiDatabase;
        global $acswuiLog;

        // get basic information
        $res = $acswuiDatabase->fetch_2d_array("Cars", ['Car', 'Name', 'Brand'], ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find Cars.Id=" . $this->Id);
            return;
        }

        $this->Model = $res[0]['Car'];
        $this->Name = $res[0]['Name'];
        $this->Brand = $res[0]['Brand'];
    }
}

?>
