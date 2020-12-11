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

    public function __toString() {
        return "Car(Id=" . $this->Id . ")";
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

    /**
     * @param $inculde_deprecated If set to TRUE, also deprectaed items are listed (Default: False)
     * @return An array of all available Car objects, ordered by name
     */
    public static function listCars($inculde_deprecated=FALSE) {
        global $acswuiDatabase;

        if (Car::$AllCarsList !== NULL) return Car::$AllCarsList;

        Car::$AllCarsList = array();

        $where = array();
        if ($inculde_deprecated !== TRUE) {
            $where['Deprecated'] = 0;
        }

        foreach ($acswuiDatabase->fetch_2d_array("Cars", ['Id'], $where, 'Name') as $row) {
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
     * @param $inculde_deprecated If set to TRUE, also deprectaed skins are listed (Default: False)
     * @return A List of according CarSkin objects
     */
    public function skins($inculde_deprecated=FALSE) {
        global $acswuiDatabase;

        // return cache
        if ($this->Skins !== NULL) return $this->Skins;

        $where = array();
        $where['Car'] = $this->Id;
        if ($inculde_deprecated !== TRUE) {
            $where['Deprecated'] = 0;
        }

        // create cache
        $this->Skins = array();
        foreach ($acswuiDatabase->fetch_2d_array("CarSkins", ['Id'], $where) as $row) {
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
