<?php

/**
 * Cached wrapper to car databse Cars table element
 */
class Car {

    private $Id = NULL;
    private $Model = NULL;
    private $Name = NULL;
    private $Brand = NULL;
    private $Skins = NULL;

    /**
     * @param $id Database table id
     */
    public function __construct($id) {
        $this->Id = $id;
    }

    //! @return The database table id
    public function id() {
        return $this->Id;
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

    //! @return User friendly brand name of the car
    public function brand() {
        if ($this->Brand === NULL) $this->updateFromDb();
        $this->Brand;
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
